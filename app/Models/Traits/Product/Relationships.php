<?php

namespace App\Models\Traits\Product;

use App\Models\Category;
use App\Models\Target;

trait Relationships
{
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function targets()
    {
        return $this->morphMany(Target::class, 'targetable');
    }
}
