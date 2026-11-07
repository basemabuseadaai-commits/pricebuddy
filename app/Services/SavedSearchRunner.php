<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductSource;
use App\Models\SavedSearch;
use App\Models\Tag;
use App\Models\Url;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs a saved search: searches each shop, keeps the cheapest results that pass
 * the filters, adds them as URLs to the search's product, and removes URLs that
 * stopped showing up.
 */
class SavedSearchRunner
{
    /** URLs not picked by any run for this long are removed from the product. */
    public const int FORGET_AFTER_DAYS = 3;

    /**
     * @return array<string, mixed> summary, also stored as last_result
     */
    public function run(SavedSearch $search): array
    {
        $product = $this->ensureProduct($search);
        $picks = collect();
        $rejected = [];
        $perSource = [];

        foreach ($this->sources($search) as $source) {
            /** @var ProductSource $source */
            try {
                $results = $this->searchSource($search, $source);
                $kept = self::filterResults(
                    $results,
                    $this->requiredWords($search, $source),
                    $search->exclude ?? [],
                    $search->min_price,
                    $search->max_price,
                );
                $cheapest = self::cheapest($kept, $search->per_shop);

                // Results that fail the filters now, e.g. after the filters were changed.
                $keptKeys = $kept->map(fn ($r) => self::matchKey((string) $r['url']))->flip();
                foreach ($results as $result) {
                    $key = self::matchKey((string) ($result['url'] ?? ''));
                    if (! $keptKeys->has($key)) {
                        $rejected[$key] = true;
                    }
                }
                $picks = $picks->merge($cheapest);
                $perSource[$source->name] = [
                    'found' => $results->count(),
                    'matched' => $kept->count(),
                    'lowest' => $cheapest->first()['price'] ?? null,
                ];
            } catch (Throwable $e) {
                logger()->warning('Saved search failed for a source', [
                    'saved_search' => $search->getKey(),
                    'source' => $source->name,
                    'error' => $e->getMessage(),
                ]);
                $perSource[$source->name] = ['error' => Str::limit($e->getMessage(), 200)];
            }
        }

        [$added, $failed] = $this->addUrls($search, $product, $picks);
        $removed = $this->forgetStaleUrls($search, $product, $picks, $rejected);

        $summary = [
            'sources' => $perSource,
            'picked' => $picks->count(),
            'added' => $added,
            'failed' => $failed,
            'removed' => $removed,
            'ran_at' => now()->toIso8601String(),
        ];

        $search->forceFill([
            'last_run_at' => now(),
            'next_run_at' => now()->addMinutes(max(15, $search->interval_minutes)),
            'last_result' => $summary,
        ])->save();

        return $summary;
    }

    /**
     * Keep results whose title has every required word and none of the excluded
     * words, and whose price (when known) is within the limits. A required word
     * may list alternatives separated by a comma, e.g. "granite, ceramic" ("|" still works).
     *
     * @param  Collection<int, array<string, mixed>>  $results
     * @param  array<int, string>  $required
     * @param  array<int, string>  $exclude
     * @return Collection<int, array<string, mixed>>
     */
    public static function filterResults(Collection $results, array $required, array $exclude, ?float $min, ?float $max): Collection
    {
        $required = array_values(array_filter(array_map('trim', $required)));
        // An excluded entry may also hold several words separated by commas.
        $exclude = array_values(array_filter(array_map('trim', Arr::flatten(array_map(fn ($word) => preg_split('/[,|]/', (string) $word), $exclude)))));

        return $results->filter(function ($result) use ($required, $exclude, $min, $max) {
            $title = Str::lower((string) ($result['title'] ?? ''));
            $price = $result['price'] ?? null;

            foreach ($required as $word) {
                $options = array_filter(array_map('trim', preg_split('/[,|]/', $word)));
                if (! collect($options)->contains(fn ($option) => self::hasWord($title, $option))) {
                    return false;
                }
            }

            foreach ($exclude as $word) {
                if (self::hasWord($title, $word)) {
                    return false;
                }
            }

            if ($price !== null && $min !== null && $price < $min) {
                return false;
            }

            return ! ($price !== null && $max !== null && $price > $max);
        })->values();
    }

    /**
     * Whole words only, plurals allowed: "bra" doesn't match "braided", "top" doesn't
     * match "stop", and "sneaker" matches "sneakers".
     */
    public static function hasWord(string $title, string $word): bool
    {
        $word = trim(Str::lower($word));

        return $word !== ''
            && preg_match('/(?<![\\p{L}\\p{N}])'.preg_quote($word, '/').'(?:s|es)?(?![\\p{L}\\p{N}])/u', Str::lower($title)) === 1;
    }

    /**
     * The cheapest results first; results without a price come last.
     *
     * @param  Collection<int, array<string, mixed>>  $results
     * @return Collection<int, array<string, mixed>>
     */
    public static function cheapest(Collection $results, int $count): Collection
    {
        return $results
            ->sortBy(fn ($r) => $r['price'] ?? PHP_FLOAT_MAX)
            // Same product listed twice (e.g. two noon seller offers): keep the cheaper one.
            ->unique(fn ($r) => self::matchKey((string) $r['url']))
            ->take(max(1, $count))
            ->values();
    }

    /**
     * One stable link per product. Amazon search links carry a /ref=... part and a
     * session token that change on every page load, so keep only up to /dp/<ASIN>.
     */
    public static function canonicalUrl(string $url): string
    {
        if (preg_match('~^(https?://[^/]*amazon\.[^/]+/(?:.*?/)?dp/[A-Z0-9]{10})~i', $url, $m)) {
            return $m[1];
        }

        // Drop tracking parameters (DeFacto adds ?waw_keyword=<search text>).
        $parts = parse_url($url);
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $kept = array_filter($query, fn ($key) => ! preg_match('/^(waw_keyword|utm_\w+)$/i', (string) $key), ARRAY_FILTER_USE_KEY);

            if (count($kept) !== count($query)) {
                $url = Str::before($url, '?').($kept ? '?'.http_build_query($kept) : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
            }
        }

        return $url;
    }

    /**
     * Key used to tell whether two links are the same product.
     */
    public static function matchKey(string $url): string
    {
        $url = self::canonicalUrl($url);

        // noon links name the seller offer in ?o=...; the product is the same.
        if (preg_match('~^https?://(?:www\.)?noon\.com/~i', $url)) {
            $url = preg_replace('~\?.*$~', '', $url);
        }

        return Url::normalizeForMatch($url);
    }

    /**
     * @return Collection<int, ProductSource>
     */
    protected function sources(SavedSearch $search): Collection
    {
        return ProductSource::query()
            ->enabled()
            ->where('user_id', $search->user_id)
            ->when(! empty($search->product_source_ids), fn ($q) => $q->whereIn('id', $search->product_source_ids))
            ->orderBy('weight')
            ->get();
    }

    /**
     * Run every query of the search on one source. When the source can't filter
     * by brand, the brand is added to the query text instead.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function searchSource(SavedSearch $search, ProductSource $source): Collection
    {
        $service = $source->getSearchService()->inCategory($search->category);
        $brand = $search->brand;

        // A brand's own shop (settings.sole_brand, e.g. adidas): its titles never name the
        // brand, so search without it, and skip the shop for any other brand.
        $soleBrand = data_get($source->settings, 'sole_brand');
        if (filled($soleBrand) && filled($brand)) {
            if (! self::sameBrand($soleBrand, $brand)) {
                return collect();
            }
            $brand = null;
        }

        return collect($search->queries)
            ->filter()
            ->flatMap(function ($query) use ($brand, $service) {
                // The shop's own brand filter, when it has one and knows the brand.
                if ($brand && $service->supportsBrandFilter() && $service->resolveBrand($query, $brand) !== null) {
                    return $service->search($query, $brand)->all();
                }

                $results = $service->search($brand ? $brand.' '.$query : $query);

                // Otherwise the brand goes in the search text and must be in the title.
                return $brand
                    ? self::filterResults($results, [$brand], [], null, null)->all()
                    : $results->all();
            })
            ->map(fn ($r) => array_merge(['price' => null], Arr::except($r, ['content']), [
                'url' => self::canonicalUrl((string) $r['url']),
            ]))
            ->values();
    }

    /**
     * "Adidas" matches "adidas"; "LC Waikiki" matches "lcwaikiki" (case, spaces and dashes ignored).
     */
    public static function sameBrand(string $a, string $b): bool
    {
        $norm = fn (string $s) => preg_replace('/[^\p{L}\p{N}]+/u', '', Str::lower($s));

        return $norm($a) !== '' && $norm($a) === $norm($b);
    }

    /**
     * @return array<int, string>
     */
    protected function requiredWords(SavedSearch $search, ProductSource $source): array
    {
        // Shops searched inside the chosen section (e.g. noon's men's sneakers) already
        // return only that kind of item, and their titles often lack words like "shoe".
        if ($search->category && $source->getSearchService()->supportsCategory($search->category)) {
            return [];
        }

        return $search->must_include ?? [];
    }

    public function ensureProduct(SavedSearch $search): Product
    {
        $product = $search->product;

        if (! $product) {
            $product = Product::create([
                'title' => $search->productTitle(),
                'user_id' => $search->user_id,
                'favourite' => true,
                'refresh_interval' => max(15, $search->interval_minutes) * 60,
            ]);

            $tag = Tag::firstOrCreate(['name' => $search->name, 'user_id' => $search->user_id]);
            $product->tags()->syncWithoutDetaching([$tag->getKey()]);

            $search->forceFill(['product_id' => $product->getKey()])->save();
            $search->setRelation('product', $product);
        }

        return $product;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $picks
     * @return array{0: int, 1: int} added, failed
     */
    protected function addUrls(SavedSearch $search, Product $product, Collection $picks): array
    {
        $known = $product->urls()->pluck('url')
            ->merge($product->ignored_urls ?? [])
            ->map(fn ($url) => self::matchKey((string) $url))
            ->flip();

        $added = 0;
        $failed = 0;

        foreach ($picks as $pick) {
            $normalized = self::matchKey((string) $pick['url']);

            if ($normalized === '' || $known->has($normalized)) {
                continue;
            }

            try {
                $url = Url::createFromUrl(url: $pick['url'], productId: $product->getKey(), userId: $search->user_id);
            } catch (Throwable $e) {
                $url = false;
                logger()->warning('Saved search could not add URL', ['url' => $pick['url'], 'error' => $e->getMessage()]);
            }

            $known->put($normalized, true);
            $url ? $added++ : $failed++;
        }

        return [$added, $failed];
    }

    /**
     * Remove URLs this search added earlier that no run has picked for a few days,
     * so the product follows what the shops show now.
     *
     * @param  Collection<int, array<string, mixed>>  $picks
     */
    protected function forgetStaleUrls(SavedSearch $search, Product $product, Collection $picks, array $rejected = []): int
    {
        $seen = $search->seen_urls ?? [];
        $now = now();
        $picked = [];

        foreach ($picks as $pick) {
            $key = self::matchKey((string) $pick['url']);
            $seen[$key] = $now->toIso8601String();
            $picked[$key] = true;
        }

        $cutoff = $now->copy()->subDays(self::FORGET_AFTER_DAYS);
        $removed = 0;

        foreach ($product->urls()->get() as $url) {
            $normalized = self::matchKey($url->url);
            $lastSeen = $seen[$normalized] ?? null;

            // Only URLs this search added; links the user added by hand stay.
            // Removed when not picked for a while, or straight away when the
            // shop still lists them but they no longer pass the filters.
            $failsFilters = isset($rejected[$normalized]) && ! isset($picked[$normalized]);
            if ($lastSeen && ($failsFilters || $now->parse($lastSeen)->lt($cutoff))) {
                $url->delete();
                unset($seen[$normalized]);
                $removed++;
            }
        }

        $search->forceFill(['seen_urls' => $seen]);

        return $removed;
    }
}
