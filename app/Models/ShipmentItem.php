<?php

namespace App\Models;

use App\Models\Traits\ShipmentItem\Relationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentItem extends Model
{
    use HasFactory,
        Relationships;

    protected $fillable = [
        'shipment_id',
        'product_id',
        'name',
        'english_name',
        'hs_code',
        'origin',
        'unit',
        'quantity',
        'unit_price',
        'total_amount',
        'net_weight',
        'gross_weight',
    ];

    protected $casts = [
        'quantity' => 'decimal:5',
        'unit_price' => 'decimal:5',
        'total_amount' => 'decimal:5',
        'net_weight' => 'decimal:5',
        'gross_weight' => 'decimal:5',
    ];
}
