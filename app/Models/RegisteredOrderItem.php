<?php

namespace App\Models;

use App\Models\Traits\RegisteredOrderItem\Relationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegisteredOrderItem extends Model
{
    use HasFactory,
        Relationships,
        SoftDeletes;

    protected $fillable = [
        'registered_order_id',
        'product_id',
        'quantity',
        'unit',
        'unit_price',
        'net_weight',
        'gross_weight',
        'entrance_fee',
        'shipping_cost',
        'extra_cost',
        'line_total',
        'packing_details',
        'description',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'net_weight' => 'decimal:2',
        'gross_weight' => 'decimal:2',
        'entrance_fee' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'extra_cost' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];
}
