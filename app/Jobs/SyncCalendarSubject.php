<?php

namespace App\Jobs;

use App\Services\Calendar\Sync\CalendarEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncCalendarSubject implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 120;

    public int $tries = 3;

    public function __construct(
        public string $class,
        public int $id,
    ) {
        $this->onQueue(config('calendar.queue'));
    }

    public function handle(CalendarEngine $engine): void
    {
        $engine->syncSubject($this->class, $this->id);
    }

    public function backoff(): array
    {
        return [30, 120];
    }

    public function uniqueId(): string
    {
        return $this->class.':'.$this->id;
    }
}
