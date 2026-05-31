<?php

namespace App\Models;

use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\SellerEntity;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\RegisteredOrder\HasFormattedName;
use App\Models\Traits\RegisteredOrder\HasSearchableRelations;
use App\Models\Traits\RegisteredOrder\Relationships as ExclusiveRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegisteredOrder extends Model
{
    use HasFactory,
        SoftDeletes,
        Relationships,
        ExclusiveRelationships,
        HasFormattedName,
        HasSearchableRelations,
        SellerEntity,
        UserStamps;


   public const SCANNABLE_TABLE = 'registered_orders';
   public  const SCANNABLE_IDENTIFIER = 'ro_number';

    public const TYPE_REGISTERED_ORDER = 'Registered Order Status';

    protected $fillable = [
        'ro_number',
        'contract_no',
        'official_registration_no',
        'seller_id',
        'buyer_id',
        'status_id',
        'order_date',
        'validity_date',
        'expected_delivery_date',
        'incoterms',
        'currency_id',
        'currency_type',
        'insurance_number',
        'insurance_provider',
        'insurance_date',
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
