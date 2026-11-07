<?php

namespace App\Filament\Resources\SavedSearchResource\Pages;

use App\Filament\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSavedSearch extends EditRecord
{
    protected static string $resource = SavedSearchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var SavedSearch $search */
        $search = $this->getRecord();

        // Keep the product name and check interval in step with the search.
        $search->product?->update([
            'title' => $search->productTitle(),
            'refresh_interval' => max(15, $search->interval_minutes) * 60,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
