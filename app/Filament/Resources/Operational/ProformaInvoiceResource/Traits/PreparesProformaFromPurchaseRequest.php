<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use App\Models\PurchaseRequest;
use App\Services\CodeGenerator;

trait PreparesProformaFromPurchaseRequest
{
    public function afterFillFromPurchaseRequest(): void
    {
        if (request()->has('purchase_request_id')) {
            $purchaseRequestId = request()->query('purchase_request_id');
            $purchaseRequest = PurchaseRequest::with(['items.product.specifications'])->find($purchaseRequestId);

            if ($purchaseRequest) {
                $items = $purchaseRequest->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity ?? 0,
                    'unit' => $item->unit ?? null,
                    'unit_price' => $item->estimated_cost ?? 0,
                    'hs_code' => $item->product?->specifications?->first()?->hs_code,
                    'total_amount' => ($item->quantity ?? 0) * ($item->estimated_cost ?? 0),
                ])->toArray();

                $this->form->fill([
                    'source_type' => 'pr',
                    'purchaseRequests' => [$purchaseRequestId],
                    'invoice_no' => CodeGenerator::generate('invoice_no'),
                    'invoice_date' => now()->toDateString(),
                    'validity_date' => now()->addWeek()->toDateString(),
                    'items' => $items,
                ]);
            }
        }
    }
}
