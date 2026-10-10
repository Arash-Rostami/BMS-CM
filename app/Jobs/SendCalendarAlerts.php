<?php

namespace App\Jobs;

use App\Services\Calendar\CalendarAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCalendarAlerts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue(config('calendar.queue'));
    }

    public function handle(CalendarAlerts $alerts): void
    {
        $alerts->sendDue(today());
    }
}
