<?php

namespace App\Services\Calendar\Display;

use App\Models\BankProfile;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\Correspondence;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

class CalendarBoard
{
    public const MORPH_WITH = [
        BankProfile::class => ['status'],
        Correspondence::class => ['status'],
        Payment::class => ['status'],
        PurchaseOrder::class => ['status'],
        PurchaseRequest::class => ['status'],
        RegisteredOrder::class => ['status'],
        Shipment::class => ['status'],
    ];

    public const AGENDA_LIMIT = 500;

    /**
     * @return array{0: ?int, 1: ?string}
     */
    public static function sanitize(User $user, mixed $ruleId, mixed $module): array
    {
        $ruleId = filter_var($ruleId, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);

        return [
            $ruleId !== null && static::viewableRules($user)->whereKey($ruleId)->exists() ? $ruleId : null,
            is_string($module) && in_array($module, CalendarModules::viewableBy($user), true) ? $module : null,
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, object{event_date: Carbon, rule_color: string, rule_type: string, hit_count: int}>
     */
    public static function monthGroups(User $user, CalendarRange $range, ?int $ruleId, ?string $module)
    {
        return static::filtered($user, $ruleId, $module)
            ->whereBetween('calendar_hits.event_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->join('calendar_rules', 'calendar_rules.id', '=', 'calendar_hits.calendar_rule_id')
            ->groupBy('calendar_hits.event_date', 'calendar_hits.calendar_rule_id', 'calendar_rules.color', 'calendar_rules.type')
            ->selectRaw('calendar_hits.event_date, calendar_hits.calendar_rule_id, calendar_rules.color as rule_color, calendar_rules.type as rule_type, count(*) as hit_count')
            ->get();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CalendarHit>
     */
    public static function agendaHits(User $user, CalendarRange $range, ?int $ruleId, ?string $module)
    {
        return static::filtered($user, $ruleId, $module)
            ->with(['rule:id,name,color,type', 'subject'])
            ->whereBetween('event_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->orderBy('event_date')
            ->limit(static::AGENDA_LIMIT)
            ->get();
    }

    public static function attentionCount(User $user): int
    {
        if (CalendarModules::viewableBy($user) === []) {
            return 0;
        }

        $today = today();

        return static::attentionWindow($user, $today)->count();
    }

    private static function attentionWindow(User $user, Carbon $today): Builder
    {
        return CalendarHit::query()
            ->visibleTo($user)
            ->notPastHeadsUp()
            ->where(fn (Builder $query): Builder => $query
                ->overdue()
                ->orWhereBetween('event_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()]));
    }

    public static function dayQuery(User $user, Carbon $day, ?int $ruleId, ?string $module): Builder
    {
        return static::filtered($user, $ruleId, $module)
            ->forDay($day)
            ->with(['rule:id,name,color,type', 'subject' => static::subjectLoader()]);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CalendarRule>
     */
    public static function legendRules(User $user, ?string $module)
    {
        return static::viewableRules($user)
            ->active()
            ->when($module, fn (Builder $query): Builder => $query->where('subject', $module))
            ->orderBy('name')
            ->get(['id', 'name', 'color']);
    }

    /**
     * @return array<int, string>
     */
    public static function moduleOptions(User $user): array
    {
        return collect(CalendarModules::viewableBy($user))
            ->mapWithKeys(fn (string $module): array => [$module => CalendarModules::label($module)])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function ruleOptions(User $user): array
    {
        return static::viewableRules($user)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function viewableRules(User $user): Builder
    {
        return CalendarRule::query()
            ->visibleTo($user)
            ->whereIn('subject', CalendarModules::viewableBy($user));
    }

    private static function filtered(User $user, ?int $ruleId, ?string $module): Builder
    {
        return CalendarHit::query()
            ->visibleTo($user)
            ->notPastHeadsUp()
            ->when($ruleId, fn (Builder $query): Builder => $query->where('calendar_rule_id', $ruleId))
            ->when($module, fn (Builder $query): Builder => $query->where('subject_type', $module));
    }

    private static function subjectLoader(): Closure
    {
        return fn (MorphTo $morphTo): MorphTo => $morphTo->morphWith(self::MORPH_WITH);
    }
}
