<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use App\Models\PurchaseRequest;
use App\Services\CodeGenerator;

trait PreparesPurchaseOrderFromPurchaseRequest
{
    public function afterFillFromPurchaseRequest(): void
    {
        if (request()->has('purchase_request_id')) {
            $purchaseRequestId = request()->query('purchase_request_id');
            $purchaseRequest = PurchaseRequest::with(['items.product'])->find($purchaseRequestId);

            if ($purchaseRequest) {
                $items = $purchaseRequest->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity ?? 0,
                    'unit' => $item->unit ?? null,
                    'unit_price' => $item->estimated_cost ?? 0,
                    'total_amount' => ($item->quantity ?? 0) * ($item->estimated_cost ?? 0),
                ])->toArray();


                $this->form->fill([
                    'purchaseRequests' => [$purchaseRequestId],
                    'po_number' => CodeGenerator::generate('po_number'),
                    'order_date' => now()->toDateString(),
                    'validity_date' => now()->addWeek()->toDateString(),
                    'items' => $items,
                ]);
            }
        }
    }
}
