<?php

namespace App\Models\Traits\Target;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;


trait SearchTargetable
{
    public function scopeSearchTargetable(Builder $query, string $term): Builder
    {
        return $query
            ->orWhereHasMorph(
                'targetable',
                [Category::class],
                fn(Builder $q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('english_name', 'like', "%{$term}%")
            )
            ->orWhereHasMorph(
                'targetable',
                [Product::class],
                fn(Builder $q) => $q->where('code', 'like', "%{$term}%")
            );
    }
}
