<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Traits;

use Filament\Schemas\Components\Utilities\Set;

trait TotalCalculation
{
    public static function updateTotal($get, Set $set): void
    {
        $items = $get('items') ?? [];

        $totalQuantity = collect($items)->sum(function ($item) {
            return is_numeric($item['quantity'] ?? 0) ? (float) $item['quantity'] : 0;
        });

        $totalAmount = collect($items)->sum(function ($item) {
            $quantity = is_numeric($item['quantity'] ?? 0) ? (float) $item['quantity'] : 0;
            $price = is_numeric($item['unit_price'] ?? 0) ? (float) $item['unit_price'] : 0;

            return $quantity * $price;
        });

        $set('total_quantity', number_format($totalQuantity, 5, '.', ''));
        $set('total_amount', number_format($totalAmount, 5, '.', ''));
    }
}
