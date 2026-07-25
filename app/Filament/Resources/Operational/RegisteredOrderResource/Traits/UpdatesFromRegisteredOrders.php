<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use App\Models\RegisteredOrder;
use App\Services\CodeGenerator;
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
            'hs_code' => $item->product?->specifications?->first()?->hs_code ?? null,
            'net_weight' => $item->net_weight ?? null,
            'gross_weight' => $item->gross_weight ?? null,
            'show_notes' => true,
            'description' => $item->description ?? null,
            'freight_charges' => $item->shipping_cost ?? 0,
            'entrance_fee' => $item->entrance_fee ?? 0,
            'extra_cost' => $item->extra_cost ?? 0,
            'total_amount' => number_format(
                (
                    ($item->quantity ?? 0) * ($item->unit_price ?? 0)
                )
                + ($item->entrance_fee ?? 0)
                + ($item->shipping_cost ?? 0)
                + ($item->extra_cost ?? 0),
                5,
                '.',
                ''
            ),
            'line_total' => number_format(
                (
                    ($item->quantity ?? 0) * ($item->unit_price ?? 0)
                )
                + ($item->entrance_fee ?? 0)
                + ($item->shipping_cost ?? 0)
                + ($item->extra_cost ?? 0),
                5,
                '.',
                ''
            ),
        ]))->toArray();

        $set('items', $items);

        if ($registeredOrders->count() === 1) {
            $ro = $registeredOrders->first();

            $set('source_type', 'ro');
            $set('registeredOrders', [(int) $ro->id]);
            $set('contract_no', $ro->contract_no ?? null);
            $set('seller_id', $ro->seller_id ?? null);
            $set('buyer_id', $ro->buyer_id ?? null);
            $set('main_currency_id', $ro->currency_id ?? null);
            $set('invoice_no', CodeGenerator::generate('invoice_no'));
            $set('invoice_date', now()->toDateString());
        }
    }
}
