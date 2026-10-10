<?php

namespace App\Filament\Resources\Master\UserResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\PositionStatus;
use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Filament\Resources\Master\UserResource\Enums\UserStatus;
use App\Jobs\ExportUsers;
use App\Models\User;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Collection;

trait Table
{
    public static function getExportBulkAction(): BulkAction
    {
        return BulkAction::make('exportUsers')
            ->label(__('resources/user/strings.export.export_users'))
            ->icon('heroicon-o-arrow-down-tray')
            ->authorize(fn (): bool => static::canViewAny())
            ->action(function (Collection $records): void {
                ExportUsers::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale());

                Notification::make()
                    ->title(__('resources/general/strings.export.started'))
                    ->info()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function getActivateBulkAction(): BulkAction
    {
        return BulkAction::make('activate')
            ->authorize(fn (): bool => static::canEditAny())
            ->label(__('resources/general/strings.bulk.activate.label'))
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->action(function (Collection $records): void {
                User::whereIn('id', $records->pluck('id'))->update(['status' => UserStatus::ACTIVE->value]);
                Notification::make()
                    ->title(__('resources/general/strings.bulk.activate.notification'))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function getDeactivateBulkAction(): BulkAction
    {
        return BulkAction::make('deactivate')
            ->authorize(fn (): bool => static::canEditAny())
            ->label(__('resources/general/strings.bulk.deactivate.label'))
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                $selected = $records->pluck('id');
                $targeted = User::whereIn('id', $selected)
                    ->where('id', '!=', auth()->id())
                    ->whereDoesntHave('roles', fn ($query) => $query->where('name', UserRole::ADMIN_JUNIOR->value))
                    ->pluck('id');

                User::whereIn('id', $targeted)->update(['status' => UserStatus::INACTIVE->value]);

                $skipped = $selected->diff($targeted)->count();

                Notification::make()
                    ->title(__('resources/general/strings.bulk.deactivate.notification'))
                    ->success()
                    ->send();

                if ($skipped > 0) {
                    Notification::make()
                        ->title(__('resources/user/strings.bulk.deactivate_skipped', ['count' => $skipped]))
                        ->warning()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function showName(): TextColumn
    {
        return TextColumn::make('name')
            ->label(__('resources/user/strings.table.name'))
            ->sortable()
            ->searchable();
    }

    public static function showPhone(): TextColumn
    {
        return TextColumn::make('phone')
            ->label(__('resources/user/strings.table.phone'))
            ->icon('heroicon-o-device-phone-mobile')
            ->searchable();
    }

    public static function showEmail(): TextColumn
    {
        return TextColumn::make('email')
            ->label(__('resources/user/strings.table.email'))
            ->icon('heroicon-m-envelope')
            ->searchable();
    }

    public static function showCompany(): TextColumn
    {
        return TextColumn::make('company')
            ->label(__('resources/user/strings.table.company'))
            ->icon('heroicon-o-building-office-2')
            ->searchable()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);

    }

    public static function showDepartment(): TextColumn
    {
        return TextColumn::make('department.name')
            ->label(__('resources/user/strings.table.department'))
            ->getStateUsing(function ($record): string {
                $department = $record->department;

                return $department?->code ?? '-';
            })
            ->tooltip(fn ($record) => (app()->getLocale() !== 'fa') ? optional($record->department)->english_name : '')
            ->sortable();
    }

    public static function showPosition(): TextColumn
    {
        return TextColumn::make('position')
            ->label(__('resources/user/strings.table.position'))
            ->icon(fn (string $state): string => (PositionStatus::tryFrom($state))?->getIcon() ?? 'heroicon-o-briefcase')
            ->color(fn (string $state): string => (PositionStatus::tryFrom($state))?->getColor() ?? 'secondary')
            ->formatStateUsing(fn (string $state): string => (PositionStatus::tryFrom($state))?->getLabel() ?? $state)
            ->toggleable(isToggledHiddenByDefault: true)
            ->searchable();
    }

    public static function showRole(): TextColumn
    {
        return TextColumn::make('roles.name')
            ->label(__('resources/user/strings.table.role'))
            ->badge()
            ->formatStateUsing(fn (string $state): string => UserRole::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn (string $state): string => UserRole::tryFrom($state)?->getColor() ?? 'gray')
            ->searchable();
    }

    public static function showImage(): ImageColumn
    {
        return ImageColumn::make('image')
            ->square()
            ->circular()
            ->disk('public')
            ->visibility('public')
            ->defaultImageUrl(fn ($record) => $record->getFilamentAvatarUrl())
            ->label(__('resources/user/strings.table.image'));
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('resources/user/strings.table.status'))
            ->icon(fn (string $state): string => (UserStatus::tryFrom($state))?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->formatStateUsing(fn (string $state): string => (UserStatus::tryFrom($state))?->getLabel() ?? $state)
            ->color(fn (string $state): string => (UserStatus::tryFrom($state))?->getColor() ?? 'secondary')
            ->searchable();
    }

    public static function showIP(): TextColumn
    {
        return TextColumn::make('user_country')
            ->label(__('resources/user/strings.table.ip'))
            ->icon('heroicon-o-globe-alt')
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showLastLogIn(): TextColumn
    {
        return TextColumn::make('last_log_in')
            ->label(__('resources/user/strings.table.last_log_in'))
            ->adaptiveDateTime()
            ->formatStateUsing(fn ($state) => $state?->diffForHumans() ?? __('resources/user/strings.table.never'))
            ->placeholder(__('resources/user/strings.table.never'))
            ->color(fn ($state) => match (true) {
                $state === null => 'danger',
                $state->lt(now()->subDays(30)) => 'warning',
                default => null,
            })
            ->tooltip(fn ($state) => $state ? adaptiveDate($state, true) : null)
            ->sortable()
            ->toggleable();
    }

    public static function showLastLogout(): TextColumn
    {
        return TextColumn::make('last_log_out')
            ->label(__('resources/user/strings.table.last_log_out'))
            ->adaptiveDateTime()
            ->formatStateUsing(fn ($state) => $state->diffForHumans())
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showDeletionTime(): TextColumn
    {
        return TextColumn::make('deleted_at')
            ->label(__('resources/user/strings.table.deleted_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/user/strings.table.created_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/user/strings.table.updated_at'))
            ->adaptiveDateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
