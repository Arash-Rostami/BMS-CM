<?php

namespace App\Notifications;

use App\Models\CalendarRule;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CalendarRuleInvalidNotification extends Notification
{
    use Queueable;

    /**
     * @param  array<int, string>  $problems
     */
    public function __construct(
        public CalendarRule $rule,
        public array $problems,
    ) {}

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'title' => $this->payloadText('title', ['rule' => $this->rule->name]),
            'body' => $this->payloadText('body', ['problems' => implode(', ', $this->problems)]),
            'actions' => [
                [
                    'name' => 'open',
                    'key' => 'resources/calendarRule/strings.invalid.action_open',
                    'url' => route('filament.dashboard.resources.calendar-rules.index', ['tableAction' => 'edit', 'tableActionRecord' => $this->rule->id]),
                    'shouldMarkAsRead' => true,
                ],
            ],
            'icon' => 'heroicon-o-exclamation-triangle',
            'iconColor' => 'danger',
            'format' => 'filament',
            'duration' => 'persistent',
            'rule_id' => $this->rule->id,
            'rule_name' => $this->rule->name,
            'problems' => $this->problems,
        ];
    }

    /**
     * @return array{key: string, params: array<string, mixed>}
     */
    private function payloadText(string $suffix, array $params): array
    {
        return [
            'key' => 'resources/calendarRule/strings.invalid.'.$suffix,
            'params' => $params,
        ];
    }
}
