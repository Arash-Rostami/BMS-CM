<?php

namespace App\Jobs;

use App\Models\CalendarRule;
use App\Services\Calendar\Sync\CalendarEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCalendarRule implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 300;

    public int $maxExceptions = 3;

    public function __construct(public int $ruleId)
    {
        $this->onQueue(config('calendar.queue'));
    }

    public function handle(CalendarEngine $engine): void
    {
        $rule = CalendarRule::withTrashed()->find($this->ruleId);

        if ($rule === null) {
            return;
        }

        if (! $engine->syncRule($rule)) {
            $this->release(60);
        }
    }

    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(30);
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function uniqueId(): string
    {
        return (string) $this->ruleId;
    }
}
