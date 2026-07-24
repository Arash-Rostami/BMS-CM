<?php

namespace App\Filament\Resources\Operational\PaymentResource\Traits;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

trait Calculation
{
    protected static function updateComputations(Get $get, Set $set): void
    {
        $total = static::computeDefaultTotal($get);
        $set('total_amount', $total);

        $calculatedTotal = static::computeCalculatedTotal($get);
        $set('calculated_total_display', $calculatedTotal);

        $total = static::computeTotal($get);
        $set('total_display', $total);

        $ratio = static::computeTotalRatio($calculatedTotal, $total);
        $set('total_ratio_display', $ratio);
    }

    private static function computeCalculatedTotal(Get $get): float
    {
        $payable = static::computeDefaultTotal($get);
        $charges = (float) $get('bank_charges') ?? 0.0;

        return $payable + $charges;
    }

    private static function computeDefaultTotal(Get $get): float
    {
        return (float) $get('payable_amount') ?? 0.0;
    }

    private static function computeTotal(Get $get): float
    {
        return (float) $get('total_amount') ?? 0.0;
    }

    private static function computeTotalRatio(float $calculatedTotal, $total): ?float
    {
        if ($total == 0) {
            return null;
        }

        return $calculatedTotal / $total;
    }
}
