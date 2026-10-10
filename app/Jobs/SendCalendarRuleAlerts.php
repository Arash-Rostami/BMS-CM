<?php

namespace App\Jobs;

use App\Models\CalendarRule;
use App\Services\Calendar\CalendarAlerts;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCalendarRuleAlerts implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public function __construct(
        public int $ruleId,
        public string $day,
    ) {
        $this->onQueue(config('calendar.queue'));
    }

    public function handle(CalendarAlerts $alerts): void
    {
        $rule = CalendarRule::query()->find($this->ruleId);

        if ($rule !== null && $rule->is_active) {
            $alerts->sendForRule($rule, Carbon::parse($this->day));
        }
    }

    public function uniqueId(): string
    {
        return $this->ruleId.':'.$this->day;
    }
}
