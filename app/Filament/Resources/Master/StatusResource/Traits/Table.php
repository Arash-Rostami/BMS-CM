<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use Filament\Tables\Columns\TextColumn;

trait Table
{
    public static function showType(): TextColumn
    {
        return TextColumn::make('type')
            ->label(__('resources/status/strings.table.type'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa');
    }

    public static function showEnglishType(): TextColumn
    {
        return TextColumn::make('english_type')
            ->label(__('resources/status/strings.table.english_type'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa');
    }

    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/status/strings.table.name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() != 'fa');
    }

    public static function showEnglishName(): TextColumn
    {
        return TextColumn::make('english_name')
            ->label(__('resources/status/strings.table.english_name'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: app()->getLocale() == 'fa');
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/status/strings.table.creator'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/status/strings.table.updater'))
            ->sortable()
            ->searchable()
            ->toggleable(isToggledHiddenByDefault: false);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/status/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/status/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
