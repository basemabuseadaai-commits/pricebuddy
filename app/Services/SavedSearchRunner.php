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
        $removed = $this->forgetStaleUrls($search, $product, $picks);

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
     * may list alternatives with "|", e.g. "granite|ceramic".
     *
     * @param  Collection<int, array<string, mixed>>  $results
     * @param  array<int, string>  $required
     * @param  array<int, string>  $exclude
     * @return Collection<int, array<string, mixed>>
     */
    public static function filterResults(Collection $results, array $required, array $exclude, ?float $min, ?float $max): Collection
    {
        $required = array_values(array_filter(array_map('trim', $required)));
        $exclude = array_values(array_filter(array_map('trim', $exclude)));

        return $results->filter(function ($result) use ($required, $exclude, $min, $max) {
            $title = Str::lower((string) ($result['title'] ?? ''));
            $price = $result['price'] ?? null;

            foreach ($required as $word) {
                $options = array_filter(array_map('trim', explode('|', Str::lower($word))));
                if (! collect($options)->contains(fn ($option) => str_contains($title, $option))) {
                    return false;
                }
            }

            foreach ($exclude as $word) {
                if (str_contains($title, Str::lower($word))) {
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
     * The cheapest results first; results without a price come last.
     *
     * @param  Collection<int, array<string, mixed>>  $results
     * @return Collection<int, array<string, mixed>>
     */
    public static function cheapest(Collection $results, int $count): Collection
    {
        return $results
            ->unique('url')
            ->sortBy(fn ($r) => $r['price'] ?? PHP_FLOAT_MAX)
            ->take(max(1, $count))
            ->values();
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
        $service = $source->getSearchService();
        $brand = $search->brand;

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
            ->map(fn ($r) => array_merge(['price' => null], Arr::except($r, ['content'])))
            ->values();
    }

    /**
     * @return array<int, string>
     */
    protected function requiredWords(SavedSearch $search, ProductSource $source): array
    {
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
            ->map(fn ($url) => Url::normalizeForMatch((string) $url))
            ->flip();

        $added = 0;
        $failed = 0;

        foreach ($picks as $pick) {
            $normalized = Url::normalizeForMatch((string) $pick['url']);

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
    protected function forgetStaleUrls(SavedSearch $search, Product $product, Collection $picks): int
    {
        $seen = $search->seen_urls ?? [];
        $now = now();

        foreach ($picks as $pick) {
            $seen[Url::normalizeForMatch((string) $pick['url'])] = $now->toIso8601String();
        }

        $cutoff = $now->copy()->subDays(self::FORGET_AFTER_DAYS);
        $removed = 0;

        foreach ($product->urls()->get() as $url) {
            $normalized = Url::normalizeForMatch($url->url);
            $lastSeen = $seen[$normalized] ?? null;

            // Only URLs this search added; links the user added by hand stay.
            if ($lastSeen && $now->parse($lastSeen)->lt($cutoff)) {
                $url->delete();
                unset($seen[$normalized]);
                $removed++;
            }
        }

        $search->forceFill(['seen_urls' => $seen]);

        return $removed;
    }
}
