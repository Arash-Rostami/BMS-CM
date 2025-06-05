<?php

namespace App\Filament\Resources\Master\ProductResource\Traits;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use App\Filament\Resources\Master\ProductResource\Enums\InStockStatus;

trait Table
{
    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/product/strings.table.name'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->searchable();
    }

    public static function showEnglishName(): TextColumn
    {
        return TextColumn::make('english_name')
            ->label(__('resources/product/strings.table.english_name'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true)
            ->searchable();
    }

    public static function showCode(): TextColumn
    {
        return TextColumn::make('code')
            ->label(__('resources/product/strings.table.code'))
            ->sortable()
            ->searchable()
            ->toggleable();
    }

    public static function showCategory(): TextColumn
    {
        return TextColumn::make('category.name')
            ->label(__('resources/product/strings.table.category'))
            ->formatStateUsing(fn($state, $record) => $record->category
                ? $record->category->sortAncestors()
                : __('resources/product/strings.table.no_category') ?? '-')
            ->tooltip(fn($record): ?string => $record->determineRollOrSheetType())
            ->size(TextColumn\TextColumnSize::Small)
            ->toggleable()
            ->sortable();
    }


    public static function showProductAttributes(): TextColumn
    {
        return TextColumn::make('attributes')
            ->label(__('resources/product/strings.table.attributes'))
            ->badge()
            ->toggleable()
            ->searchable()
            ->color('info');
    }

    public static function showInStock(): IconColumn
    {
        return IconColumn::make('in_stock')
            ->boolean()
            ->label(__('resources/product/strings.table.in_stock'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->icon(fn(bool $state): string => InStockStatus::tryFrom((int)$state)?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->color(fn(bool $state): string => InStockStatus::tryFrom((int)$state)?->getColor() ?? 'gray');
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/product/strings.table.creator'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable()
            ->searchable();
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/product/strings.table.updater'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->sortable()
            ->searchable();
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/product/strings.table.created_at'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->dateTime()
            ->sortable();
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/product/strings.table.updated_at'))
            ->toggleable(isToggledHiddenByDefault: true)
            ->dateTime()
            ->sortable();
    }
}
