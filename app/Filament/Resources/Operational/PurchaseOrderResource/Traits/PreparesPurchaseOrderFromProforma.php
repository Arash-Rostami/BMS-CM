<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use App\Models\ProformaInvoice;
use App\Services\CodeGenerator;

trait PreparesPurchaseOrderFromProforma
{
    public function afterFillFromProformaInvoice(): void
    {
        if (request()->has('proforma_invoice_id')) {
            $proformaInvoiceId = request()->query('proforma_invoice_id');
            $proformaInvoice = ProformaInvoice::with(['items.product.specifications'])->find($proformaInvoiceId);

            if ($proformaInvoice) {
                $items = $proformaInvoice->items->map(fn($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity ?? 0,
                    'unit' => $item->unit ?? null,
                    'unit_price' => $item->unit_price ?? 0,
                    'net_weight' => $item->net_weight ?? null,
                    'gross_weight' => $item->gross_weight ?? null,
                    'total_amount' => number_format(
                        (($item->quantity ?? 0) * ($item->unit_price ?? 0)) + ($item->entrance_fee ?? 0) + ($item->shipping_cost ?? 0) + ($item->extra_cost ?? 0),
                        2,
                        '.',
                        ''
                    )
                ])->toArray();

                $this->form->fill([
                    'source_type' => 'pi',
                    'proformaInvoices' => [$proformaInvoiceId],
                    'seller_id' => $proformaInvoice->seller_id ?? null,
                    'buyer_id' => $proformaInvoice->buyer_id ?? null,
                    'currency_id' => $proformaInvoice->main_currency_id ?? null,
                    'incoterms' => $proformaInvoice-> delivery_terms ?? null,
                    'po_number' => CodeGenerator::generate('po_number'),
                    'order_date' => now()->toDateString(),
                    'items' => $items,
                ]);
            }
        }
    }
}
