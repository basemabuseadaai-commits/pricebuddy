<?php

namespace App\Filament\Resources\SavedSearchResource\Pages;

use App\Filament\Resources\SavedSearchResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSavedSearches extends ListRecords
{
    protected static string $resource = SavedSearchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label(__('New saved search')),
        ];
    }
}
