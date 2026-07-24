<?php

namespace App\Filament\Resources\Master\EntityAttributeResource\Traits;

use App\Models\EntityAttribute;
use App\Models\User;
use App\Services\PermissionLabeler;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;

trait Filters
{
    public static function getCreatorFilter(): SelectFilter
    {
        return SelectFilter::make('user_id')
            ->label(__('resources/entityAttribute/strings.filters.creator'))
            ->options(fn (): array => User::query()->pluck('name', 'id')->toArray())
            ->searchable();
    }

    public static function getEntityTypeFilter(): SelectFilter
    {
        return SelectFilter::make('entity_type')
            ->label(__('resources/entityAttribute/strings.filters.entity_type'))
            ->options(fn (): array => EntityAttribute::query()
                ->distinct()
                ->pluck('entity_type', 'entity_type')
                ->mapWithKeys(fn ($v) => [$v => PermissionLabeler::getEntityLabel($v)])
                ->toArray());
    }

    public static function getKeyFilter(): SelectFilter
    {
        return SelectFilter::make('key')
            ->label(__('resources/entityAttribute/strings.filters.key'))
            ->options(fn (): array => EntityAttribute::query()
                ->distinct()
                ->pluck('key', 'key')
                ->toArray())
            ->searchable();
    }

    public static function getTrashedFilter(): TrashedFilter
    {
        return TrashedFilter::make();
    }
}
