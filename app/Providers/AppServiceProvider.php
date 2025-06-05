<?php

namespace App\Providers;

use App\Configurators\FilamentAssets;
use App\Configurators\LanguageSwitcher;
use App\Models\Category;
use App\Observers\CategoryObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        LanguageSwitcher::configure();
        FilamentAssets::register();
        Category::observe(CategoryObserver::class);
    }
}
