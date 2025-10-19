<?php

namespace App\Filament\Resources\Master\UserResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\PositionStatus;
use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Filament\Resources\Master\UserResource\Enums\UserStatus;
use Filament\Infolists\Components;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;

trait Infolist
{
    public static function viewImage(): ImageEntry
    {
        return ImageEntry::make('image')
            ->label(__('resources/user/strings.form.image'))
            ->circular()
            ->size(80);
    }

    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/user/strings.form.name'));
    }

    public static function viewEmail(): TextEntry
    {
        return TextEntry::make('email')
            ->label(__('resources/user/strings.form.email'))
            ->copyable();
    }

    public static function viewPhone(): TextEntry
    {
        return TextEntry::make('phone')
            ->label(__('resources/user/strings.form.phone'))
            ->copyable();
    }

    public static function viewCompany(): TextEntry
    {
        return TextEntry::make('company')
            ->label(__('resources/user/strings.form.company'))
            ->placeholder('-');
    }

    public static function viewDepartment(): TextEntry
    {
        return TextEntry::make('department.name')
            ->label(__('resources/user/strings.form.department'))
            ->placeholder('-');
    }

    public static function viewPosition(): TextEntry
    {
        return TextEntry::make('position')
            ->label(__('resources/user/strings.form.position'))
            ->color(fn (string $state): string => PositionStatus::tryFrom($state)?->getColor() ?? 'gray');
    }

    public static function viewRole(): TextEntry
    {
        return TextEntry::make('role')
            ->label(__('resources/user/strings.form.role'))
            ->formatStateUsing(fn (string $state): string => UserRole::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn (string $state): string => UserRole::tryFrom($state)?->getColor() ?? 'gray');
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status')
            ->label(__('resources/user/strings.form.status'))
            ->formatStateUsing(fn (string $state): string => UserStatus::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn (string $state): string => UserStatus::tryFrom($state)?->getColor() ?? 'gray');
    }

    public static function viewIP(): TextEntry
    {
        return TextEntry::make('ip')
            ->label(__('resources/user/strings.form.ip'))
            ->formatStateUsing(fn($state, ?Model $record): string => ($state && $record) ? "{$state} ({$record->user_country})" : '🌎 N/A');
    }

    public static function viewLastLogIn(): TextEntry
    {
        return TextEntry::make('last_log_in')
            ->label(__('resources/user/strings.form.last_log_in'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewLastLogOut(): TextEntry
    {
        return TextEntry::make('last_log_out')
            ->label(__('resources/user/strings.form.last_log_out'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('Created At'))
            ->dateTime('M Y | D: H:i:s');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('Updated At'))
            ->dateTime('M Y | D: H:i:s');
    }
}
