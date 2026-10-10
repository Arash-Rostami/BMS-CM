<?php

namespace App\Services\Calendar;

use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Jobs\SendCalendarRuleAlerts;
use App\Models\CalendarHit;
use App\Models\CalendarRule;
use App\Models\User;
use App\Notifications\CalendarAlertNotification;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class CalendarAlerts
{
    public function __construct(private CalendarActivity $activity) {}

    public function sendDue(Carbon $today): void
    {
        CalendarRule::query()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $id) => SendCalendarRuleAlerts::dispatch($id, $today->toDateString()));
    }

    public function sendForRule(CalendarRule $rule, Carbon $today): void
    {
        $entries = $this->dueHitsFor($rule, $today);

        if ($entries->isEmpty()) {
            return;
        }

        $recipients = $rule->recipients();
        $emails = array_values(array_diff($rule->outsideEmails(), $recipients->pluck('email')->map(fn (?string $email): string => mb_strtolower(trim((string) $email)))->all()));

        if ($recipients->isEmpty() && $emails === []) {
            return;
        }

        $this->deliver($rule, $today, $entries, $this->pendingRecipients($recipients, $rule, $today));
        $this->deliverToEmails($rule, $today, $entries, $this->pendingEmails($emails, $rule, $today));
        $this->commit($rule, $today, $entries);
    }

    /**
     * @param  Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     * @param  Collection<int, User>  $pending
     */
    private function deliver(CalendarRule $rule, Carbon $today, Collection $entries, Collection $pending): void
    {
        $notification = new CalendarAlertNotification($rule, $entries, $today->toDateString());

        foreach ($pending as $user) {
            Notification::send($user, $notification);

            if (! $rule->shouldSendInApp()) {
                Cache::put($this->mailGuardKey($rule, $today, $user), true, now()->addDays(2));
            }
        }
    }

    /**
     * @param  Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     * @param  array<int, string>  $pending
     */
    private function deliverToEmails(CalendarRule $rule, Carbon $today, Collection $entries, array $pending): void
    {
        $notification = new CalendarAlertNotification($rule, $entries, $today->toDateString());

        foreach ($pending as $email) {
            Notification::send(Notification::route('mail', $email), $notification);
            Cache::put($this->emailGuardKey($rule, $today, $email), true, now()->addDays(2));
        }
    }

    /**
     * @param  array<int, string>  $emails
     * @return array<int, string>
     */
    private function pendingEmails(array $emails, CalendarRule $rule, Carbon $today): array
    {
        return array_values(array_filter(
            $emails,
            fn (string $email): bool => ! Cache::has($this->emailGuardKey($rule, $today, $email)),
        ));
    }

    private function emailGuardKey(CalendarRule $rule, Carbon $today, string $email): string
    {
        return "calendar_alert_mail:{$rule->id}:{$today->toDateString()}:e".sha1($email);
    }

    private function mailGuardKey(CalendarRule $rule, Carbon $today, User $user): string
    {
        return "calendar_alert_mail:{$rule->id}:{$today->toDateString()}:{$user->id}";
    }

    /**
     * @return Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>
     */
    public function dueHitsFor(CalendarRule $rule, Carbon $today): Collection
    {
        $entries = collect();
        $this->collectLeadEntries($rule, $today, $entries);

        if ($rule->type === RuleType::ACTION) {
            $this->overdueEntries($rule, $today, $entries);
        }

        return $entries;
    }

    /**
     * @param  Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     */
    private function collectLeadEntries(CalendarRule $rule, Carbon $today, Collection $entries): void
    {
        $iso = $today->toDateString();
        $leadTimes = (array) ($rule->lead_times ?? []);
        $maxLead = $leadTimes === [] ? 0 : max($leadTimes);

        CalendarHit::query()
            ->where('calendar_rule_id', $rule->id)
            ->whereBetween('event_date', [$iso, $today->copy()->addDays($maxLead)->toDateString()])
            ->chunkById($this->chunkSize(), function (Collection $hits) use ($rule, $today, $iso, &$entries): void {
                foreach ($hits as $hit) {
                    $this->collectDueHit($rule, $hit, $today, $iso, $entries);
                }
            });
    }

    /**
     * @param  Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     */
    private function collectDueHit(CalendarRule $rule, CalendarHit $hit, Carbon $today, string $iso, Collection $entries): void
    {
        $onDay = $rule->on_day && ! isset($hit->alerts_sent[0]) && $hit->event_date->toDateString() === $iso;
        $leads = $this->dueLeads($rule, $hit, $today);

        if ($onDay) {
            $leads = array_values(array_diff($leads, [0]));
        }

        if ($leads !== [] || $onDay) {
            $entries->push(['hit' => $hit, 'leads' => $leads, 'on_day' => $onDay, 'overdue' => false]);
        }
    }

    /**
     * @return array<int, int>
     */
    private function dueLeads(CalendarRule $rule, CalendarHit $hit, Carbon $today): array
    {
        $sent = $hit->alerts_sent ?? [];
        $leads = [];

        foreach ((array) ($rule->lead_times ?? []) as $lead) {
            $lead = (int) $lead;

            if (isset($sent[$lead])) {
                continue;
            }

            if ($hit->event_date->copy()->subDays($lead)->lte($today) && $today->lte($hit->event_date)) {
                $leads[] = $lead;
            }
        }

        return $leads;
    }

    /**
     * @param  Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     */
    private function overdueEntries(CalendarRule $rule, Carbon $today, Collection $entries): void
    {
        $iso = $today->toDateString();

        CalendarHit::query()
            ->where('calendar_rule_id', $rule->id)
            ->where('event_date', '<', $iso)
            ->where('overdue_count', '<', (int) config('calendar.max_overdue_alerts'))
            ->chunkById($this->chunkSize(), function (Collection $hits) use ($today, &$entries): void {
                foreach ($hits as $hit) {
                    $dates = $hit->alerts_sent['overdue'] ?? [];

                    if ($dates === [] || $today->gte(Carbon::parse(max($dates))->addDays(7))) {
                        $entries->push(['hit' => $hit, 'leads' => [], 'on_day' => false, 'overdue' => true]);
                    }
                }
            });
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @return Collection<int, User>
     */
    private function pendingRecipients(Collection $recipients, CalendarRule $rule, Carbon $today): Collection
    {
        $notified = DB::table('notifications')
            ->where('type', CalendarAlertNotification::class)
            ->where('notifiable_type', (new User)->getMorphClass())
            ->whereIn('notifiable_id', $recipients->pluck('id')->all())
            ->whereRaw('JSON_EXTRACT(data, "$.rule_id") = ?', [$rule->id])
            ->whereRaw('JSON_UNQUOTE(JSON_EXTRACT(data, "$.alert_date")) = ?', [$today->toDateString()])
            ->pluck('notifiable_id');

        return $recipients
            ->reject(fn (User $user): bool => $notified->contains($user->id))
            ->unless($rule->shouldSendInApp(), fn (Collection $pending): Collection => $pending->reject(
                fn (User $user): bool => Cache::has($this->mailGuardKey($rule, $today, $user)),
            ));
    }

    /**
     * @param  Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     */
    private function commit(CalendarRule $rule, Carbon $today, Collection $entries): void
    {
        DB::transaction(function () use ($rule, $today, $entries): void {
            $valid = collect();

            foreach ($entries->chunk($this->chunkSize()) as $chunk) {
                $valid = $valid->concat($this->revalidate($chunk));
            }

            if ($valid->isEmpty()) {
                return;
            }

            foreach ($valid->chunk($this->chunkSize()) as $chunk) {
                $this->markSent($rule, $chunk, $today);
                $this->logSent($rule, $chunk);
            }
        });
    }

    /**
     * @param  Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     * @return Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>
     */
    private function revalidate(Collection $entries): Collection
    {
        $fresh = CalendarHit::query()
            ->whereIn('id', $entries->pluck('hit.id')->all())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        return $entries
            ->filter(fn (array $entry): bool => ($hit = $fresh->get($entry['hit']->id)) !== null
                && $hit->event_date->equalTo($entry['hit']->event_date))
            ->map(fn (array $entry): array => ['hit' => $fresh[$entry['hit']->id], 'leads' => $entry['leads'], 'on_day' => $entry['on_day'], 'overdue' => $entry['overdue']])
            ->values();
    }

    /**
     * @param  Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     */
    private function markSent(CalendarRule $rule, Collection $entries, Carbon $today): void
    {
        $iso = $today->toDateString();
        [$alertsCase, $alertsBindings] = $this->caseUpdate($entries, fn (array $entry): string => json_encode($this->sentAfterEntry($entry, $iso)));
        $overdue = $entries->filter(fn (array $entry): bool => $entry['overdue']);
        [$overdueCase, $overdueBindings] = $this->caseUpdate($overdue, fn (array $entry): int => $entry['hit']->overdue_count + 1);

        $sets = ['alerts_sent = CASE id '.$alertsCase.' ELSE alerts_sent END'];

        if ($overdue->isNotEmpty()) {
            $sets[] = 'overdue_count = CASE id '.$overdueCase.' ELSE overdue_count END';
        }

        $ids = $entries->pluck('hit.id')->all();
        DB::update(
            'update calendar_hits set '.implode(', ', $sets).' where calendar_rule_id = ? and id in ('.implode(',', array_fill(0, count($ids), '?')).')',
            [...$alertsBindings, ...$overdueBindings, $rule->id, ...$ids],
        );
    }

    /**
     * @param  Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function caseUpdate(Collection $entries, Closure $value): array
    {
        $whens = [];
        $bindings = [];

        foreach ($entries as $entry) {
            $whens[] = 'WHEN '.(int) $entry['hit']->id.' THEN ?';
            $bindings[] = $value($entry);
        }

        return [implode(' ', $whens), $bindings];
    }

    /**
     * @param  array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}  $entry
     * @return array<string, mixed>
     */
    private function sentAfterEntry(array $entry, string $iso): array
    {
        $sent = $entry['hit']->alerts_sent ?? [];

        foreach ($entry['leads'] as $lead) {
            $sent[$lead] = $iso;
        }

        if ($entry['on_day']) {
            $sent[0] = $iso;
        }

        if ($entry['overdue']) {
            $sent['overdue'] = [...($sent['overdue'] ?? []), $iso];
        }

        return $sent;
    }

    /**
     * @param  Collection<int, array{hit: CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     */
    private function logSent(CalendarRule $rule, Collection $entries): void
    {
        $this->activity->logMany('alert_sent', $rule, $entries
            ->map(fn (array $entry): array => [
                'subject_type' => $entry['hit']->subject_type,
                'subject_id' => $entry['hit']->subject_id,
                'properties' => [
                    'label' => $entry['hit']->label,
                    'event_date' => $entry['hit']->event_date->toDateString(),
                    'kind' => $entry['overdue'] ? 'overdue' : ($entry['on_day'] ? 'on_day' : 'lead'),
                    'lead' => $entry['leads'] === [] ? 0 : min($entry['leads']),
                ],
            ])
            ->all());
    }

    private function chunkSize(): int
    {
        return (int) config('calendar.sync_chunk', 500);
    }
}
