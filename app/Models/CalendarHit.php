<?php

namespace App\Models;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Models\Traits\CalendarHit\Relationships;
use App\Services\Calendar\CalendarModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class CalendarHit extends Model
{
    use HasFactory,
        Relationships;

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
    ];

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->whereIn('subject_type', CalendarModules::viewableBy($user))
            ->whereIn('calendar_rule_id', static::visibleRuleIds($user));
    }

    /**
     * @return array<int, int>
     */
    public static function visibleRuleIds(User $user): array
    {
        return static::$visibleRuleIds[$user->id] ??= CalendarRule::query()->visibleTo($user)->pluck('id')->all();
    }

    public static function flushVisibleRuleIds(): void
    {
        static::$visibleRuleIds = [];
    }

    /**
     * @var array<int, array<int, int>>
     */
    private static array $visibleRuleIds = [];

    public function scopeForDay(Builder $query, Carbon $day): Builder
    {
        return $query->where('event_date', $day->toDateString());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereHas('rule', fn (Builder $query): Builder => $query->where('type', RuleType::ACTION->value))
            ->where('event_date', '<', today()->toDateString());
    }

    public function scopeNotPastHeadsUp(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->where('event_date', '>=', today()->toDateString())
                ->orWhereHas('rule', fn (Builder $query): Builder => $query->where('type', RuleType::ACTION->value));
        });
    }
}
