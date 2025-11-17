<?php

namespace App\Filament\Resources\Operational\BankProfileResource\Traits;

use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

trait Calculation
{
    protected static function updateComputations(Get $get, Set $set): void
    {
        $finalRate = static::computeFinalRate($get);
        $set('final_rate', number_format($finalRate, 5, '.', ''));

        $eurEqRate = static::computeEurEquivalentRate($get);
        $set('eur_equivalent_rate', number_format($eurEqRate, 5, '.', ''));

        $set('commission_amount_purchased', static::computeCommissionAmount($get));
        $set('commission_equivalent_eur', static::computeCommissionEur($get));
        $set('final_eur_equivalent', static::computeFinalEur($get, $finalRate));
        $set('remaining_commitment', static::computeRemaining($get));
        $set('total_rial_remittance', static::computeTotalRialRemittance($get));
        $set('total_usd_remittance', static::computeTotalUsdRemittance($get));
        $set('total_eur_remittance', static::computeTotalEurRemittance($get));
    }

    private static function computeCommissionAmount(Get $get): float
    {
        $commissionRate = (float)$get('commission_rate') / 100;
        $currencyId = self::getCurrencyId($get);

        if ($currencyId === 1) { // 1 = USD
            return (float)$get('purchased_equivalent') * $commissionRate;
        }

        return (float)$get('requested_amount') * $commissionRate;
    }

    private static function computeCommissionEur(Get $get): float
    {
        $commissionAmount = static::computeCommissionAmount($get);
        $currencyId = self::getCurrencyId($get);

        if ($currencyId !== 1) { // Not USD (assume EUR or 1:1)
            return $commissionAmount;
        }

        $requested = (float)$get('requested_amount');
        $purchased = (float)$get('purchased_equivalent');

        if ($purchased == 0 || $requested == 0) {
            return 0;
        }

        return ($requested / $purchased) * $commissionAmount;
    }

    private static function computeEurEquivalentRate(Get $get): float
    {
        $requested = (float)$get('requested_amount');
        $purchased = (float)$get('purchased_equivalent');
        $exchangeRate = (float)$get('exchange_rate');

        if ($requested == 0) return 0;

        return ($purchased / $requested) * $exchangeRate;
    }

    private static function computeFinalEur(Get $get, ?float $finalRate = null): float
    {
        $requested = (float)$get('requested_amount');
        $purchased = (float)$get('purchased_equivalent');
        $finalRate = $finalRate ?? static::computeFinalRate($get);

        if ($requested == 0) return 0;

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
        $requested = (float)$get('requested_amount');
        $documents = (float)$get('documents_amount');
        return $requested - $documents;
    }

    private static function computeTotalEurRemittance(Get $get): float
    {
        $currencyId = self::getCurrencyId($get);

        if ($currencyId === 1) { // 1 = USD
            //  EUR Equivalent Base + EUR Equivalent Commission
            $baseEurEquivalent = (float)$get('requested_amount');
            $commissionEurEquivalent = static::computeCommissionEur($get);
            return $baseEurEquivalent + $commissionEurEquivalent;
        }

        if ($currencyId === 2) { // 2 = EUR
            //  Base EUR + Base EUR Commission
            $baseAmount = (float)$get('requested_amount');
            $commission = static::computeCommissionAmount($get);
            return $baseAmount + $commission;
        }

        return 0.0;
    }

    private static function computeTotalRialRemittance(Get $get): float
    {
        $exchangeRate = (float)$get('exchange_rate');
        $currencyId = self::getCurrencyId($get);

        if ($currencyId === 2) { // 2 = EUR
            return (float)$get('requested_amount') * $exchangeRate;
        }

        return (float)$get('purchased_equivalent') * $exchangeRate;
    }

    private static function computeTotalUsdRemittance(Get $get): float
    {
        $currencyId = self::getCurrencyId($get);
        if ($currencyId === 1) { // 1 = USD
            $baseAmount = (float)$get('purchased_equivalent');
            $commission = static::computeCommissionAmount($get);
            return $baseAmount + $commission;
        }
        return 0.0;
    }

    private static function getCurrencyId(Get $get): ?int
    {
        $id = $get('currency_id');
        return $id ? (int)$id : null;
    }
}
