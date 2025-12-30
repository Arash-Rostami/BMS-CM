<?php

namespace App\Filament\Resources\Master\PermissionResource\Traits;

use App\Services\PermissionLabeler;
use Filament\Tables\Filters\SelectFilter;

trait Filters
{
    public static function getModuleFilter(): SelectFilter
    {
        return SelectFilter::make('module')
            ->label(__('resources/permission/strings.filters.module'))
            ->options(PermissionLabeler::getModuleOptions())
            ->query(function ($query, array $data) {
                if (empty($data['value'])) return $query;
                return $query->where('name', 'like', "{$data['value']}.%");
            })
            ->searchable();
    }
}
