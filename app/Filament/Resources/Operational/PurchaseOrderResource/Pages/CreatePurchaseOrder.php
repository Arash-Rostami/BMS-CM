<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\PreparesPurchaseOrderFromProforma;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\PreparesPurchaseOrderFromPurchaseRequest;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\PreparesPurchaseOrderFromRegisteredOrder;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Notifications\Notification;

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

        $this->warnIfRecentDuplicateExists($data['seller_id'] ?? null, $data['buyer_id'] ?? null);

        return $data;
    }

    protected function warnIfRecentDuplicateExists(?int $sellerId, ?int $buyerId): void
    {
        if (! $sellerId || ! $buyerId) {
            return;
        }

        $exists = PurchaseOrder::where('seller_id', $sellerId)
            ->where('buyer_id', $buyerId)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if (! $exists) {
            return;
        }

        Notification::make()
            ->title(__('resources/purchaseOrder/strings.notifications.duplicate_title'))
            ->body(__('resources/purchaseOrder/strings.notifications.duplicate_body'))
            ->warning()
            ->send();
    }
}
