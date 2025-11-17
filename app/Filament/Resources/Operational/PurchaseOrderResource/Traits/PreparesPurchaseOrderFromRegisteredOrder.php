<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use App\Models\RegisteredOrder;
use App\Services\CodeGenerator;

trait PreparesPurchaseOrderFromRegisteredOrder
{
    public function afterFillFromRegisteredOrder(): void
    {
        if (request()->has('registered_order_id')) {
            $registeredOrderId = request()->query('registered_order_id');
            $registeredOrder = RegisteredOrder::with(['items.product.specifications'])->find($registeredOrderId);

            if ($registeredOrder) {
                $items = $registeredOrder->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity ?? 0,
                    'unit' => $item->unit ?? null,
                    'unit_price' => $item->unit_price ?? 0,
                    'net_weight' => $item->net_weight ?? null,
                    'gross_weight' => $item->gross_weight ?? null,
                ])->toArray();


                $this->form->fill([
                    'source_type' => 'ro',
                    'registeredOrders' => [$registeredOrderId],
                    'incoterms' => $registeredOrder->incoterms ?? null,
                    'seller_id' => $registeredOrder->seller_id ?? null,
                    'buyer_id' => $registeredOrder->buyer_id ?? null,
                    'currency_id' => $registeredOrder->currency_id ?? null,
                    'po_number' => CodeGenerator::generate('po_number'),
                    'order_date' => now()->toDateString(),
                    'items' => $items,
                ]);
            }
        }
    }
}
