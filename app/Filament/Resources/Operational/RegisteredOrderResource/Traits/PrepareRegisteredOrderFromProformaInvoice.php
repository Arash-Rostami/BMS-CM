<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use App\Models\ProformaInvoice;
use App\Services\CodeGenerator;

trait PrepareRegisteredOrderFromProformaInvoice
{
    public function afterFillFromProformaInvoice(): void
    {
        if (request()->has('proforma_invoice_id')) {
            $proformaInvoiceId = request()->query('proforma_invoice_id');
            $proformaInvoice = ProformaInvoice::with(['items.product.specifications'])->find($proformaInvoiceId);
            if ($proformaInvoice) {
                $items = $proformaInvoice->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity ?? 0,
                    'unit' => $item->unit ?? null,
                    'unit_price' => $item->unit_price ?? 0,
                    'net_weight' => $item->net_weight ?? 0,
                    'gross_weight' => $item->gross_weight ?? 0,
                    'shipping_cost' => $item->freight_charges ?? 0,
                    'line_total' => (($item->quantity ?? 0) * ($item->unit_price ?? 0)) + $item->freight_charges,
                ])->toArray();

                $this->form->fill([
                    'source_type' => 'pi',
                    'proformaInvoices' => [$proformaInvoiceId],
                    'ro_number' => CodeGenerator::generate('ro_number'),
                    'contract_no' => CodeGenerator::generate('contract_no'),
                    'order_date' => now()->toDateString(),
                    'validity_date' => now()->addWeek()->toDateString(),
                    'buyer_id' => $proformaInvoice->buyer_id,
                    'seller_id' => $proformaInvoice->seller_id,
                    'currency_id' => $proformaInvoice->main_currency_id,
                    'incoterms' => $proformaInvoice->delivery_terms,
                    'items' => $items,
                ]);
            }
        }
    }
}
