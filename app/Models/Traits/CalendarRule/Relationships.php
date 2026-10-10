<?php

namespace App\Models\Traits\CalendarRule;

use App\Models\CalendarHit;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
    public function hits(): HasMany
    {
        return $this->hasMany(CalendarHit::class, 'calendar_rule_id');
    }
}
