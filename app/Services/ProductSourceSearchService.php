<?php

namespace App\Services;

use App\Dto\StandardStrategyDto;
use App\Models\ProductSource;
use App\Services\Helpers\CurrencyHelper;
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

    public function makeScraper(string $query, ?string $brand = null): WebScraperInterface
    {
        return WebScraper::make($this->source->scraper_service)
            ->from($this->buildSearchUrl($query, $brand));
    }

    /**
     * Whether this source can filter its search by brand (settings.brand_param).
     */
    public function supportsBrandFilter(): bool
    {
        return filled(data_get($this->source->settings, 'brand_param'));
    }

    public function search(string $query, ?string $brand = null): Collection
    {
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

    public function buildSearchUrl(string $query, ?string $brand = null): string
    {
        $url = str_replace(':search_term', urlencode($query), $this->source->search_url);

        // settings.brand_param is appended with {brand} replaced, e.g. "&rh=p_89:{brand}".
        $brandParam = data_get($this->source->settings, 'brand_param');
        if (filled($brand) && filled($brandParam)) {
            $url .= str_replace('{brand}', rawurlencode($brand), $brandParam);
        }

        return $url;
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
