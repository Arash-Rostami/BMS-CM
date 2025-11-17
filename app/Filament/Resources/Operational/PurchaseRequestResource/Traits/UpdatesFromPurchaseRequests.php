<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use Filament\Schemas\Components\Utilities\Set;
use App\Models\PurchaseRequest;

trait UpdatesFromPurchaseRequests
{
    public static function populateFromPR(mixed $state, Set $set): void
    {
        if (empty($state)) {
            $set('items', []);
            return;
        }

        $purchaseRequests = PurchaseRequest::whereIn('id', (array)$state)
            ->with(['items.product.specifications'])
            ->get();

        $items = $purchaseRequests->flatMap(fn($pr) => $pr->items->map(fn($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity ?? 0,
            'unit' => $item->unit ?? null,
            'unit_price' => $item->estimated_cost ?? 0,
            'hs_code' => $item->product?->specifications?->first()?->hs_code,
            'total_amount' => number_format(
                ($item->quantity ?? 0) * ($item->estimated_cost ?? 0),
                2,
                '.',
                ''
            ),
            'line_total' => number_format(
                ($item->quantity ?? 0) * ($item->estimated_cost ?? 0),
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
