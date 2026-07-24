<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use App\Models\PurchaseOrder;
use App\Services\CodeGenerator;

trait PrepareRegisteredOrderFromPurchaseOrder
{
    public function afterFillFromPurchaseOrder(): void
    {
        if (request()->has('purchase_order_id')) {
            $purchaseOrderId = request()->query('purchase_order_id');
            $purchaseOrder = PurchaseOrder::with(['items.product.specifications'])->find($purchaseOrderId);
            if ($purchaseOrder) {
                $items = $purchaseOrder->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity ?? 0,
                    'unit' => $item->unit ?? null,
                    'unit_price' => $item->unit_price ?? 0,
                    'net_weight' => $item->net_weight ?? 0,
                    'gross_weight' => $item->gross_weight ?? 0,
                    'line_total' => (($item->quantity ?? 0) * ($item->unit_price ?? 0)),
                ])->toArray();

                $this->form->fill([
                    'source_type' => 'po',
                    'purchaseOrders' => [$purchaseOrderId],
                    'ro_number' => CodeGenerator::generate('ro_number'),
                    'contract_no' => CodeGenerator::generate('contract_no'),
                    'order_date' => now()->toDateString(),
                    'validity_date' => now()->addWeek()->toDateString(),
                    'buyer_id' => $purchaseOrder->buyer_id,
                    'seller_id' => $purchaseOrder->seller_id,
                    'currency_id' => $purchaseOrder->currency_id,
                    'incoterms' => $purchaseOrder->incoterms,
                    'items' => $items,
                ]);
            }
        }
    }
}
