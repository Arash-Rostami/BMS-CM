<?php

namespace App\Filament\Resources\Master\UserResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\PositionStatus;
use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Filament\Resources\Master\UserResource\Enums\UserStatus;
use App\Models\User;
use App\Services\SmartCacheManager;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait Filters
{
    public static function getCompanyFilter(): SelectFilter
    {
        return SelectFilter::make('company')
            ->label(__('resources/user/strings.filters.company'))
            ->options(fn () => SmartCacheManager::remember(
                'User',
                ['filter' => 'company_options'],
                150,
                fn () => User::distinct('company')
                    ->pluck('company', 'company')
                    ->sort()
                    ->filter(fn ($company) => ! empty($company))
                    ->all()
            ))
            ->multiple()
            ->searchable();
    }

    public static function getDepartmentFilter(): SelectFilter
    {
        return SelectFilter::make('department')
            ->label(__('resources/user/strings.filters.department'))
            ->relationship('department', app()->getLocale() === 'fa' ? 'name' : 'english_name')
            ->preload()
            ->searchable();
    }

    public static function getPositionFilter(): SelectFilter
    {
        return SelectFilter::make('position')
            ->label(__('resources/user/strings.filters.position'))
            ->options(PositionStatus::class)
            ->multiple();
    }

    public static function getRoleFilter(): SelectFilter
    {
        return SelectFilter::make('roles')
            ->label(__('resources/user/strings.filters.role'))
            ->relationship('roles', 'name')
            ->getOptionLabelFromRecordUsing(fn (Model $record) => UserRole::tryFrom($record->name)?->getLabel() ?? $record->name)
            ->multiple()
            ->preload()
            ->searchable();
    }

    public static function getStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label(__('resources/user/strings.filters.status'))
            ->options(UserStatus::class)
            ->multiple();
    }

    public static function getStaleLoginFilter(): Filter
    {
        return Filter::make('stale_login')
            ->label(__('resources/user/strings.filters.stale_login'))
            ->indicator(__('resources/user/strings.filters.stale_login'))
            ->toggle()
            ->query(fn (Builder $query) => $query->where(
                fn (Builder $query) => $query->whereNull('last_log_in')->orWhere('last_log_in', '<', now()->subDays(30))
            ));
    }

    public static function getThrashedFilter()
    {
        return TrashedFilter::make();
    }
}
