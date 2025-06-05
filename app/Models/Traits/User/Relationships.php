<?php

namespace App\Models\Traits\User;

use App\Models\Department;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Relationships
{
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
