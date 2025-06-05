<?php

namespace App\Filament\Resources\Operational\TargetResource\Traits;


use App\Models\Target;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Category;
use App\Models\Product;
use App\Filament\Resources\Operational\TargetResource\Enums\Status as TargetStatus;

trait Filters
{
    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }

    public static function getYearFilter(): SelectFilter
    {
        return SelectFilter::make('year')
            ->label(__('resources/target/strings.filters.year'))
            ->options(fn() => Target::pluck('year', 'year')->unique()->sort()->toArray())
            ->placeholder(__('resources/target/strings.filters.year_placeholder'));
    }

    public static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label(__('resources/target/strings.filters.status'))
            ->options(TargetStatus::class)
            ->placeholder(__('resources/target/strings.filters.status_placeholder'));
    }

    public static function getQuantityFilter(): Filter
    {
        return Filter::make('quantity')
            ->form([
                Fieldset::make()
                    ->label(__('resources/target/strings.filters.quantity'))
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('quantity_from')
                            ->label(__('resources/target/strings.filters.quantity_from'))
                            ->numeric(),
                        TextInput::make('quantity_to')
                            ->label(__('resources/target/strings.filters.quantity_to'))
                            ->numeric(),
                    ]),
            ])
            ->columnSpanFull()
            ->query(fn(Builder $query, array $data) => $query
                ->when($data['quantity_from'] ?? null, fn(Builder $query, $from): Builder => $query->where('quantity', '>=', $from))
                ->when($data['quantity_to'] ?? null, fn(Builder $query, $to): Builder => $query->where('quantity', '<=', $to))
            );
    }

    public static function getMetricsFilter(): SelectFilter
    {
        return SelectFilter::make('metrics')
            ->label(__('resources/target/strings.filters.metrics'))
            ->options(__('resources/target/strings.metrics'))
            ->placeholder(__('resources/target/strings.filters.metrics_placeholder'));
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/target/strings.table.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/target/strings.table.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }
}
