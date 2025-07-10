<?php

namespace App\Models\Traits\General;

trait HasScope
{
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
