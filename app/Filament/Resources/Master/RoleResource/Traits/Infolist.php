<?php

namespace App\Filament\Resources\Master\RoleResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Models\Role;
use App\Services\PermissionLabeler;
use Filament\Infolists\Components\TextEntry;

trait Infolist
{
    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/role/strings.infolist.name'))
            ->formatStateUsing(fn (string $state, Role $record) => $record->base_name)
            ->color(fn (string $state): string => UserRole::tryFrom($state)?->getColor() ?? 'gray')
            ->badge();
    }

    public static function viewGrade(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/role/strings.infolist.grade'))
            ->formatStateUsing(fn (string $state, Role $record) => $record->grade_label);
    }

    public static function viewPermissions(): TextEntry
    {
        return TextEntry::make('permissions.name')
            ->label(__('resources/role/strings.infolist.permissions'))
            ->formatStateUsing(fn (string $state) => PermissionLabeler::getLabel($state))
            ->columnSpanFull()
            ->badge();
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/role/strings.infolist.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/role/strings.infolist.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray');
    }
}
