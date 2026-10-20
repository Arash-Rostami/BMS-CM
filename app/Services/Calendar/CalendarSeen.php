<?php

namespace App\Services\Calendar;

use App\Models\CalendarHit;
use App\Models\CalendarRead;
use App\Models\User;
use Illuminate\Support\Carbon;

class CalendarSeen
{
    /**
     * @var array<int, array<int, Carbon>>
     */
    private static array $memo = [];

    public static function flush(): void
    {
        static::$memo = [];
    }

    /**
     * @return array<int, int>
     */
    public function readIds(User $user): array
    {
        return array_keys($this->map($user));
    }

    public function isRead(User $user, CalendarHit $hit): bool
    {
        return isset($this->map($user)[$hit->id]);
    }

    public function seenAt(User $user, int $hitId): ?Carbon
    {
        return $this->map($user)[$hitId] ?? null;
    }

    /**
     * @param  iterable<int, CalendarHit|int|string>  $hitsOrIds
     */
    public function mark(User $user, iterable $hitsOrIds): void
    {
        $ids = $this->idsOf($hitsOrIds);
        $hits = $ids === [] ? collect() : CalendarHit::query()->visibleTo($user)->whereIn('id', $ids)->get(['id', 'event_date']);

        if ($hits->isEmpty()) {
            return;
        }

        $stamp = now();

        CalendarRead::query()->upsert(
            $hits->map(fn (CalendarHit $hit): array => [
                'user_id' => $user->id,
                'calendar_hit_id' => $hit->id,
                'event_date' => $hit->event_date->toDateString(),
                'read_at' => $stamp,
            ])->all(),
            ['user_id', 'calendar_hit_id'],
            ['event_date', 'read_at']
        );

        unset(static::$memo[$user->getKey()]);
    }

    /**
     * @param  iterable<int, CalendarHit|int|string>  $hitsOrIds
     */
    public function unmark(User $user, iterable $hitsOrIds): void
    {
        $ids = $this->idsOf($hitsOrIds);

        if ($ids === []) {
            return;
        }

        CalendarRead::query()->byUser($user)->whereIn('calendar_hit_id', $ids)->delete();

        unset(static::$memo[$user->getKey()]);
    }

    /**
     * @return array<int, Carbon>
     */
    private function map(User $user): array
    {
        return static::$memo[$user->getKey()] ??= CalendarRead::query()
            ->byUser($user)
            ->join('calendar_hits', 'calendar_hits.id', '=', 'calendar_reads.calendar_hit_id')
            ->current()
            ->get(['calendar_reads.calendar_hit_id', 'calendar_reads.read_at'])
            ->mapWithKeys(fn (CalendarRead $read): array => [$read->calendar_hit_id => $read->read_at])
            ->all();
    }

    /**
     * @param  iterable<int, CalendarHit|int|string>  $hitsOrIds
     * @return array<int, int>
     */
    private function idsOf(iterable $hitsOrIds): array
    {
        $ids = [];

        foreach ($hitsOrIds as $item) {
            $id = $item instanceof CalendarHit ? $item->id : (is_numeric($item) ? (int) $item : 0);

            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}
