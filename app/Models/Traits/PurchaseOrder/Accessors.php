<?php

namespace App\Models\Traits\PurchaseOrder;


use DB;
use Illuminate\Database\Eloquent\Casts\Attribute;

trait Accessors
{
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->items()->sum(DB::raw('quantity * unit_price')),
        );
    }

    protected function totalQuantity(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->items()->sum(DB::raw('quantity')),
        );
    }
}
