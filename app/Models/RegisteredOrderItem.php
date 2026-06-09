<?php

namespace App\Models;

use App\Models\Traits\RegisteredOrderItem\Relationships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegisteredOrderItem extends Model
{
    use SoftDeletes,
        Relationships;

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
}
