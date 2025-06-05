<?php

namespace App\Filament\Resources\Master\CategoryResource\Traits;

use App\Filament\Resources\Master\CategoryResource\Enums\Level;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Support\Facades\Cache;

trait Filters
{
    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }

    public static function getLevelFilter(): SelectFilter
    {
        return SelectFilter::make('level')
            ->label(__('resources/category/strings.filters.level'))
            ->options(fn() => Cache::remember('levels_filter', now()->addMinutes(5),
                fn() => Category::pluck('level')
                    ->unique()
                    ->sort()
                    ->mapWithKeys(fn(int $lvl) => [
                        $lvl => $lvl === 0
                            ? Level::BASE->getLabel() : Level::fromLevel($lvl)->getLabel() ?? (string)$lvl
                    ])->all())
            )
            ->placeholder(__('resources/category/strings.filters.level_placeholder'));
    }

    public static function getAncestorsFilter(): Filter
    {
        return static::makeRelationFilter(
            'ancestors_filter',
            'resources/category/strings.filters.ancestors',
            'descendants'
        );
    }

    public static function getDescendantsFilter(): Filter
    {
        return static::makeRelationFilter(
            'descendants_filter',
            'resources/category/strings.filters.descendants',
            'ancestors'
        );
    }


    public static function getActiveFilter(): TernaryFilter
    {
        return TernaryFilter::make('active')
            ->label(__('resources/category/strings.table.active'))
            ->trueLabel(__('resources/category/strings.table.active'))
            ->falseLabel(__('resources/category/strings.table.inactive'));
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/category/strings.table.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/category/strings.table.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }

    protected static function makeRelationFilter(string $filterName, string $labelKey, string $relation): Filter
    {

        return Filter::make($filterName)
            ->form([
                Select::make('category_id')
                    ->label(__($labelKey))
                    ->options(fn() => Cache::remember("filter_{$filterName}_options", now()->addMinutes(5),
                        fn() => Category::pluck('name', 'id')->all())
                    )->searchable(),
            ])
            ->query(fn($query, array $data) => $query
                ->when(
                    $data['category_id'] ?? null,
                    fn($q, $id) => $q->whereHas($relation, fn($q) => $q->where('id', $id))
                )
            );
    }
}
