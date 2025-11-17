<?php

namespace App\Providers;

use App\Configurators\FilamentAssets;
use App\Configurators\FilamentCustomLogin;
use App\Configurators\LanguageSwitcher;
use App\Models\Category;
use App\Models\PurchaseRequest;
use App\Observers\CategoryObserver;
use App\Observers\PurchaseRequestObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        FilamentCustomLogin::configure($this->app);
        LanguageSwitcher::configure();
        FilamentAssets::register();
        Category::observe(CategoryObserver::class);
        PurchaseRequest::observe(PurchaseRequestObserver::class);
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
    }
}
