<?php

namespace App\Filament\Resources\Master\RoleResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Jobs\ExportRoles;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Collection;

trait Table
{
    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportRoles')
            ->label(__('resources/role/strings.export.export_roles'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => static::canViewAny())
            ->action(function (Collection $records): void {
                ExportRoles::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

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
            ->icon('heroicon-o-key')
            ->alignEnd()
            ->badge()
            ->color(fn (?int $state): string => $state === 0 ? 'gray' : 'info')
            ->sortable();
    }

    public static function showUsersCount(): TextColumn
    {
        return TextColumn::make('users_count')
            ->label(__('resources/role/strings.table.users_count'))
            ->icon('heroicon-o-users')
            ->alignEnd()
            ->badge()
            ->color(fn (?int $state): string => $state === 0 ? 'gray' : 'success')
            ->sortable();
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/role/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/role/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
