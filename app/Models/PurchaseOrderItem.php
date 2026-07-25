<?php

namespace App\Models;

use App\Models\Traits\PurchaseOrderItem\Relationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrderItem extends Model
{
    use HasFactory, Relationships, SoftDeletes;

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
        'quantity' => 'decimal:5',
        'unit_price' => 'decimal:5',
        'net_weight' => 'decimal:5',
        'gross_weight' => 'decimal:5',
    ];
}
