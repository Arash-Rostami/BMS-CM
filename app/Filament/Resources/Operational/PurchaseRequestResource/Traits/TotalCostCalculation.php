<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Traits;

use Filament\Forms\{Get, Set};


trait TotalCostCalculation
{
    public static function updateTotalCost(Get $get, Set $set): void
    {
        $total = collect($get('items') ?? [])
            ->sum(fn($i) => ((float)$i['quantity'] ?? 0) * ((float)$i['estimated_cost'] ?? 0));

        $set('total_estimated_cost', number_format($total, 2, '.', ''));
    }
}
