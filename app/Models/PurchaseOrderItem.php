<?php

namespace App\Models;

use App\Models\Traits\PurchaseOrderItem\Relationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderItem extends Model
{
    use HasFactory, SoftDeletes, Relationships;

    protected $fillable = [
        'purchase_order_id',
        'product_id',
        'quantity',
        'unit',
        'unit_price',
        'net_weight',
        'gross_weight',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'net_weight' => 'decimal:2',
        'gross_weight' => 'decimal:2',
    ];
}
