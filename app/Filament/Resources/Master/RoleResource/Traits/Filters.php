<?php

namespace App\Filament\Resources\Master\RoleResource\Traits;

use App\Models\Role;
use Filament\Tables\Filters\SelectFilter;

trait Filters
{
    public static function getNameFilter(): SelectFilter
    {
        return SelectFilter::make('base_name')
            ->label(__('resources/role/strings.filters.name'))
            ->options(fn() => Role::getUniqueBaseNames())
            ->query(fn($query, array $data) => empty($data['value']) ? $query : $query->whereBaseName($data['value']))
            ->searchable();
    }

    public static function getGradeFilter(): SelectFilter
    {
        return SelectFilter::make('grade')
            ->label(__('resources/role/strings.filters.grade'))
            ->options(Role::getGradeOptions())
            ->query(fn($query, array $data) => empty($data['value']) ? $query : $query->whereGrade($data['value']));
    }
}
