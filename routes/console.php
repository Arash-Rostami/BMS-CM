<?php

use App\Jobs\RebuildCalendarHits;
use App\Jobs\SendCalendarAlerts;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new SendCalendarAlerts)
    ->dailyAt(config('calendar.alert_time'))
    ->withoutOverlapping(60);

Schedule::job(new RebuildCalendarHits)
    ->dailyAt(config('calendar.rebuild_time'))
    ->withoutOverlapping(60);
