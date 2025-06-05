<?php

namespace App\Filament\Resources\Master\ProductResource\Traits;

use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Category;


trait Filters
{
    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }

    public static function getCategoryFilter(): SelectFilter
    {
        return SelectFilter::make('category_id')
            ->label(__('resources/product/strings.filters.category'))
            ->relationship('category', 'name')
            ->searchable()
            ->preload()
            ->placeholder(__('resources/product/strings.filters.category_placeholder'))
            ->query(function (Builder $query, array $data): Builder {
                if (empty($data['value'])) {
                    return $query;
                }

                $category = Category::with(['ancestors:id', 'descendants:id'])->find($data['value']);

                if (!$category) {
                    return $query;
                }

                $allIds = collect([$category->id])
                    ->concat($category->ancestors->pluck('id'))
                    ->concat($category->descendants->pluck('id'))
                    ->unique()
                    ->values();

                return $query->whereIn('category_id', $allIds);
            });

    }

    public static function getInStockFilter(): TernaryFilter
    {
        return TernaryFilter::make('in_stock')
            ->label(__('resources/product/strings.table.in_stock'))
            ->trueLabel(__('resources/product/strings.table.in_stock_true'))
            ->falseLabel(__('resources/product/strings.table.in_stock_false'));
    }

    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/product/strings.table.creator'))
            ->relationship('creator', 'name')
            ->searchable()
            ->preload();
    }

    public static function getUpdaterFilter(): SelectFilter
    {
        return SelectFilter::make('updated_by_id')
            ->label(__('resources/product/strings.table.updater'))
            ->relationship('updater', 'name')
            ->searchable()
            ->preload();
    }
}
