<?php

namespace App\Providers;

use App\Services\PersianCalendar;
use Illuminate\Support\ServiceProvider;

class CalendarServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(PersianCalendar::class, function ($app) {
            return new PersianCalendar();
        });
    }

    public function boot()
    {}
}
