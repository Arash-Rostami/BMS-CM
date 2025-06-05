<?php

namespace App\Models\Traits\Category;

use Illuminate\Database\Eloquent\Builder;

trait Scopables
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }


    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }


    public function scopeLevel(Builder $query, int $level): Builder
    {
        return $query->where('level', $level);
    }
}
