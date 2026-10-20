<?php

namespace App\Models\Traits\CalendarHit;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Models\CalendarRead;
use App\Models\CalendarRule;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait HasScopes
{
    /**
     * @var array<int, array<int, int>>
     */
    private static array $visibleRuleIds = [];

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
        return static::$visibleRuleIds[$user->id] ??= CalendarRule::query()->visibleTo($user)->active()->pluck('id')->all();
    }

    public static function flushVisibleRuleIds(): void
    {
        static::$visibleRuleIds = [];
    }

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

    public function scopeReadBy(Builder $query, User $user, bool $read = true): Builder
    {
        $constraint = fn (Builder $reads): Builder => $reads->byUser($user)->current();

        return $read ? $query->whereHas('reads', $constraint) : $query->whereDoesntHave('reads', $constraint);
    }

    public function scopeWithReadAt(Builder $query, User $user): Builder
    {
        return $query->select('calendar_hits.*')->addSelect([
            'read_at' => CalendarRead::query()
                ->select('read_at')
                ->whereColumn('calendar_reads.calendar_hit_id', 'calendar_hits.id')
                ->byUser($user)
                ->current()
                ->limit(1),
        ]);
    }
}
