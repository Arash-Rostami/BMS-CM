<?php

namespace App\Models\Traits\Payment;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasComputedAttributes
{
    public function calculatedTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => ($this->payable_amount ?? 0) + ($this->bank_charges ?? 0)
        );
    }

    public function totalRatio(): Attribute
    {
        return Attribute::make(
            get: function () {
                $calculated = $this->calculated_total;
                $userTotal = $this->total_amount ?? 0;

                if ($userTotal == 0) {
                    return null;
                }

                return $calculated / $userTotal;
            }
        );
    }
}
