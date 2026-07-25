<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use App\Models\PurchaseOrder;
use App\Services\CodeGenerator;

trait PreparesProformaFromPurchaseOrder
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
                    'hs_code' => $item->product?->specifications?->first()?->hs_code,
                    'total_amount' => number_format(
                        ($item->quantity ?? 0) * ($item->unit_price ?? 0),
                        5,
                        '.',
                        ''
                    ),
                ])->toArray();

                $this->form->fill([
                    'source_type' => 'po',
                    'purchaseOrders' => [$purchaseOrderId],
                    'po_number' => CodeGenerator::generate('po_number'),
                    'invoice_no' => CodeGenerator::generate('invoice_no'),
                    'invoice_date' => now()->toDateString(),
                    'items' => $items,
                ]);
            }
        }
    }
}
