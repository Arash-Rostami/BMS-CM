<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Pages;

use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\PreparesPurchaseOrderFromProforma;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\PreparesPurchaseOrderFromPurchaseRequest;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\PreparesPurchaseOrderFromRegisteredOrder;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseOrder extends CreateRecord
{
    use PreparesPurchaseOrderFromPurchaseRequest;
    use PreparesPurchaseOrderFromRegisteredOrder;
    use PreparesPurchaseOrderFromProforma;

    protected static string $resource = PurchaseOrderResource::class;

    public function afterFill(): void
    {
        if (request()->has('purchase_request_id')) {
            self::afterFillFromPurchaseRequest();
        }

        if (request()->has('registered_order_id')) {
            self::afterFillFromRegisteredOrder();
        }

        if (request()->has('proforma_invoice_id')) {
            self::afterFillFromProformaInvoice();
        }
    }
}
