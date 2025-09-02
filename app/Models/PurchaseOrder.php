<?php

namespace App\Models;

use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\PurchaseOrder\Accessors;
use App\Models\Traits\PurchaseOrder\HasSearchableRelations;
use App\Models\Traits\PurchaseOrder\Relationships as ExclusiveRelationships;
use App\Models\Traits\PurchaseOrder\HasFormattedName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory,
        SoftDeletes,
        Relationships,
        ExclusiveRelationships,
        Accessors,
        HasFormattedName,
        HasSearchableRelations,
        UserStamps;

    public const TYPE_PURCHASE_ORDER = 'Purchase Order Status';

    protected $appends = ['total_amount', 'total_quantity'];


    protected $fillable = [
        'po_number',
        'supplier_id',
        'buyer_id',
        'status_id',
        'order_date',
        'validity_date',
        'expected_delivery_date',
        'incoterms',
        'shipping_address',
        'packing_details',
        'currency_id',
        'notes',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'order_date' => 'date',
        'validity_date' => 'date',
        'expected_delivery_date' => 'date',
    ];
}
