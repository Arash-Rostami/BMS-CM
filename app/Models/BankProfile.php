<?php

namespace App\Models;

use App\Models\Traits\BankProfile\HasComputedAttributes;
use App\Models\Traits\BankProfile\HasSearchableRelations;
use App\Models\Traits\BankProfile\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\HasProductCategoryFormatting;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\SearchTargetable;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankProfile extends Model
{
    use HasFactory,
        SoftDeletes,
        Relationships,
        ExclusiveRelationships,
        HasComputedAttributes,
        searchTargetable,
        HasProductCategoryFormatting,
        HasSearchableRelations,
        UserStamps;

    public const TYPE_BANK_PROFILE = 'Bank Profile Status';

    public const SCANNABLE_TABLE = 'bank_profiles';
    public const SCANNABLE_IDENTIFIER = 'bp_number';


    protected $fillable = [
        'bp_number',
        'status_id',
        'registered_order_id',
        'company_id',
        'bank_id',
        'targetable_type',
        'targetable_id',
        'order_number',
        'supply_source',
        'requested_amount',
        'requested_currency_id',
        'purchased_equivalent',
        'purchased_currency_id',
        'commission_rate',
        'exchange_rate',
        'final_rate',
        'conversion_rate',
        'documents_amount',
        'creation_date',
        'allocation_date',
        'purchase_date',
        'delivery_date',
        'notes',
        'user_id',
        'updated_by_id',
    ];

    protected $appends = [
        'commission_amount_purchased',
        'commission_equivalent',
        'final_equivalent',
        'final_rate_display',
        'remaining_commitment',
        'total_rial_remittance',
        'total_requested_remittance',
        'total_purchased_remittance',
    ];


    protected $casts = [
        'creation_date' => 'date',
        'allocation_date' => 'date',
        'purchase_date' => 'date',
        'delivery_date' => 'date',
        'requested_amount' => 'decimal:2',
        'purchased_equivalent' => 'decimal:2',
        'commission_rate' => 'decimal:5',
        'exchange_rate' => 'decimal:5',
        'final_rate' => 'decimal:5',
        'conversion_rate' => 'decimal:5',
        'documents_amount' => 'decimal:2',
    ];
}
