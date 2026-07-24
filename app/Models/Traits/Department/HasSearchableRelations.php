<?php

namespace App\Models\Traits\Department;

use Illuminate\Database\Eloquent\Builder;

trait HasSearchableRelations
{
    public function scopeSearchDepartment(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if (! $term) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->whereRaw('LOWER(name) LIKE LOWER(?)', ["%{$term}%"])
                ->orWhereRaw('LOWER(english_name) LIKE LOWER(?)', ["%{$term}%"])
                ->orWhereRaw('UPPER(code) LIKE UPPER(?)', ["%{$term}%"]);
        });
    }
}
