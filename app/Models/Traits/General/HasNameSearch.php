<?php

namespace App\Models\Traits\General;

use Illuminate\Database\Eloquent\Builder;

trait HasNameSearch
{
    public function scopeSearchByName(Builder $query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn (Builder $q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('english_name', 'like', "%{$term}%")
        );
    }
}
