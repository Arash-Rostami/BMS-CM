<?php

namespace App\Models\Traits\CalendarRead;

use App\Models\CalendarHit;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Relationships
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hit(): BelongsTo
    {
        return $this->belongsTo(CalendarHit::class, 'calendar_hit_id');
    }
}
