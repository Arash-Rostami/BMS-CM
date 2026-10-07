<?php

namespace App\Filament\Resources\Master\PermissionResource\Traits;

use App\Services\PermissionLabeler;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;

trait Filters
{
    public static function getModuleFilter(): SelectFilter
    {
        return SelectFilter::make('module')
            ->label(__('resources/permission/strings.filters.module'))
            ->options(PermissionLabeler::getModuleOptions())
            ->query(function ($query, array $data) {
                if (empty($data['value'])) {
                    return $query;
                }

                return $query->where('name', 'like', "{$data['value']}.%");
            })
            ->searchable();
    }

    public static function getUngrantedFilter(): Filter
    {
        return Filter::make('ungranted')
            ->label(__('resources/permission/strings.filters.ungranted'))
            ->toggle()
            ->query(fn ($query) => $query->doesntHave('roles')->doesntHave('users'))
            ->indicator(__('resources/permission/strings.filters.ungranted_indicator'));
    }
}
