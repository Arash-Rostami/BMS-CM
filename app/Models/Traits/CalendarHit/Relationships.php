<?php

namespace App\Models\Traits\CalendarHit;

use App\Models\CalendarRule;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait Relationships
{
    public function rule(): BelongsTo
    {
        return $this->belongsTo(CalendarRule::class, 'calendar_rule_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
