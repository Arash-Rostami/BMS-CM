<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use App\Models\RegisteredOrder;
use Filament\Schemas\Components\Utilities\Set;

trait UpdatesFromRegisteredOrders
{
    public static function populateFromRO(mixed $state, Set $set): void
    {
        if (empty($state)) {
            $set('items', []);

            return;
        }

        $registeredOrders = RegisteredOrder::whereIn('id', (array) $state)
            ->with(['items.product.specifications'])
            ->get();

        $items = $registeredOrders->flatMap(fn ($ro) => $ro->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'quantity' => $item->quantity ?? 0,
            'unit' => $item->unit ?? null,
            'unit_price' => $item->unit_price ?? 0,
            'net_weight' => $item->net_weight ?? null,
            'gross_weight' => $item->gross_weight ?? null,
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
            'show_notes' => true,
        ]))->toArray();

        $set('items', $items);

        if ($registeredOrders->count() === 1) {
            $ro = $registeredOrders->first();

            $set('source_type', 'ro');
            $set('registeredOrders', [(int) $ro->id]);
            $set('incoterms', $ro->incoterms ?? null);
            $set('seller_id', $ro->seller_id ?? null);
            $set('buyer_id', $ro->buyer_id ?? null);
            $set('currency_id', $ro->currency_id ?? null);
            $set('main_currency_id', $ro->currency_id ?? null);
        }
    }
}
