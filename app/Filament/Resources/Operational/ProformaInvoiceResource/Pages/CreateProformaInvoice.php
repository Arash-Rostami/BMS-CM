<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Pages;

use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\PreparesProformaFromPurchaseOrder;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\PreparesProformaFromPurchaseRequest;
use App\Filament\Resources\ProformaInvoiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProformaInvoice extends CreateRecord
{
    use PreparesProformaFromPurchaseRequest;
    use PreparesProformaFromPurchaseOrder;

    protected static string $resource = ProformaInvoiceResource::class;

    public function afterFill(): void
    {
        if (request()->has('purchase_request_id')) {
            self::afterFillFromPurchaseRequest();
        }

        if (request()->has('purchase_order_id')) {
            self::afterFillFromPurchaseOrder();
        }
    }
}
