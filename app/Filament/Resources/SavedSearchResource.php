<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SavedSearchResource\Pages;
use App\Jobs\RunSavedSearchJob;
use App\Models\ProductSource;
use App\Models\SavedSearch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SavedSearchResource extends Resource
{
    protected static ?string $model = SavedSearch::class;

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass-circle';

    protected static ?string $navigationLabel = 'Saved searches';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('What to look for'))
                ->description(__('Searched again in each shop on a schedule. The cheapest matching results are tracked as one product, so new items and sellers are picked up.'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name'))
                        ->placeholder('Granite cookware')
                        ->helperText(__('Also used as the tag that groups the products on the dashboard.'))
                        ->required(),
                    Forms\Components\TagsInput::make('queries')
                        ->label(__('Search text'))
                        ->placeholder('korkmaz granite')
                        ->helperText(__('What you would type in the shop search bar. Press Enter after each one; every search text is run.'))
                        ->required(),
                    Forms\Components\TagsInput::make('brands')
                        ->label(__('Brands'))
                        ->placeholder('Korkmaz')
                        ->helperText(__('One product per brand, so you see the best price for each. Uses the shop\'s own brand filter where it has one. Leave empty for any brand.'))
                        ->visibleOn('create'),
                    Forms\Components\TextInput::make('brand')
                        ->label(__('Brand'))
                        ->hiddenOn('create'),
                ]),
            Forms\Components\Section::make(__('Filters'))
                ->columns(2)
                ->schema([
                    Forms\Components\TagsInput::make('must_include')
                        ->label(__('Title must include'))
                        ->placeholder('granite|ceramic')
                        ->helperText(__('Every word must be in the product title. Use | for "either", e.g. granite|ceramic.')),
                    Forms\Components\TagsInput::make('exclude')
                        ->label(__('Title must not include'))
                        ->placeholder('lid'),
                    Forms\Components\TextInput::make('min_price')
                        ->label(__('Min price'))
                        ->numeric(),
                    Forms\Components\TextInput::make('max_price')
                        ->label(__('Max price'))
                        ->numeric(),
                ]),
            Forms\Components\Section::make(__('Shops and schedule'))
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('product_source_ids')
                        ->label(__('Shops'))
                        ->multiple()
                        ->options(fn () => ProductSource::query()->currentUser()->enabled()->orderBy('weight')->pluck('name', 'id'))
                        ->helperText(__('Leave empty to search every shop.')),
                    Forms\Components\TextInput::make('per_shop')
                        ->label(__('Track the cheapest per shop'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(20)
                        ->default(3)
                        ->required(),
                    Forms\Components\Select::make('interval_minutes')
                        ->label(__('Search again every'))
                        ->options([
                            60 => __('1 hour'),
                            120 => __('2 hours'),
                            240 => __('4 hours'),
                            360 => __('6 hours'),
                            720 => __('12 hours'),
                            1440 => __('Day'),
                        ])
                        ->default(120)
                        ->required(),
                    Forms\Components\Toggle::make('enabled')
                        ->label(__('Enabled'))
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->weight(FontWeight::Bold)
                    ->description(fn (SavedSearch $record) => implode(', ', $record->queries ?? []))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand')
                    ->placeholder(__('Any brand')),
                Tables\Columns\TextColumn::make('product.title')
                    ->label(__('Product'))
                    ->url(fn (SavedSearch $record) => $record->product ? ProductResource::getUrl('view', ['record' => $record->product]) : null),
                Tables\Columns\TextColumn::make('last_result')
                    ->label(__('Last run'))
                    ->formatStateUsing(fn (SavedSearch $record) => self::summary($record))
                    ->wrap(),
                Tables\Columns\TextColumn::make('last_run_at')
                    ->label(__('Ran'))
                    ->since()
                    ->sortable(),
                Tables\Columns\IconColumn::make('enabled')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->actions([
                Tables\Actions\Action::make('run')
                    ->label(__('Run now'))
                    ->icon('heroicon-o-play')
                    ->action(function (SavedSearch $record) {
                        RunSavedSearchJob::dispatch($record->getKey());
                        Notification::make()
                            ->title(__('Searching the shops. This can take a few minutes.'))
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->currentUser()->with('product'));
    }

    public static function summary(SavedSearch $record): string
    {
        $result = $record->last_result;

        if (! $result) {
            return __('Not run yet');
        }

        $parts = collect($result['sources'] ?? [])
            ->map(fn ($s, $name) => isset($s['error'])
                ? $name.': '.__('error')
                : $name.': '.($s['matched'] ?? 0).' '.__('matched'))
            ->values()
            ->all();

        $parts[] = __(':added added, :removed removed', ['added' => $result['added'] ?? 0, 'removed' => $result['removed'] ?? 0]);

        return implode(' · ', $parts);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSavedSearches::route('/'),
            'create' => Pages\CreateSavedSearch::route('/create'),
            'edit' => Pages\EditSavedSearch::route('/{record}/edit'),
        ];
    }
}
