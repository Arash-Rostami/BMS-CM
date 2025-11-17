<?php

namespace App\Models\Traits\BankProfile;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasComputedAttributes
{
    /**
     * مبلغ کارمزد خریداری شده
     * =IF([@[ارز درخواست]]="USD",[@[معادل خریداری شده]]*[@[نرخ کارمزد]],[@[نرخ کارمزد]]*[@[مبلغ درخواست]])
     * ---
     *  currency_id == 1 (USD)
     */
    public function commissionAmountPurchased(): Attribute
    {
        return Attribute::make(
            get: function () {
                $commissionRate = ($this->commission_rate ?? 0) / 100;;

                if ($this->currency_id == 1) {
                    return ($this->purchased_equivalent ?? 0) * $commissionRate;
                }

                return ($this->requested_amount ?? 0) * $commissionRate;
            }
        );
    }

    /**
     * معادل یورویی کارمزد
     * =IF([@[ارز درخواست]]="USD",([@[مبلغ درخواست]]/[@[معادل خریداری شده]])*[@[مبلغ کارمزد خریداری شده]])
     * (Assumes non-USD is EUR and thus 1:1 with commission amount)
     * ---
     * CHANGED to check currency_id != 1 (NOT USD)
     */
    public function commissionEquivalentEur(): Attribute
    {
        return Attribute::make(
            get: function () {
                $commissionAmount = $this->commission_amount_purchased;

                if ($this->currency_id != 1) {
                    return $commissionAmount;
                }

                $requested = $this->requested_amount ?? 0;
                $purchased = $this->purchased_equivalent ?? 0;

                if ($purchased == 0 || $requested == 0) {
                    return 0;
                }

                return ($requested / $purchased) * $commissionAmount;
            }
        );
    }

    /**
     * معادل یورو تمام شده
     * =([@[معادل خریداری شده]]/[@[مبلغ درخواست]])*[@[نرخ تمام شده]]
     * ---
     * (No currency logic needed here)
     */
    public function finalEurEquivalent(): Attribute
    {
        return Attribute::make(
            get: function () {
                $requested = $this->requested_amount ?? 0;
                $purchased = $this->purchased_equivalent ?? 0;
                $finalRate = $this->final_rate ?? 0;

                if ($requested == 0) {
                    return 0;
                }

                return ($purchased / $requested) * $finalRate;
            }
        );
    }

    /**
     * مانده تعهد
     * =[@[مبلغ درخواست]]-[@[مبلغ اسناد ]]
     * ---
     * (No currency logic needed here)
     */
    public function remainingCommitment(): Attribute
    {
        return Attribute::make(
            get: fn() => ($this->requested_amount ?? 0) - ($this->documents_amount ?? 0)
        );
    }

    public function totalEurRemittance(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->currency_id == 1) { // 1 = USD
                    $baseEurEquivalent = $this->requested_amount ?? 0;
                    $commissionEurEquivalent = $this->commission_equivalent_eur;
                    return $baseEurEquivalent + $commissionEurEquivalent;
                }

                if ($this->currency_id == 2) { // 2 = EUR
                    $baseAmount = $this->requested_amount ?? 0;
                    $commission = $this->commission_amount_purchased;
                    return $baseAmount + $commission;
                }

                return 0.0;
            }
        );
    }

    /**
     * مجموع ریال (خالص حواله)
     * =IF([@[ارز درخواست]]="EUR",[@[مبلغ درخواست]]*[@[نرخ ارز]],[@[معادل خریداری شده]]*[@[نرخ ارز]])
     * ---
     * CHANGED to check currency_id == 2 (EUR)
     */
    public function totalRialRemittance(): Attribute
    {
        return Attribute::make(
            get: function () {
                $exchangeRate = $this->exchange_rate ?? 0;

                if ($this->currency_id == 2) {
                    return ($this->requested_amount ?? 0) * $exchangeRate;
                }

                return ($this->purchased_equivalent ?? 0) * $exchangeRate;
            }
        );
    }

    public function totalUsdRemittance(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->currency_id == 1) { // 1 = USD
                    $baseAmount = $this->purchased_equivalent ?? 0;
                    $commission = $this->commission_amount_purchased;
                    return $baseAmount + $commission;
                }
                return 0.0;
            }
        );
    }
}
