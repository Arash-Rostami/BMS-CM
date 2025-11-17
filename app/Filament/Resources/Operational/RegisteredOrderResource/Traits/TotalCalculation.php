<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

trait TotalCalculation
{

    protected static function updateTotal(Get $get, Set $set): void
    {
        $items = collect($get('items') ?? []);

        $totalQuantity = $items->sum(function ($item) {
            return isset($item['quantity']) && is_numeric($item['quantity']) ? (float)$item['quantity'] : 0.0;
        });

        $totalAmount = $items->reduce(function ($carry, $item) {

            $quantity = isset($item['quantity']) && is_numeric($item['quantity']) ? (float)$item['quantity'] : 0.0;
            $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price']) ? (float)$item['unit_price'] : 0.0;
            $shipping = isset($item['shipping_cost']) && is_numeric($item['shipping_cost']) ? (float)$item['shipping_cost'] : 0.0;
            $extra = isset($item['extra_cost']) && is_numeric($item['extra_cost']) ? (float)$item['extra_cost'] : 0.0;

            return $carry + (($quantity * $unitPrice) + $shipping + $extra);
        }, 0.0);


        $set('total_quantity', number_format($totalQuantity, 2, '.', ''));
        $set('total_amount', number_format($totalAmount, 2, '.', ''));
    }
}
