<?php

namespace App\Filament\Resources\Master\UserResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\PositionStatus;
use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Filament\Resources\Master\UserResource\Enums\UserStatus;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;

trait Infolist
{
    public static function viewCompany(): TextEntry
    {
        return TextEntry::make('company')
            ->label(__('resources/user/strings.form.company'))
            ->icon('heroicon-m-building-office')
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/user/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewDepartment(): TextEntry
    {
        return TextEntry::make('department.name')
            ->label(__('resources/user/strings.form.department'))
            ->icon('heroicon-m-building-office-2')
            ->placeholder('-');
    }

    public static function viewEmail(): TextEntry
    {
        return TextEntry::make('email')
            ->label(__('resources/user/strings.form.email'))
            ->icon('heroicon-m-envelope')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewIP(): TextEntry
    {
        return TextEntry::make('ip')
            ->label(__('resources/user/strings.form.ip'))
            ->formatStateUsing(fn ($state, ?Model $record): string => ($state && $record) ? "{$state} ({$record->user_country})" : '🌎 N/A')
            ->icon('heroicon-m-globe-alt')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewImage(): ImageEntry
    {
        return ImageEntry::make('image')
            ->label(__('resources/user/strings.form.image'))
            ->circular()
            ->imageSize(80)
            ->disk('public')
            ->visibility('public');
    }

    public static function viewLastLogIn(): TextEntry
    {
        return TextEntry::make('last_log_in')
            ->label(__('resources/user/strings.form.last_log_in'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->icon('heroicon-m-arrow-right-end-on-rectangle')
            ->placeholder('-');
    }

    public static function viewLastLogOut(): TextEntry
    {
        return TextEntry::make('last_log_out')
            ->label(__('resources/user/strings.form.last_log_out'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->icon('heroicon-m-arrow-left-start-on-rectangle')
            ->placeholder('-');
    }

    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/user/strings.form.name'))
            ->icon('heroicon-m-user')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewPhone(): TextEntry
    {
        return TextEntry::make('phone')
            ->label(__('resources/user/strings.form.phone'))
            ->icon('heroicon-m-phone')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewPosition(): TextEntry
    {
        return TextEntry::make('position')
            ->label(__('resources/user/strings.form.position'))
            ->color(fn (string $state): string => PositionStatus::tryFrom($state)?->getColor() ?? 'gray')
            ->badge()
            ->icon('heroicon-m-briefcase')
            ->placeholder('-');
    }

    public static function viewRole(): TextEntry
    {
        return TextEntry::make('roles.name')
            ->label(__('resources/user/strings.form.role'))
            ->badge()
            ->formatStateUsing(fn (string $state): string => UserRole::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn (string $state): string => UserRole::tryFrom($state)?->getColor() ?? 'gray')
            ->icon('heroicon-m-shield-check')
            ->placeholder('-');
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status')
            ->label(__('resources/user/strings.form.status'))
            ->formatStateUsing(fn (string $state): string => UserStatus::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn (string $state): string => UserStatus::tryFrom($state)?->getColor() ?? 'gray')
            ->badge()
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/user/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }
}
