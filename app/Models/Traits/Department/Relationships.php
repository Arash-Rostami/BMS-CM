<?php

namespace App\Models\Traits\Department;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
