<?php

namespace App\Filament\Resources\Master\UserResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\PositionStatus;
use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Filament\Resources\Master\UserResource\Enums\UserStatus;
use App\Models\User;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;

trait Filters
{

    public static function getThrashedFilter()
    {
        return TrashedFilter::make();
    }

    public static function getCompanyFilter(): SelectFilter
    {
        return SelectFilter::make('company')
            ->label(__('resources/user/strings.table.company'))
            ->options(
                User::distinct('company')
                    ->pluck('company', 'company')
                    ->sort()
                    ->filter(fn($company) => !empty($company))
                    ->all()
            )
            ->multiple()
            ->searchable();
    }


    public static function getDepartmentFilter(): SelectFilter
    {
        return SelectFilter::make('department')
            ->label(__('resources/user/strings.table.department'))
            ->relationship(
                name: 'department',
                titleAttribute: fn() => app()->getLocale() === 'fa' ? ('name' ?? 'english_name') : 'english_name');
    }


    public static function getRoleFilter(): SelectFilter
    {
        return SelectFilter::make('role')
            ->label(__('resources/user/strings.table.role'))
            ->options(UserRole::class)
            ->multiple();
    }


    public static function getPositionFilter(): SelectFilter
    {
        return SelectFilter::make('position')
            ->label(__('resources/user/strings.table.position'))
            ->options(PositionStatus::class)
            ->multiple();
    }

    public static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label(__('resources/user/strings.table.status'))
            ->options(UserStatus::class)
            ->multiple();
    }
}
