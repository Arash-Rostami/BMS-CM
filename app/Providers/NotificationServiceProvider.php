<?php

namespace App\Providers;

use App\Observers\NotificationDispatcher;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class NotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerScannableModelObservers();
    }

    public function register(): void {}

    private function registerScannableModelObservers(): void
    {
        $modelsPath = app_path('Models');
        $models = File::allFiles($modelsPath);

        foreach ($models as $modelFile) {
            $modelClass = 'App\\Models\\'.Str::studly(pathinfo($modelFile, PATHINFO_FILENAME));

            if (! class_exists($modelClass)) {
                continue;
            }

            if (defined("{$modelClass}::SCANNABLE_TABLE")) {
                $modelClass::observe(NotificationDispatcher::class);
            }
        }
    }
}
