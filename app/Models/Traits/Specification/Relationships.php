<?php

namespace App\Models\Traits\Specification;

trait Relationships
{
    public function specifiable()
    {
        return $this->morphTo();
    }
}
