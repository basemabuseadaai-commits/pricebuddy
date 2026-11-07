<?php

namespace App\Services;

use App\Dto\StandardStrategyDto;
use App\Models\ProductSource;
use App\Services\Helpers\CurrencyHelper;
use App\Settings\AppSettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Jez500\WebScraperForLaravel\Exceptions\DomSelectorException;
use Jez500\WebScraperForLaravel\Facades\WebScraper;
use Jez500\WebScraperForLaravel\WebScraperInterface;
use Psr\Log\LoggerInterface;

class ProductSourceSearchService
{
    protected LoggerInterface $logger;

    protected ?string $htmlMemoryCache = null;

    public bool $logErrors = true;

    protected array $keys = [
        'list_container',
        'product_title',
        'product_url',
    ];

    public function __construct(protected ProductSource $source)
    {
        // @phpstan-ignore-next-line - withContext is valid.
        $this->logger = Log::channel('db')->withContext(['source' => $source->getKey()]);
    }

    public static function new(ProductSource $source): self
    {
        return resolve(static::class, ['source' => $source]);
    }

    public function makeScraper(string $query, ?string $brand = null, bool $useCache = true): WebScraperInterface
    {
        return WebScraper::make($this->source->scraper_service)
            ->from($this->buildSearchUrl($query, $brand))
            // Same page cache time as product pages (Settings > Scrape cache ttl).
            ->setCacheMinsTtl(AppSettings::new()->scrape_cache_ttl)
            ->setUseCache($useCache)
            // e.g. ['sleep' => 8000] for pages that fill in their products slowly.
            ->setOptions((array) data_get($this->source->settings, 'scraper_options', []));
    }

    /**
     * Whether this source can filter its search by brand: settings.brand_param
     * (appended to the search URL) or settings.brand_search_url (a whole URL).
     */
    public function supportsBrandFilter(): bool
    {
        $settings = $this->source->settings ?? [];

        return filled(data_get($settings, 'brand_param')) || filled(data_get($settings, 'brand_search_url'));
    }

    /**
     * Search, optionally limited to one brand with the shop's own brand filter.
     * Returns nothing when the shop doesn't know the brand.
     */
    public function search(string $query, ?string $brand = null): Collection
    {
        if (filled($brand) && $this->supportsBrandFilter()) {
            $brand = $this->resolveBrand($query, $brand);

            if ($brand === null) {
                return collect();
            }
        } else {
            $brand = null;
        }

        $strategy = data_get($this->source, 'extraction_strategy', []);
        $items = $this->getList($query, $brand);

        try {
            // For each result, instantiate a new scraper and extract the title and url.
            return $items->map(function ($item) use ($strategy) {
                $itemScraper = WebScraper::http()->setBody($item);

                $price = ! empty($strategy['product_price'])
                    ? $this->extractValue($itemScraper, $strategy['product_price'], 'product_price')
                    : null;

                return [
                    'title' => $this->extractValue($itemScraper, $strategy['product_title'], 'product_title'),
                    'url' => $this->scrapeUrl($itemScraper, $strategy),
                    // Search pages show English-formatted prices ("1,299.00").
                    'price' => filled($price) ? CurrencyHelper::toFloat($price, 'en') : null,
                    'content' => $item,
                ];
            })
                ->reject(fn ($item) => empty($item['title']) || empty($item['url']))
                ->values();
        } catch (DomSelectorException $e) {
            $this->errorLog($e->getMessage());

            return collect();
        }
    }

    public function getHtml(string $query): ?string
    {
        if (is_null($this->htmlMemoryCache)) {
            // We cache the body response for 5 minutes to prevent multiple scrapes.
            $this->htmlMemoryCache = cache()
                ->remember(
                    'product_source_search:html:'.md5($this->source->updated_at.':'.$query),
                    now()->addMinutes(5),
                    fn () => $this->makeScraper($query)->get()->getBody()
                );
        }

        return $this->htmlMemoryCache;
    }

    public function getList(string $query, ?string $brand = null): Collection
    {
        $strategy = data_get($this->source, 'extraction_strategy', []);

        $scraper = $this->makeScraper($query, $brand)->get();

        if ($errors = $scraper->getErrors()) {
            $this->errorLog('Error scraping Product Source search result page', [
                'store_id' => $this->source->getKey(),
                'errors' => $errors,
            ]);

            return collect();
        }

        return $this->scrapeOption($scraper, ($strategy['list_container']));
    }

    /**
     * @param  ?string  $brand  the shop's own brand value (see resolveBrand())
     */
    public function buildSearchUrl(string $query, ?string $brand = null): string
    {
        $settings = $this->source->settings ?? [];
        $template = filled($brand) && filled(data_get($settings, 'brand_search_url'))
            ? data_get($settings, 'brand_search_url')
            : $this->source->search_url;

        $url = str_replace(
            [':search_term', '{brand}'],
            [urlencode($query), rawurlencode((string) $brand)],
            $template
        );

        // settings.brand_param is appended with {brand} replaced, e.g. "&rh=p_123:{brand}".
        $brandParam = data_get($settings, 'brand_param');
        if (filled($brand) && filled($brandParam)) {
            $url .= str_replace('{brand}', rawurlencode($brand), $brandParam);
        }

        return $url;
    }

    /**
     * Turn a brand name into the value the shop's filter expects.
     *
     * settings.brand_format "lower_underscore" gives "cuisine_art" for "Cuisine Art".
     * settings.brand_lookup {pattern} is a regex run on the search page for
     * "<brand> <query>"; its named groups `name` and optional `value` list the
     * shop's brands, and the one whose name matches is used. Null = not found.
     */
    public function resolveBrand(string $query, string $brand): ?string
    {
        $settings = $this->source->settings ?? [];
        $pattern = data_get($settings, 'brand_lookup.pattern');

        if (blank($pattern)) {
            return data_get($settings, 'brand_format') === 'lower_underscore'
                ? str_replace(' ', '_', mb_strtolower(trim($brand)))
                : trim($brand);
        }

        $cacheKey = 'product_source:brand:'.$this->source->getKey().':'.md5(mb_strtolower($brand).'|'.$pattern);

        $value = cache()->get($cacheKey);
        if ($value !== null) {
            return $value === '' ? null : $value;
        }

        $scraper = $this->makeScraper(trim($brand.' '.$query), useCache: false)->get();

        // A failed fetch (timeout, block) says nothing about the brand: try again next run.
        if ($scraper->getErrors()) {
            return null;
        }

        $options = self::brandOptions((string) $scraper->getBody(), $pattern);
        $value = self::matchBrand($options, $brand);

        if ($value !== null) {
            cache()->put($cacheKey, $value, now()->addDays(7));
        } elseif ($options !== []) {
            // The page listed brands, just not this one. A page without a brand
            // list (blocked, captcha) is not remembered, so the next run retries.
            cache()->put($cacheKey, '', now()->addHours(6));
        }

        return $value;
    }

    /**
     * @return array<string, string> brand name => filter value
     */
    public static function brandOptions(string $html, string $pattern): array
    {
        if (@preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $options = [];
        foreach ($matches as $match) {
            $name = trim(html_entity_decode((string) ($match['name'] ?? $match[1] ?? ''), ENT_QUOTES | ENT_HTML5));
            if ($name !== '') {
                $options[$name] ??= trim((string) ($match['value'] ?? $name));
            }
        }

        return $options;
    }

    /**
     * Exact name first (any case), then a name that starts with the brand.
     *
     * @param  array<string, string>  $options
     */
    public static function matchBrand(array $options, string $brand): ?string
    {
        $wanted = mb_strtolower(trim($brand));

        foreach ($options as $name => $value) {
            if (mb_strtolower($name) === $wanted) {
                return $value;
            }
        }

        foreach ($options as $name => $value) {
            if (str_starts_with(mb_strtolower($name), $wanted)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Extract a single value from a slot, applying prepend/append (and regex /
     * schema.org handling) via the shared StrategyExtractor. Returns null and
     * logs if the selector is invalid, so one bad item does not abort the search.
     *
     * @param  array<string, mixed>  $slot
     */
    protected function extractValue(WebScraperInterface $scraper, array $slot, string $field): ?string
    {
        $dto = StandardStrategyDto::fromArray($slot);

        if ($dto === null) {
            return null;
        }

        try {
            return StrategyExtractor::extract($scraper, $dto, $field);
        } catch (DomSelectorException $e) {
            $this->errorLog('Error extracting field from search result item', [
                'field' => $field,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return null;
    }

    protected function scrapeOption(WebScraperInterface $scraper, array $options, bool $multiple = false): Collection
    {
        $type = data_get($options, 'type');
        $value = data_get($options, 'value');

        $value = match ($type) {
            'selector' => ScrapeUrl::parseSelector($value),
            default => [$value]
        };

        $method = ScrapeUrl::getMethodFromType($type);

        try {
            // Return a collection of values.
            return call_user_func_array([$scraper, $method], $value);
        } catch (DomSelectorException $e) {
            $this->errorLog('Error scraping URL', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }

        return collect();
    }

    protected function scrapeUrl(WebScraperInterface $scraper, array $strategy): ?string
    {
        $url = $this->extractValue($scraper, $strategy['product_url'], 'product_url');

        if (! empty($strategy['product_url']['url_decode'])) {
            $url = urldecode((string) $url);
        }

        return $url;
    }

    protected function errorLog(string $message, array $data = []): void
    {
        if (! $this->logErrors) {
            return;
        }

        $this->logger->error($message, $data);
    }
}
