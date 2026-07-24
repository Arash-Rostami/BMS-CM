<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\PrepareRegisteredOrderFromProformaInvoice;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\PrepareRegisteredOrderFromPurchaseOrder;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\PrepareRegisteredOrderFromPurchaseRequest;
use App\Filament\Resources\RegisteredOrderResource;

class CreateRegisteredOrder extends CreateRecord
{
    use PrepareRegisteredOrderFromProformaInvoice;
    use PrepareRegisteredOrderFromPurchaseOrder;
    use PrepareRegisteredOrderFromPurchaseRequest;

    protected static string $resource = RegisteredOrderResource::class;

    public function afterFill(): void
    {
        if (request()->has('proforma_invoice_id')) {
            self::afterFillFromProformaInvoice();
        }

        if (request()->has('purchase_order_id')) {
            self::afterFillFromPurchaseOrder();
        }

        if (request()->has('purchase_request_id')) {
            self::afterFillFromPurchaseRequest();
        }
    }
}
