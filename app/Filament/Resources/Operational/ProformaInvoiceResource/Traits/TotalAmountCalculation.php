<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

trait TotalAmountCalculation
{
    public static function updateTotalAmount(Get $get, Set $set): void
    {
        $items = collect($get('items') ?? []);

        $itemsTotal = $items->sum(fn ($i) => floatval($i['quantity'] ?? 0) * floatval($i['unit_price'] ?? 0));
        $itemsFreight = $items->sum(fn ($i) => floatval($i['quantity'] ?? 0) * floatval($i['freight_charges'] ?? 0)); // ← × quantity

        $set('freight_charges', number_format($itemsFreight, 5, '.', ''));   // header total freight
        $set('total_amount', number_format(
            $itemsTotal
            - (float) ($get('discount') ?? 0)
            + $itemsFreight
            + (float) ($get('other_charges') ?? 0),
            5, '.', ''
        ));
    }
}
