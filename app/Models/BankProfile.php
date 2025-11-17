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
        'purchased_equivalent',
        'commission_rate',
        'exchange_rate',
        'final_rate',
        'eur_equivalent_rate',
        'documents_amount',
        'allocation_date',
        'purchase_date',
        'delivery_date',
        'notes',
        'user_id',
        'updated_by_id',
    ];

    protected $appends = [
        'commission_amount_purchased',
        'commission_equivalent_eur',
        'final_eur_equivalent',
        'remaining_commitment',
        'total_rial_remittance',
        'total_usd_remittance',
        'total_eur_remittance',
    ];


    protected $casts = [
        'allocation_date' => 'date',
        'purchase_date' => 'date',
        'delivery_date' => 'date',
        'requested_amount' => 'decimal:2',
        'purchased_equivalent' => 'decimal:2',
        'commission_rate' => 'decimal:5',
        'exchange_rate' => 'decimal:5',
        'final_rate' => 'decimal:5',
        'eur_equivalent_rate' => 'decimal:5',
        'documents_amount' => 'decimal:2',
    ];
}
