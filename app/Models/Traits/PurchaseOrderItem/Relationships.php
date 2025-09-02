<?php

namespace App\Models\Traits\PurchaseOrderItem;

use App\Models\Product;
use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait Relationships
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
