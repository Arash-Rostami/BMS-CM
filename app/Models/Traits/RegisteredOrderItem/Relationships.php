<?php

namespace App\Models\Traits\RegisteredOrderItem;

use App\Models\Product;
use App\Models\RegisteredOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Relationships
{
    public function registeredOrder(): BelongsTo
    {
        return $this->belongsTo(RegisteredOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
