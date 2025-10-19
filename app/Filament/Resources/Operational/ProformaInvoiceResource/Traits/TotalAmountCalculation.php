<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use Filament\Forms\{Get, Set};

trait TotalAmountCalculation
{
    public static function updateTotalAmount(\Filament\Schemas\Components\Utilities\Get $get, \Filament\Schemas\Components\Utilities\Set $set): void
    {
        $total = collect($get('items') ?? [])
            ->sum(fn($i) => floatval($i['quantity'] ?? 0) * floatval($i['unit_price'] ?? 0));

        $set('total_cfr_amount', number_format(
            $total
            - (float)($get('discount') ?? 0)
            + (float)($get('freight_charges') ?? 0)
            + (float)($get('other_charges') ?? 0),
            2, '.', ''
        ));
    }
}
