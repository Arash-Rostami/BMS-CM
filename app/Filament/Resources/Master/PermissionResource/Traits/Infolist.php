<?php

namespace App\Filament\Resources\Master\PermissionResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Services\PermissionLabeler;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/permission/strings.infolist.name'))
            ->formatStateUsing(fn(string $state): string => PermissionLabeler::getLabel($state));
    }

    public static function viewRoles(): TextEntry
    {
        return TextEntry::make('roles.name')
            ->label(__('resources/permission/strings.infolist.roles'))
            ->badge()
            ->formatStateUsing(fn(string $state): string => UserRole::tryFrom($state)?->getLabel() ?? $state)
            ->color(fn(string $state): string => UserRole::tryFrom($state)?->getColor() ?? 'gray');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/permission/strings.infolist.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/permission/strings.infolist.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray');
    }
}
