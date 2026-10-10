<?php

namespace App\Observers;

use App\Models\Attachment;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Company;
use App\Models\Correspondence;
use App\Models\CorrespondenceRecipient;
use App\Models\Currency;
use App\Models\Custom;
use App\Models\Department;
use App\Models\DeskReference;
use App\Models\EntityAttribute;
use App\Models\NotificationSetting;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\ProformaInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Models\Role;
use App\Models\Shipment;
use App\Models\Specification;
use App\Models\Status;
use App\Models\StatusHistory;
use App\Models\Target;
use App\Models\User;
use App\Services\Calendar\Sync\CalendarRouter;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CalendarTouchObserver
{
    public const OBSERVED = [
        Attachment::class,
        Bank::class,
        BankProfile::class,
        Category::class,
        Company::class,
        Correspondence::class,
        CorrespondenceRecipient::class,
        Currency::class,
        Custom::class,
        Department::class,
        DeskReference::class,
        EntityAttribute::class,
        NotificationSetting::class,
        Payment::class,
        Permission::class,
        Product::class,
        ProformaInvoice::class,
        ProformaInvoiceItem::class,
        PurchaseOrder::class,
        PurchaseOrderItem::class,
        PurchaseRequest::class,
        PurchaseRequestItem::class,
        RegisteredOrder::class,
        RegisteredOrderItem::class,
        Role::class,
        Shipment::class,
        Specification::class,
        Status::class,
        StatusHistory::class,
        Target::class,
        User::class,
    ];

    public function __construct(private CalendarRouter $router) {}

    public function created(Model $model): void
    {
        $this->touch($model, 'created');
    }

    public function saved(Model $model): void
    {
        $this->touch($model, 'saved');
    }

    public function deleted(Model $model): void
    {
        $this->touch($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->touch($model, 'restored');
    }

    public function forceDeleted(Model $model): void
    {
        $this->touch($model, 'forceDeleted');
    }

    private function touch(Model $model, string $event): void
    {
        try {
            $this->router->touch($model, $event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
