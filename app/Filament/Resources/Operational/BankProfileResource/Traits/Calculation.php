<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Traits;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

trait Calculation
{
    protected static function updateComputations(Get $get, Set $set): void
    {
        $finalRate = static::computeFinalRate($get);
        $set('final_rate', number_format($finalRate, 2, '.', ''));
        $set('final_rate_display', number_format($finalRate, 2, '.', ''));

        $conversionRate = static::computeConversionRate($get);
        $set('conversion_rate', number_format($conversionRate, 2, '.', ''));

        $set('commission_amount_purchased', static::computeCommissionAmount($get));
        $set('commission_equivalent', static::computeCommissionEquivalent($get));
        $set('final_equivalent', static::computeFinalEquivalent($get, $finalRate));
        $set('remaining_commitment', static::computeRemaining($get));
        $set('total_rial_remittance', static::computeTotalRialRemittance($get));
        $set('total_requested_remittance', static::computeTotalRequestedRemittance($get));
        $set('total_purchased_remittance', static::computeTotalPurchasedRemittance($get));
    }

    private static function computeCommissionAmount(Get $get): float
    {
        return (float)$get('purchased_equivalent') * ((float)$get('commission_rate') / 100);
    }

    private static function computeCommissionEquivalent(Get $get): float
    {
        $commissionAmount = static::computeCommissionAmount($get);
        $requested = (float)$get('requested_amount');
        $purchased = (float)$get('purchased_equivalent');

        if ($purchased == 0 || $requested == 0) return 0;

        return ($requested / $purchased) * $commissionAmount;
    }

    private static function computeConversionRate(Get $get): float
    {
        $requested = (float)$get('requested_amount');
        $purchased = (float)$get('purchased_equivalent');
        $exchangeRate = (float)$get('exchange_rate');

        if ($requested == 0) return 0;

        return ($purchased / $requested) * $exchangeRate;
    }

    private static function computeFinalEquivalent(Get $get, ?float $finalRate = null): float
    {
        $requested = (float)$get('requested_amount');
        $purchased = (float)$get('purchased_equivalent');
        $finalRate = $finalRate ?? static::computeFinalRate($get);

        if ($requested == 0) {
            return 0;
        }
        return ($purchased / $requested) * $finalRate;
    }

    private static function computeFinalRate(Get $get): float
    {
        $exchangeRate = (float)$get('exchange_rate');
        $commissionRate = (float)$get('commission_rate') / 100;
        return $exchangeRate * (1 + $commissionRate);
    }

    private static function computeRemaining(Get $get): float
    {
        return (float)$get('requested_amount') - (float)$get('documents_amount');
    }

    private static function computeTotalPurchasedRemittance(Get $get): float
    {
        $baseAmount = (float)$get('purchased_equivalent');
        $commission = static::computeCommissionAmount($get);
        return $baseAmount + $commission;
    }

    private static function computeTotalRequestedRemittance(Get $get): float
    {
        $baseAmount = (float)$get('requested_amount');
        $commissionEquivalent = static::computeCommissionEquivalent($get);
        return $baseAmount + $commissionEquivalent;
    }

    private static function computeTotalRialRemittance(Get $get): float
    {
        $exchangeRate = (float)$get('exchange_rate');
        $purchasedCurrencyId = (int)$get('purchased_currency_id');

        if ($purchasedCurrencyId !== 1) return (float)$get('requested_amount') * $exchangeRate;

        return (float)$get('purchased_equivalent') * $exchangeRate;
    }
}
