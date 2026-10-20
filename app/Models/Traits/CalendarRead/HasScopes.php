<?php

namespace App\Models\Traits\CalendarRead;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait HasScopes
{
    public function scopeByUser(Builder $query, User $user): Builder
    {
        return $query->where('calendar_reads.user_id', $user->id);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereColumn('calendar_reads.event_date', 'calendar_hits.event_date');
    }
}
