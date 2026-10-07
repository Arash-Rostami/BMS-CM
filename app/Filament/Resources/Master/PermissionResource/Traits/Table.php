<?php

namespace App\Filament\Resources\Master\PermissionResource\Traits;

use App\Jobs\ExportPermissions;
use App\Models\Permission;
use App\Services\PermissionLabeler;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait Table
{
    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportPermissions')
            ->label(__('resources/permission/strings.export.export_permissions'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => static::canViewAny())
            ->action(function (Collection $records): void {
                ExportPermissions::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

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
            ->label(__('resources/permission/strings.table.name'))
            ->formatStateUsing(fn (string $state): string => PermissionLabeler::getLabel($state))
            ->searchable(query: function (Builder $query, string $search): Builder {
                $labelMatches = Permission::pluck('name')
                    ->filter(fn (string $name): bool => str_contains(
                        Str::lower(PermissionLabeler::getLabel($name)),
                        Str::lower($search)
                    ))
                    ->all();

                return $query->where(function (Builder $query) use ($search, $labelMatches) {
                    $query->where('name', 'like', "%{$search}%");

                    if ($labelMatches !== []) {
                        $query->orWhereIn('name', $labelMatches);
                    }
                });
            })
            ->sortable();
    }

    public static function showRolesCount(): TextColumn
    {
        return TextColumn::make('roles_count')
            ->label(__('resources/permission/strings.table.roles_count'))
            ->icon('heroicon-o-user-group')
            ->alignEnd()
            ->badge()
            ->color(fn (?int $state): string => $state === 0 ? 'gray' : 'info')
            ->sortable();
    }

    public static function showUsersCount(): TextColumn
    {
        return TextColumn::make('users_count')
            ->label(__('resources/permission/strings.table.users_count'))
            ->icon('heroicon-o-users')
            ->alignEnd()
            ->badge()
            ->color(fn (?int $state): string => $state === 0 ? 'gray' : 'success')
            ->sortable();
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/permission/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/permission/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
