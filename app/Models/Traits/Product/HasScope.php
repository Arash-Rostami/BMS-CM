<?php

namespace App\Models\Traits\Product;


use Illuminate\Database\Eloquent\Builder;

trait HasScope
{
    public function scopeWhereIsSheet(Builder $query): Builder
    {
        return $query->whereRaw("JSON_SEARCH(attributes, 'one', '%*%') IS NOT NULL");
    }

    public function scopeWhereIsRoll(Builder $query): Builder
    {
        return $query->whereRaw("LOWER(JSON_EXTRACT(attributes, '$')) LIKE '%cm%'");
    }
}
