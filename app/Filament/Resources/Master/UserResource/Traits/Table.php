<?php

namespace App\Filament\Resources\Master\UserResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\PositionStatus;
use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Filament\Resources\Master\UserResource\Enums\UserStatus;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Vite;

trait Table
{
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
            ->tooltip(fn($record) => (app()->getLocale() !== 'fa') ? optional($record->department)->english_name : '')
            ->sortable();
    }

    public static function showPosition(): TextColumn
    {
        return TextColumn::make('position')
            ->label(__('resources/user/strings.table.position'))
            ->icon(fn(string $state): string => (PositionStatus::tryFrom($state))?->getIcon() ?? 'heroicon-o-briefcase')
            ->color(fn(string $state): string => (PositionStatus::tryFrom($state))?->getColor() ?? 'secondary')
            ->formatStateUsing(fn(string $state): string => (PositionStatus::tryFrom($state))?->getLabel() ?? $state)
            ->toggleable(isToggledHiddenByDefault: true)
            ->searchable();
    }

    public static function showRole(): TextColumn
    {
        return TextColumn::make('role')
            ->label(__('resources/user/strings.table.role'))
            ->icon(fn(string $state): string => (UserRole::tryFrom($state))?->getIcon() ?? 'heroicon-o-circle')
            ->color(fn(string $state): string => (UserRole::tryFrom($state))?->getColor() ?? 'secondary')
            ->formatStateUsing(fn(string $state): string => (UserRole::tryFrom($state))?->getLabel() ?? $state)
            ->searchable();
    }

    public static function showImage(): ImageColumn
    {
        return ImageColumn::make('image')
            ->square()
            ->height(30)
            ->disk('public')
            ->visibility('public')
            ->defaultImageUrl(fn($record) => $record->getFilamentAvatarUrl())
            ->label(__('resources/user/strings.table.image'));
    }

    public static function showStatus(): TextColumn
    {
        return TextColumn::make('status')
            ->label(__('resources/user/strings.table.status'))
            ->icon(fn(string $state): string => (UserStatus::tryFrom($state))?->getIcon() ?? 'heroicon-o-question-mark-circle')
            ->formatStateUsing(fn(string $state): string => (UserStatus::tryFrom($state))?->getLabel() ?? $state)
            ->color(fn(string $state): string => (UserStatus::tryFrom($state))?->getColor() ?? 'secondary')
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
            ->dateTime()
            ->formatStateUsing(fn($state) => $state->diffForHumans())
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showLastLogout(): TextColumn
    {
        return TextColumn::make('last_log_out')
            ->label(__('resources/user/strings.table.last_log_out'))
            ->dateTime()
            ->formatStateUsing(fn($state) => $state->diffForHumans())
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }


    public static function showDeletionTime(): TextColumn
    {
        return TextColumn::make('deleted_at')
            ->label(__('resources/user/strings.table.deleted_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showCreationTime(): TextColumn
    {
        return TextColumn::make('created_at')
            ->label(__('resources/user/strings.table.created_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }

    public static function showUpdateTime(): TextColumn
    {
        return TextColumn::make('updated_at')
            ->label(__('resources/user/strings.table.updated_at'))
            ->dateTime()
            ->sortable()
            ->toggleable(isToggledHiddenByDefault: true);
    }
}
