<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Actions\CreateAction;
use App\Filament\Resources\SavedSearchResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected static ?string $title = 'Your products';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('track_search')
                ->label(__('Track a search'))
                ->tooltip(__('Track what you would type in the shop search bar, without picking links'))
                ->icon('heroicon-o-magnifying-glass-circle')
                ->color('gray')
                ->url(SavedSearchResource::getUrl('create')),
            CreateAction::make(),
        ];
    }
}
