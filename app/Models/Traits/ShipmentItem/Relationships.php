<?php

namespace App\Models\Traits\ShipmentItem;

use App\Models\Product;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Relationships
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
