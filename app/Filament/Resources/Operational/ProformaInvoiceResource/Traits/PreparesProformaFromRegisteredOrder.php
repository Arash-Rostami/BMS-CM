<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use App\Models\RegisteredOrder;
use App\Services\CodeGenerator;

trait PreparesProformaFromRegisteredOrder
{
    public function afterFillFromRegisteredOrder(): void
    {
        if (request()->has('registered_order_id')) {
            $registeredOrderId = request()->query('registered_order_id');
            $registeredOrder = RegisteredOrder::with(['items.product.specifications'])->find($registeredOrderId);

            if ($registeredOrder) {
                $items = $registeredOrder->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity ?? 0,
                    'unit' => $item->unit ?? null,
                    'unit_price' => $item->unit_price ?? 0,
                    'hs_code' => $item->product?->specifications?->first()?->hs_code,
                    'net_weight' => $item->net_weight ?? null,
                    'gross_weight' => $item->gross_weight ?? null,
                    'freight_charges' => ($item->shipping_cost ?? 0),
                    'total_amount' => number_format(
                        (($item->quantity ?? 0) * ($item->unit_price ?? 0)) + ($item->entrance_fee ?? 0) + ($item->shipping_cost ?? 0) + ($item->extra_cost ?? 0),
                        5,
                        '.',
                        ''
                    ),
                ])->toArray();

                $this->form->fill([
                    'source_type' => 'ro',
                    'registeredOrders' => [$registeredOrderId],
                    'contract_no' => $registeredOrder->contract_no ?? null,
                    'seller_id' => $registeredOrder->seller_id ?? null,
                    'buyer_id' => $registeredOrder->buyer_id ?? null,
                    'main_currency_id' => $registeredOrder->currency_id ?? null,
                    'invoice_no' => CodeGenerator::generate('invoice_no'),
                    'invoice_date' => now()->toDateString(),
                    'items' => $items,
                ]);
            }
        }
    }
}
