<?php

namespace App\Models\Traits\BankProfile;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasComputedAttributes
{
    public function commissionAmountPurchased(): Attribute
    {
        return Attribute::make(
            get: fn() => ($this->purchased_equivalent ?? 0) * (($this->commission_rate ?? 0) / 100)
        );
    }

    public function commissionEquivalent(): Attribute
    {
        return Attribute::make(
            get: function () {
                $commissionAmount = $this->commission_amount_purchased;
                $requested = $this->requested_amount ?? 0;
                $purchased = $this->purchased_equivalent ?? 0;

                if ($purchased == 0 || $requested == 0) return 0;

                return ($requested / $purchased) * $commissionAmount;
            }
        );
    }

    public function finalEquivalent(): Attribute
    {
        return Attribute::make(
            get: function () {
                $requested = $this->requested_amount ?? 0;
                $purchased = $this->purchased_equivalent ?? 0;
                $finalRate = $this->final_rate ?? 0;

                if ($requested == 0) return 0;

                return ($purchased / $requested) * $finalRate;
            }
        );
    }

    public function finalRateDisplay(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => ($this->final_rate ?? 0),

            set: function ($value, array $attributes) {
                $exchangeRate = (float) ($attributes['exchange_rate'] ?? 0);
                $commissionRate = (float) ($attributes['commission_rate'] ?? 0);

                return $exchangeRate * (1 + ($commissionRate / 100));
            }
        );
    }

    public function remainingCommitment(): Attribute
    {
        return Attribute::make(
            get: fn() => ($this->requested_amount ?? 0) - ($this->documents_amount ?? 0)
        );
    }

    public function totalPurchasedRemittance(): Attribute
    {
        return Attribute::make(
            get: fn() => ($this->purchased_equivalent ?? 0) + $this->commission_amount_purchased
        );
    }


    public function totalRequestedRemittance(): Attribute
    {
        return Attribute::make(
            get: fn() => ($this->requested_amount ?? 0) + $this->commission_equivalent
        );
    }

    public function totalRialRemittance(): Attribute
    {
        return Attribute::make(
            get: function () {
                $exchangeRate = $this->exchange_rate ?? 0;

                if ($this->purchased_currency_id !== 1) return ($this->requested_amount ?? 0) * $exchangeRate;


                return ($this->purchased_equivalent ?? 0) * $exchangeRate;
            }
        );
    }
}
