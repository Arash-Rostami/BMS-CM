<?php

namespace App\Services\Calendar;

use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\User;
use App\Notifications\CalendarAlertNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CalendarActivity
{
    public const LOG_NAME = 'calendar';

    private const READ_LIMIT = 100;

    public function log(string $event, Model $record, CalendarRule $rule, array $props = []): void
    {
        activity(self::LOG_NAME)
            ->performedOn($record)
            ->withProperties(['event' => $event, 'rule_id' => $rule->id, 'rule_name' => $rule->name] + $props)
            ->log($event);
    }

    /**
     * Bulk path for sync loops — one insert() call per chunk, no Eloquent model events.
     *
     * @param  array<int, array{subject_type: string, subject_id: int, properties: array<string, mixed>}>  $entries
     */
    public function logMany(string $event, CalendarRule $rule, array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $now = now();
        $base = ['event' => $event, 'rule_id' => $rule->id, 'rule_name' => $rule->name];

        $rows = array_map(fn (array $entry): array => [
            'log_name' => self::LOG_NAME,
            'description' => $event,
            'subject_type' => $entry['subject_type'],
            'subject_id' => $entry['subject_id'],
            'event' => $event,
            'properties' => json_encode($base + ($entry['properties'] ?? [])),
            'created_at' => $now,
            'updated_at' => $now,
        ], $entries);

        DB::table(config('activitylog.table_name'))->insert($rows);
    }

    /**
     * History for one record. Descriptions are stored as event keys and rendered
     * at read time from resources/calendarRule/strings.activity.{event} — never frozen at write.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forViewer(Model $record, User $user, bool $all = false): Collection
    {
        return $this->forRecord($record, $all, CalendarRule::query()->visibleTo($user)->pluck('id')->all());
    }

    /**
     * @param  array<int, int>|null  $ruleIds  null = unrestricted; otherwise only events of these rules
     * @return Collection<int, array<string, mixed>>
     */
    public function forRecord(Model $record, bool $all = false, ?array $ruleIds = null): Collection
    {
        if ($ruleIds === []) {
            return collect();
        }

        $rows = $this->rowsFor($record, $ruleIds)->get();

        $events = $rows->map(fn ($row): array => [
            'event' => $row->description,
            'created_at' => $row->created_at,
            'properties' => json_decode($row->properties, true) ?? [],
        ])->values();

        if (! $all) {
            return $events;
        }

        return $events
            ->concat($this->ruleRows($events))
            ->concat($this->seenRows($record, $events))
            ->sortByDesc('created_at')
            ->values()
            ->take(self::READ_LIMIT);
    }

    private function rowsFor(Model $record, ?array $ruleIds): QueryBuilder
    {
        return DB::table(config('activitylog.table_name'))
            ->where('log_name', self::LOG_NAME)
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->when($ruleIds !== null, fn (QueryBuilder $query): QueryBuilder => $query
                ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(properties, "$.rule_id")) in ('.implode(',', array_map('intval', (array) $ruleIds)).')'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::READ_LIMIT);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @return Collection<int, array<string, mixed>>
     */
    private function ruleRows(Collection $events): Collection
    {
        $ruleIds = $events->pluck('properties.rule_id')->filter()->unique()->values()->all();

        if ($ruleIds === []) {
            return collect();
        }

        return DB::table(config('activitylog.table_name'))
            ->where('log_name', self::LOG_NAME)
            ->where('subject_type', (new CalendarRule)->getMorphClass())
            ->whereIn('subject_id', $ruleIds)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(self::READ_LIMIT)
            ->get()
            ->map(fn ($row): array => [
                'event' => $row->description,
                'created_at' => $row->created_at,
                'properties' => json_decode($row->properties, true) ?? [],
            ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @return Collection<int, array<string, mixed>>
     */
    private function seenRows(Model $record, Collection $events): Collection
    {
        $ruleIds = $events->pluck('properties.rule_id')->filter()->unique()->values()->all();

        if ($ruleIds === []) {
            return collect();
        }

        $hitIds = CalendarHit::query()
            ->where('subject_type', $record->getMorphClass())
            ->where('subject_id', $record->getKey())
            ->pluck('id');

        if ($hitIds->isEmpty()) {
            return collect();
        }

        return $this->readNotifications($hitIds->all(), $ruleIds)
            ->map(fn ($row): array => [
                'event' => 'seen',
                'created_at' => $row->read_at,
                'properties' => json_decode($row->data, true),
            ]);
    }

    /**
     * @param  array<int, int>  $hitIds
     * @param  array<int, int>  $ruleIds
     */
    private function readNotifications(array $hitIds, array $ruleIds): Collection
    {
        $rows = collect();

        foreach (array_chunk($hitIds, 50) as $chunk) {
            $contains = implode(' OR ', array_fill(0, count($chunk), 'JSON_CONTAINS(data, ?, "$.hit_ids")'));
            $rows = $rows->concat(DB::table('notifications')
                ->where('type', CalendarAlertNotification::class)
                ->whereNotNull('read_at')
                ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.rule_id")) in ('.implode(',', array_map('intval', $ruleIds)).')')
                ->whereRaw('('.$contains.')', array_map(fn (int $id): string => (string) $id, $chunk))
                ->orderByDesc('read_at')
                ->limit(self::READ_LIMIT)
                ->get());
        }

        return $rows->sortByDesc('read_at')->values()->take(self::READ_LIMIT);
    }
}
