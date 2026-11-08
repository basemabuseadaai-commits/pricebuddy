<?php

namespace App\Filament\Resources\SavedSearchResource\Pages;

use App\Filament\Resources\SavedSearchResource;
use App\Jobs\RunSavedSearchJob;
use App\Models\SavedSearch;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class CreateSavedSearch extends CreateRecord
{
    protected static string $resource = SavedSearchResource::class;

    /**
     * One saved search (and product) per brand, so each brand gets its own best price.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $brands = array_values(array_filter(array_map('trim', Arr::pull($data, 'brands') ?? [])));
        $data['user_id'] = auth()->id();

        $records = collect($brands ?: [null])->map(
            fn ($brand) => SavedSearch::create(array_merge($data, ['brand' => $brand]))
        );

        $records->each(fn (SavedSearch $search) => RunSavedSearchJob::dispatch($search->getKey()));

        return $records->first();
    }

    /**
     * Prefill from "Track this search" on the product search (?query=...).
     */
    protected function afterFill(): void
    {
        $query = trim((string) request()->query('query', ''));

        if ($query === '') {
            return;
        }

        $this->data['queries'] = [$query];
        $this->data['name'] = Str::ucfirst($query);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return __('Saved. Searching the shops now, results appear on the dashboard in a few minutes.');
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
