<?php

namespace App\Filament\Resources\Master\EntityAttributeResource\Traits;

use App\Services\PermissionLabeler;
use Filament\Tables\Columns\TextColumn;

trait Table
{
    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/entityAttribute/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreator(): TextColumn
    {
        return TextColumn::make('creator.name')
            ->label(__('resources/entityAttribute/strings.table.created_by'))
            ->sortable();
    }

    public static function showEntityId(): TextColumn
    {
        return TextColumn::make('entity_id')
            ->label(__('resources/entityAttribute/strings.table.entity_id'))
            ->badge()
            ->color('gray')
            ->sortable();
    }

    public static function showEntityType(): TextColumn
    {
        return TextColumn::make('entity_type')
            ->label(__('resources/entityAttribute/strings.table.entity_type'))
            ->formatStateUsing(fn ($state): string => PermissionLabeler::getEntityLabel($state))
            ->badge()
            ->searchable()
            ->sortable();
    }

    public static function showKey(): TextColumn
    {
        return TextColumn::make('key')
            ->label(__('resources/entityAttribute/strings.table.key'))
            ->searchable()
            ->sortable()
            ->copyable();
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/entityAttribute/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdater(): TextColumn
    {
        return TextColumn::make('updater.name')
            ->label(__('resources/entityAttribute/strings.table.updated_by'))
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showValue(): TextColumn
    {
        return TextColumn::make('value')
            ->label(__('resources/entityAttribute/strings.table.value'))
            ->formatStateUsing(fn ($state): string => is_string($state) ? $state : json_encode($state, JSON_UNESCAPED_UNICODE))
            ->limit(80)
            ->searchable();
    }
}
