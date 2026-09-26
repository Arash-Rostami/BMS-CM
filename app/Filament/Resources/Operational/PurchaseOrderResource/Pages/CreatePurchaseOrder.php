<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\PreparesPurchaseOrderFromProforma;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\PreparesPurchaseOrderFromPurchaseRequest;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\PreparesPurchaseOrderFromRegisteredOrder;
use App\Filament\Resources\PurchaseOrderResource;

class CreatePurchaseOrder extends CreateRecord
{
    use PreparesPurchaseOrderFromProforma;
    use PreparesPurchaseOrderFromPurchaseRequest;
    use PreparesPurchaseOrderFromRegisteredOrder;

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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = PurchaseOrderResource::applyInitialStatusOnCreate($data);

        PurchaseOrderResource::assertStatusTransitionAllowed(null, 'status_id', $data['status_id'] ?? null);

        return $data;
    }
}
