<?php

namespace App\Models\Traits\RegisteredOrder;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasComputedAttributes
{
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->items()->sum('line_total'),
        );
    }

    protected function totalQuantity(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->items()->sum('quantity'),
        );
    }
}
