<?php

namespace App\Jobs;

use App\Models\CalendarRule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RebuildCalendarHits implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue(config('calendar.queue'));
    }

    public function handle(): void
    {
        CalendarRule::query()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $id) => SyncCalendarRule::dispatch($id));
    }
}
