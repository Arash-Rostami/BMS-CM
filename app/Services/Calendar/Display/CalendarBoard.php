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
use App\Services\Calendar\CalendarActivity;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarPathResolver;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

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

    public const RULE_OPTIONS_LIMIT = 300;

    public const CONDITIONS_LIMIT = 8;

    public const RULE_WITH = 'rule:id,name,color,type,subject,filters,extra_paths,date_path,updated_at';

    /**
     * @return array{0: ?int, 1: ?string}
     */
    public static function sanitize(User $user, mixed $ruleId, mixed $module, bool $moduleWins = false): array
    {
        $ruleId = filter_var($ruleId, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $subject = $ruleId === null ? null : static::viewableRules($user)->whereKey($ruleId)->value('subject');
        $module = is_string($module) && in_array($module, CalendarModules::viewableBy($user), true) ? $module : null;

        if ($subject === null) {
            return [null, $module];
        }

        if ($module === null || $module === $subject) {
            return [$ruleId, $module];
        }

        return $moduleWins ? [null, $module] : [$ruleId, null];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, object{event_date: Carbon, rule_color: string, rule_type: string, hit_count: int}>
     */
    public static function monthGroups(User $user, CalendarRange $range, ?int $ruleId, ?string $module)
    {
        return static::filtered($user, $ruleId, $module, 'unread')
            ->whereBetween('calendar_hits.event_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->join('calendar_rules', 'calendar_rules.id', '=', 'calendar_hits.calendar_rule_id')
            ->groupBy('calendar_hits.event_date', 'calendar_hits.calendar_rule_id', 'calendar_rules.color', 'calendar_rules.type')
            ->orderBy('calendar_hits.event_date')
            ->orderBy('calendar_hits.calendar_rule_id')
            ->selectRaw('calendar_hits.event_date, calendar_hits.calendar_rule_id, calendar_rules.color as rule_color, calendar_rules.type as rule_type, count(*) as hit_count')
            ->get();
    }

    public static function nextEventDate(User $user, Carbon $day, ?int $ruleId, ?string $module, string $read = 'all'): ?string
    {
        $date = static::filtered($user, $ruleId, $module, $read)->where('event_date', '>', $day->toDateString())->min('event_date');

        return $date ? Carbon::parse($date)->toDateString() : null;
    }

    public static function attentionCount(User $user, ?CalendarRange $range = null, string $read = 'all', ?string $time = null): int
    {
        return array_sum(static::attentionModules($user, $range, $read, $time));
    }

    /**
     * @return array<string, int>
     */
    public static function attentionModules(User $user, ?CalendarRange $range = null, string $read = 'all', ?string $time = null): array
    {
        if (CalendarModules::viewableBy($user) === []) {
            return [];
        }

        return static::attentionWindow($user, $range, $read, $time)
            ->select('calendar_hits.subject_type')
            ->selectRaw('count(*) as hit_count')
            ->groupBy('calendar_hits.subject_type')
            ->pluck('hit_count', 'subject_type')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();
    }

    public static function attentionPage(User $user, ?CalendarRange $range, string $read, ?string $time, ?string $module, int $perPage, int $page, int $total): LengthAwarePaginator
    {
        return static::attentionWindow($user, $range, $read, $time)
            ->when($module, fn (Builder $query): Builder => $query->where('calendar_hits.subject_type', $module))
            ->withReadAt($user)
            ->with([self::RULE_WITH, 'subject' => static::subjectLoader()])
            ->orderBy('calendar_hits.event_date')
            ->orderBy('calendar_hits.id')
            ->paginate($perPage, ['*'], 'page', $page, $total);
    }

    public static function attentionWindow(User $user, ?CalendarRange $range = null, string $read = 'all', ?string $time = null): Builder
    {
        $today = today();
        $range ??= CalendarRange::forMonth($today->toDateString(), isJalaliCalendar());
        $carriesOver = $today->betweenIncluded($range->start, $range->end);

        return CalendarHit::query()
            ->visibleTo($user)
            ->notPastHeadsUp()
            ->where(fn (Builder $query): Builder => $query
                ->whereBetween('event_date', [$range->start->toDateString(), $range->end->toDateString()])
                ->when($carriesOver, fn (Builder $query): Builder => $query->orWhere(fn (Builder $overdue): Builder => $overdue->overdue())))
            ->when($time === 'previous', fn (Builder $query): Builder => $query->where('event_date', '<', $today->toDateString()))
            ->when($time === 'upcoming', fn (Builder $query): Builder => $query->where('event_date', '>=', $today->toDateString()))
            ->when($read !== 'all', fn (Builder $query): Builder => $query->readBy($user, $read === 'read'));
    }

    public static function dayQuery(User $user, Carbon $day, ?int $ruleId, ?string $module, string $read = 'all'): Builder
    {
        return static::filtered($user, $ruleId, $module, $read)
            ->forDay($day)
            ->withReadAt($user)
            ->with([self::RULE_WITH, 'subject' => static::subjectLoader()]);
    }

    public static function monthQuery(User $user, CalendarRange $range, ?int $ruleId, ?string $module, string $read = 'all'): Builder
    {
        return static::filtered($user, $ruleId, $module, $read)
            ->whereBetween('calendar_hits.event_date', [$range->start->toDateString(), $range->end->toDateString()])
            ->withReadAt($user)
            ->orderBy('calendar_hits.event_date')
            ->with([self::RULE_WITH, 'subject' => static::subjectLoader()]);
    }

    public static function legendPage(User $user, ?string $module, int $perPage, int $page, int $total, ?int $keepId = null): LengthAwarePaginator
    {
        $rules = static::legendQuery($user, $module)
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'color'], 'legendPage', $page, $total);

        if ($keepId && ! $rules->getCollection()->contains('id', $keepId)) {
            $kept = static::legendQuery($user, $module)->whereKey($keepId)->get(['id', 'name', 'color']);
            $rules->setCollection($kept->concat($rules->getCollection()));
        }

        return $rules;
    }

    public static function legendCount(User $user, ?string $module): int
    {
        return static::legendQuery($user, $module)->count();
    }

    private static function legendQuery(User $user, ?string $module): Builder
    {
        return static::viewableRules($user)
            ->when($module, fn (Builder $query): Builder => $query->where('subject', $module));
    }

    /**
     * @return array{name: string, type: string, color: string, date: string, lead: string, conditions: array<int, string>, conditions_more: int, changes: \Illuminate\Support\Collection<int, array<string, mixed>>}|null
     */
    public static function ruleDetails(User $user, ?int $ruleId): ?array
    {
        $rule = $ruleId ? static::viewableRules($user)->find($ruleId) : null;

        if ($rule === null) {
            return null;
        }

        $guide = static::ruleReason($rule);
        $conditions = $guide['conditions'];

        return [
            'name' => $rule->name,
            'type' => (string) $rule->type?->getLabel(),
            'color' => $rule->color?->value ?? 'slate',
            'date' => $guide['date'],
            'lead' => collect($rule->lead_times)->sortDesc()->implode(', '),
            'conditions' => array_slice($conditions, 0, static::CONDITIONS_LIMIT),
            'conditions_more' => max(0, count($conditions) - static::CONDITIONS_LIMIT),
            'changes' => app(CalendarActivity::class)->forRule($rule),
        ];
    }

    /**
     * @return array{date: string, conditions: array<int, string>}
     */
    public static function ruleReason(CalendarRule $rule): array
    {
        $key = 'calendar_rule_guide:'.$rule->id.':'.$rule->updated_at?->timestamp.':'.app()->getLocale().':'.(isJalaliCalendar() ? 'j' : 'g');

        return Cache::remember($key, now()->addMinutes(10), function () use ($rule): array {
            $resolver = app(CalendarPathResolver::class);

            return [
                'date' => $resolver->datePathOptions($rule->subject)[$rule->date_path] ?? $rule->date_path,
                'conditions' => $resolver->summaries($rule),
            ];
        });
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
    public static function ruleOptions(User $user, ?int $keepId = null): array
    {
        $options = static::viewableRules($user)->orderBy('name')->limit(static::RULE_OPTIONS_LIMIT)->pluck('name', 'id');

        if ($keepId && ! $options->has($keepId)) {
            $options = static::viewableRules($user)->whereKey($keepId)->pluck('name', 'id')->union($options);
        }

        return $options->all();
    }

    private static function viewableRules(User $user): Builder
    {
        return CalendarRule::query()
            ->visibleTo($user)
            ->active()
            ->whereIn('subject', CalendarModules::viewableBy($user));
    }

    private static function filtered(User $user, ?int $ruleId, ?string $module, string $read = 'all'): Builder
    {
        return CalendarHit::query()
            ->visibleTo($user)
            ->notPastHeadsUp()
            ->when($ruleId, fn (Builder $query): Builder => $query->where('calendar_rule_id', $ruleId))
            ->when($module, fn (Builder $query): Builder => $query->where('subject_type', $module))
            ->when($read !== 'all', fn (Builder $query): Builder => $query->readBy($user, $read === 'read'));
    }

    private static function subjectLoader(): Closure
    {
        return fn (MorphTo $morphTo): MorphTo => $morphTo->morphWith(self::MORPH_WITH);
    }
}
