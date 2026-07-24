<?php

namespace App\Filament\Resources\Master\PermissionResource\Traits;

use App\Services\PermissionLabeler;
use Filament\Tables\Columns\TextColumn;

trait Table
{
    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/permission/strings.table.name'))
            ->formatStateUsing(fn (string $state): string => PermissionLabeler::getLabel($state))
            ->searchable(isIndividual: true, isGlobal: false)
            ->sortable();
    }

    public static function showRolesCount(): TextColumn
    {
        return TextColumn::make('roles_count')
            ->label(__('resources/permission/strings.table.roles_count'))
            ->sortable();
    }

    public static function showUsersCount(): TextColumn
    {
        return TextColumn::make('users_count')
            ->label(__('resources/permission/strings.table.users_count'))
            ->sortable();
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/permission/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/permission/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
