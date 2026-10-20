<?php

namespace App\Models;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Models\Traits\CalendarHit\HasScopes;
use App\Models\Traits\CalendarHit\Relationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarHit extends Model
{
    use HasFactory,
        Relationships,
        HasScopes;

    protected $fillable = [
        'calendar_rule_id',
        'subject_type',
        'subject_id',
        'label',
        'event_date',
        'alerts_sent',
        'overdue_count',
        'synced_at',
    ];

    protected $casts = [
        'event_date' => 'date',
        'alerts_sent' => 'array',
        'overdue_count' => 'integer',
        'synced_at' => 'datetime',
        'read_at' => 'datetime',
    ];

    public function urgency(): string
    {
        $days = (int) today()->diffInDays($this->event_date->copy()->startOfDay(), false);

        return match (true) {
            $days < 0 && $this->rule?->type === RuleType::ACTION => 'overdue',
            $days <= 0 => 'today',
            $days <= 3 => 'soon',
            default => 'later',
        };
    }
}
