<?php

namespace App\Models\Traits\Company;

use Illuminate\Database\Eloquent\Builder;


trait HasSearchableRelations
{
    public function scopeSearchCompany(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if (!$term) return $query;

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('english_name', 'like', "%{$term}%");
        });
    }
}
