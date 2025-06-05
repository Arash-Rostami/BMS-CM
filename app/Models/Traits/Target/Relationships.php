<?php

namespace App\Models\Traits\Target;


use Illuminate\Database\Eloquent\Relations\MorphTo;

trait Relationships
{
    public function targetable(): morphTo
    {
        return $this->morphTo();
    }
}
