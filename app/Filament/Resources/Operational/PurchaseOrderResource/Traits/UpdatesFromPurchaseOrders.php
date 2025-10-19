<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use Filament\Schemas\Components\Utilities\Set;
use App\Models\PurchaseOrder;

trait UpdatesFromPurchaseOrders
{
    public static function populateFromPO(mixed $state, Set $set): void
    {
        if (empty($state)) {
            $set('items', []);
            return;
        }

        $purchaseOrders = PurchaseOrder::whereIn('id', (array)$state)
            ->with(['items.product.specifications'])
            ->get();

        $items = $purchaseOrders->flatMap(fn($pr) => $pr->items->map(fn($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity ?? 0,
            'unit' => $item->unit ?? null,
            'unit_price' => $item->unit_price ?? 0,
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
