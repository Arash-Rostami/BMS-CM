<?php

namespace App\Filament\Resources\Master\RoleResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use Filament\Tables\Columns\TextColumn;

trait Table
{
    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/role/strings.table.name'))
            ->formatStateUsing(fn (string $state): string => UserRole::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn (string $state): string => UserRole::tryFrom($state)?->getColor() ?? 'gray')
            ->badge()
            ->sortable();
    }

    public static function showPermissionsCount(): TextColumn
    {
        return TextColumn::make('permissions_count')
            ->label(__('resources/role/strings.table.permissions_count'))
            ->sortable();
    }

    public static function showUsersCount(): TextColumn
    {
        return TextColumn::make('users_count')
            ->label(__('resources/role/strings.table.users_count'))
            ->sortable();
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/role/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/role/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
