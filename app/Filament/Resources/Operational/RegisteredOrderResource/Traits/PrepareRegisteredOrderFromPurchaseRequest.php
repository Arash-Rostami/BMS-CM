<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use App\Models\PurchaseRequest;
use App\Services\CodeGenerator;

trait PrepareRegisteredOrderFromPurchaseRequest
{
    public function afterFillFromPurchaseRequest(): void
    {
        if (request()->has('purchase_request_id')) {
            $purchaseRequestId = request()->query('purchase_request_id');
            $purchaseRequest = PurchaseRequest::with(['items.product.specifications'])->find($purchaseRequestId);

            if ($purchaseRequest) {
                $items = $purchaseRequest->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity ?? 0,
                    'unit' => $item->unit ?? null,
                    'unit_price' => $item->estimated_cost ?? 0,
                    'line_total' => ($item->quantity ?? 0) * ($item->estimated_cost ?? 0),
                ])->toArray();

                $this->form->fill([
                    'source_type' => 'pr',
                    'purchaseRequests' => [$purchaseRequestId],
                    'ro_number' => CodeGenerator::generate('ro_number'),
                    'contract_no' => CodeGenerator::generate('contract_no'),
                    'order_date' => now()->toDateString(),
                    'validity_date' => now()->addWeek()->toDateString(),
                    'items' => $items,
                ]);
            }
        }
    }
}
