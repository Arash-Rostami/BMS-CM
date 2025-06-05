<?php

namespace App\Filament\Resources\Master\CurrencyResource\Traits;


use Filament\Tables\Columns\TextColumn;

trait Table
{
    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/currency/strings.table.name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa');

    }

    public static function showEnglishName(): TextColumn
    {
        return TextColumn::make('english_name')
            ->label(__('resources/currency/strings.table.english_name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa');
    }

    public static function showDescription(): TextColumn
    {
        return TextColumn::make('description')
            ->label(__('resources/currency/strings.table.description'))
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false)
            ->limit(50);

    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/currency/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/currency/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/currency/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/currency/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
