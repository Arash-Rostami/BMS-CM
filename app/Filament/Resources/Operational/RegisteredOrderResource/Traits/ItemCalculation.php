<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Traits;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

trait ItemCalculation
{
    protected static function computeItemLineTotalFromState(array $item): float
    {
        $quantity = isset($item['quantity']) && is_numeric($item['quantity']) ? (float)$item['quantity'] : 0.0;
        $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price']) ? (float)$item['unit_price'] : 0.0;
        $shipping = isset($item['shipping_cost']) && is_numeric($item['shipping_cost']) ? (float)$item['shipping_cost'] : 0.0;
        $extra = isset($item['extra_cost']) && is_numeric($item['extra_cost']) ? (float)$item['extra_cost'] : 0.0;

        return round(($quantity * $unitPrice) + $shipping + $extra, 2);
    }

    protected static function recalcAllItems(Get $get, Set $set): void
    {
        $items = $get('items') ?? [];

        foreach ($items as $index => $item) {
            $computed = static::computeItemLineTotalFromState(is_array($item) ? $item : []);
            $set("items.{$index}.line_total", number_format($computed, 2, '.', ''));
        }

        static::updateTotal($get, $set);
    }

    protected static function updateItemLineTotal(Get $get, Set $set): void
    {
        $quantity = is_numeric($get('quantity')) ? (float)$get('quantity') : 0.0;
        $unitPrice = is_numeric($get('unit_price')) ? (float)$get('unit_price') : 0.0;
        $shipping = is_numeric($get('shipping_cost')) ? (float)$get('shipping_cost') : 0.0;
        $extra = is_numeric($get('extra_cost')) ? (float)$get('extra_cost') : 0.0;

        $lineTotal = ($quantity * $unitPrice) + $shipping + $extra;

        $set('line_total', number_format($lineTotal, 2, '.', ''));

        static::updateTotal($get, $set);
    }
}
