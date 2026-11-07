<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A search that is run again on a schedule. The cheapest matching results from
 * each shop are added as URLs to one product, so new items and new sellers are
 * picked up without choosing links by hand.
 *
 * @property int $id
 * @property int $user_id
 * @property ?int $product_id
 * @property string $name
 * @property array<int, string> $queries
 * @property ?string $brand
 * @property ?array<int, string> $must_include
 * @property ?array<int, string> $exclude
 * @property ?float $min_price
 * @property ?float $max_price
 * @property int $per_shop
 * @property ?array<int, int> $product_source_ids
 * @property int $interval_minutes
 * @property bool $enabled
 * @property ?Carbon $last_run_at
 * @property ?Carbon $next_run_at
 * @property ?array<string, mixed> $last_result
 * @property ?array<string, string> $seen_urls
 * @property-read ?Product $product
 * @property-read User $user
 */
class SavedSearch extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'queries' => 'array',
            'must_include' => 'array',
            'exclude' => 'array',
            'product_source_ids' => 'array',
            'last_result' => 'array',
            'seen_urls' => 'array',
            'min_price' => 'float',
            'max_price' => 'float',
            'per_shop' => 'integer',
            'interval_minutes' => 'integer',
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeCurrentUser(Builder $query): Builder
    {
        return $query->where('user_id', auth()->id());
    }

    public function scopeDue(Builder $query): Builder
    {
        return $query->where('enabled', true)
            ->where(fn (Builder $q) => $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', now()));
    }

    public function productTitle(): string
    {
        return $this->brand ? $this->name.' - '.$this->brand : $this->name;
    }
}
