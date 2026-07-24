<?php

namespace App\Providers;

use App\Configurators\FilamentAssets;
use App\Configurators\FilamentCustomLogin;
use App\Configurators\FilamentRenderHooks;
use App\Configurators\LanguageSwitcher;
use App\Models\Attachment;
use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Custom;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Observers\AttachmentObserver;
use App\Observers\CategoryObserver;
use App\Observers\CodeGeneratingObserver;
use App\Observers\PurchaseRequestObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    private const CODE_GENERATED_MODELS = [
        PurchaseRequest::class,
        ProformaInvoice::class,
        RegisteredOrder::class,
        PurchaseOrder::class,
        BankProfile::class,
        Payment::class,
        Shipment::class,
        Custom::class,
    ];

    public function boot(): void
    {
        $this->configureFilament();
        $this->registerObservers();
    }

    public function register(): void {}

    private function configureFilament(): void
    {
        FilamentCustomLogin::configure($this->app);
        LanguageSwitcher::configure();
        FilamentAssets::register();
        FilamentRenderHooks::configure();
    }

    private function registerObservers(): void
    {
        Attachment::observe(AttachmentObserver::class);
        Category::observe(CategoryObserver::class);
        PurchaseRequest::observe(PurchaseRequestObserver::class);

        foreach (self::CODE_GENERATED_MODELS as $model) {
            $model::observe(CodeGeneratingObserver::class);
        }
    }
}
