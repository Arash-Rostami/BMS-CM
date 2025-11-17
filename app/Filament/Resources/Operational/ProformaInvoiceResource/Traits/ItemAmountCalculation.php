<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Traits;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

trait ItemAmountCalculation
{
    public static function itemAfterStateUpdated(Get $get, Set $set)
    {
        $quantity = floatval($get('quantity') ?? 0);
        $unitPrice = floatval($get('unit_price') ?? 0);
        $freight = floatval($get('freight_charges') ?? 0);
        $set('total_amount', number_format(($quantity * $unitPrice) + $freight, 2, '.', ''));
    }
}
