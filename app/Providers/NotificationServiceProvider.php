<?php

namespace App\Providers;

use App\Models\NotificationSetting;
use App\Observers\NotificationDispatcher;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerScannableModelObservers();
    }

    public function register(): void {}

    private function registerScannableModelObservers(): void
    {
        foreach (NotificationSetting::scannableModels() as $modelClass) {
            $modelClass::observe(NotificationDispatcher::class);
        }
    }
}
