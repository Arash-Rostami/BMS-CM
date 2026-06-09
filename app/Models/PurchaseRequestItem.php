<?php

namespace App\Models;

use App\Models\Traits\PurchaseRequestItem\Relationships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequestItem extends Model
{
    use SoftDeletes, Relationships;

    public const TYPE_PURCHASE_REQUEST = 'Purchase Item Status';


    protected $fillable = [
        'purchase_request_id',
        'product_id',
        'quantity',
        'unit',
        'estimated_cost',
        'status_id',
        'notes',
    ];

    protected $casts = [
        'estimated_unit_cost' => 'decimal:2',
        'estimated_line_total' => 'decimal:2',
    ];
}
