<?php

namespace App\Providers;

use App\Configurators\FilamentAssets;
use App\Configurators\FilamentCustomLogin;
use App\Configurators\FilamentExportDefaults;
use App\Configurators\FilamentRenderHooks;
use App\Configurators\FilamentTableDefaults;
use App\Configurators\FilamentViewActionDefaults;
use App\Configurators\LanguageSwitcher;
use App\Models\Attachment;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Company;
use App\Models\Correspondence;
use App\Models\Currency;
use App\Models\Custom;
use App\Models\EntityAttribute;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\Target;
use App\Models\User;
use App\Observers\AttachmentObserver;
use App\Observers\CategoryObserver;
use App\Observers\CodeGeneratingObserver;
use App\Observers\EntityAttributeObserver;
use App\Observers\PurchaseRequestObserver;
use App\Observers\StatusObserver;
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

        Attachment::class,
        Bank::class,
        BankProfile::class,
        Category::class,
        Company::class,
        Correspondence::class,
        Currency::class,
        Custom::class,
        Payment::class,
        Permission::class,
        Product::class,
        ProformaInvoice::class,
        PurchaseOrder::class,
        PurchaseRequest::class,
        RegisteredOrder::class,
        Role::class,
        Shipment::class,
        Status::class,
        Target::class,
        User::class,
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
        FilamentTableDefaults::configure();
        FilamentExportDefaults::configure();
        FilamentViewActionDefaults::configure();
    }

    private function registerObservers(): void
    {
        Attachment::observe(AttachmentObserver::class);
        Category::observe(CategoryObserver::class);
        PurchaseRequest::observe(PurchaseRequestObserver::class);
        Status::observe(StatusObserver::class);
        EntityAttribute::observe(EntityAttributeObserver::class);

        foreach (self::CODE_GENERATED_MODELS as $model) {
            $model::observe(CodeGeneratingObserver::class);
        }
    }
}
