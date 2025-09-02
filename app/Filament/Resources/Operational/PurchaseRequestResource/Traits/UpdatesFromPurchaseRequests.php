<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use App\Models\PurchaseRequest;
use Filament\Forms\Set;

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
        ])
        )->toArray();

        $set('items', $items);
    }
}
