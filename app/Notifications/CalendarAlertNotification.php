<?php

namespace App\Notifications;

use App\Models\CalendarRule;
use App\Services\Calendar\CalendarModules;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CalendarAlertNotification extends Notification
{
    use Queueable;

    /**
     * @param  Collection<int, array{hit: \App\Models\CalendarHit, leads: array<int, int>, on_day: bool, overdue: bool}>  $entries
     */
    public function __construct(
        public CalendarRule $rule,
        public Collection $entries,
        public string $alertDate,
    ) {}

    public function via(mixed $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        return match ($this->rule->notification_type) {
            CalendarRule::NOTIFICATION_CHANNEL_EMAIL => ['mail'],
            CalendarRule::NOTIFICATION_CHANNEL_ALL => ['database', 'mail'],
            CalendarRule::NOTIFICATION_CHANNEL_IN_APP => ['database'],
            default => (array) config('calendar.channels'),
        };
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $count = $this->entries->count();
        $params = ['rule' => $this->rule->name, 'count' => $count];
        $outsider = $notifiable instanceof AnonymousNotifiable;
        $mail = (new MailMessage)
            ->subject($this->mailLine('subject', $params))
            ->greeting($outsider ? $this->mailLine('greeting_anonymous') : $this->mailLine('greeting', ['name' => $notifiable->name]))
            ->line($this->mailLine('intro', $params));

        foreach ($this->entries->take(3) as $entry) {
            $mail->line($this->mailLine('item', [
                'label' => $entry['hit']->label,
                'kind' => __('resources/calendarRule/strings.alerts.kinds.'.$this->kindOf($entry)),
            ]));
        }

        if ($count > 3) {
            $mail->line($this->mailLine('more', ['more' => $count - 3]));
        }

        if (! $outsider && ($path = $this->openPath()) !== null) {
            $mail->action($this->mailLine('action'), rtrim((string) config('app.url'), '/').$path);
        }

        return $mail->line($this->mailLine($outsider ? 'outro_anonymous' : 'outro'));
    }

    public function toDatabase(mixed $notifiable): array
    {
        $items = $this->payloadItems();
        $kinds = array_values(array_unique(array_column($items, 'kind')));

        return [
            'title' => $this->payloadText('title', ['rule' => $this->rule->name, 'count' => count($items)]),
            'body' => $this->payloadBody($items),
            'items' => $items,
            'kinds' => $kinds,
            'actions' => $this->payloadActions(),
            'icon' => in_array('overdue', $kinds, true) ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-calendar-days',
            'iconColor' => $this->payloadColor($kinds),
            'format' => 'filament',
            'duration' => 'persistent',
            'rule_id' => $this->rule->id,
            'rule_name' => $this->rule->name,
            'alert_date' => $this->alertDate,
            'hit_ids' => $this->entries->pluck('hit.id')->all(),
        ];
    }

    /**
     * @return array<int, array{id: int, label: string, kind: string, lead: ?int}>
     */
    private function payloadItems(): array
    {
        return $this->entries->map(fn (array $entry): array => [
            'id' => $entry['hit']->id,
            'label' => Str::limit($entry['hit']->label, 255, ''),
            'kind' => $this->kindOf($entry),
            'lead' => $entry['leads'] === [] ? null : min($entry['leads']),
        ])->all();
    }

    /**
     * @param  array<int, array{id: int, label: string, kind: string, lead: ?int}>  $items
     * @return array{key: string, params: array<string, mixed>}
     */
    private function payloadBody(array $items): array
    {
        $leads = $this->entries->filter(fn (array $entry): bool => $entry['leads'] !== [])
            ->flatMap(fn (array $entry): array => $entry['leads']);

        return $this->payloadText('body', [
            'rule' => $this->rule->name,
            'count' => count($items),
            'more' => max(0, count($items) - 3),
            'lead' => $leads->isEmpty() ? 0 : $leads->min(),
        ]);
    }

    /**
     * @return array{key: string, params: array<string, mixed>}
     */
    private function payloadText(string $suffix, array $params): array
    {
        return [
            'key' => 'resources/calendarRule/strings.alerts.'.$suffix,
            'params' => $params,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function payloadActions(): array
    {
        return [
            [
                'name' => 'open',
                'key' => 'resources/calendarRule/strings.alerts.action_open',
                'url' => $this->openUrl(),
                'shouldMarkAsRead' => false,
            ],
            [
                'name' => 'seen',
                'key' => 'resources/calendarRule/strings.alerts.action_seen',
                'shouldMarkAsRead' => true,
            ],
        ];
    }

    /**
     * @param  array<int, string>  $kinds
     */
    private function payloadColor(array $kinds): string
    {
        return match (true) {
            in_array('overdue', $kinds, true) => 'danger',
            in_array('on_day', $kinds, true) => 'warning',
            default => 'info',
        };
    }

    private function kindOf(array $entry): string
    {
        return match (true) {
            $entry['overdue'] => 'overdue',
            $entry['on_day'] => 'on_day',
            default => 'lead',
        };
    }

    private function mailLine(string $suffix, array $params = []): string
    {
        return __('resources/calendarRule/strings.mail.'.$suffix, $params);
    }

    private function openPath(): ?string
    {
        $parts = parse_url((string) $this->openUrl());

        return isset($parts['path']) ? $parts['path'].(isset($parts['query']) ? '?'.$parts['query'] : '') : null;
    }

    private function openUrl(): ?string
    {
        if ($this->entries->count() === 1 && ($subject = $this->entries->first()['hit']->subject) !== null) {
            return CalendarModules::url($subject);
        }

        $date = $this->entries->first()['hit']->event_date?->toDateString();

        return $date === null
            ? null
            : route('filament.dashboard.pages.dashboard', ['cal_date' => $date, 'cal_rule' => $this->rule->id]);
    }
}
