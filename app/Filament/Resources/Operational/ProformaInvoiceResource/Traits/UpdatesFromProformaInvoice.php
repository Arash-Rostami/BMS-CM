<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use App\Models\ProformaInvoice;
use Filament\Schemas\Components\Utilities\Set;

trait UpdatesFromProformaInvoice
{
    public static function populateFromPI(mixed $state, Set $set): void
    {
        if (empty($state)) {
            $set('items', []);
            return;
        }

        $proformaInvoice = ProformaInvoice::whereIn('id', (array)$state)
            ->with(['items.product.specifications'])
            ->get();

        $items = $proformaInvoice->flatMap(fn($pr) => $pr->items->map(fn($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity ?? 0,
            'unit' => $item->unit ?? null,
            'unit_price' => $item->unit_price ?? 0,
            'total_amount' => number_format(
                ($item->quantity ?? 0) * ($item->unit_price ?? 0),
                2,
                '.',
                ''
            ),
            'line_total' => number_format(
                ($item->quantity ?? 0) * ($item->unit_price ?? 0),
                2,
                '.',
                ''
            ),
            'show_notes' => true
        ])
        )->toArray();

        $set('items', $items);
    }
}
