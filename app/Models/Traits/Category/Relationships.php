<?php

namespace App\Models\Traits\Category;

use App\Models\Category;
use App\Models\Product;
use App\Models\Target;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }


    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }


    public function ancestors()
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'descendant_id',
            'ancestor_id'
        )
            ->withPivot('depth');
    }

    public function sortedAncestors()
    {
        return $this->ancestors()
            ->wherePivot('depth', '>', 0)
            ->get()
            ->sortBy('parent_id');
    }

    public function descendants()
    {
        return $this->belongsToMany(
            Category::class,
            'category_closure',
            'ancestor_id',
            'descendant_id'
        )->withPivot('depth');
    }

    public function products(): HasMany
    {
        return $this->hasMany(
            Product::class,
            'category_id',
            'id'
        );
    }

    public function targets()
    {
        return $this->morphMany(Target::class, 'targetable');
    }
}
