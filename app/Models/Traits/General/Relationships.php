<?php

namespace App\Models\Traits\General;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Relationships
{
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_id');
    }
}
