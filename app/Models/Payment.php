<?php

namespace App\Models;

use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\Payment\HasComputedAttributes;
use App\Models\Traits\Payment\HasSearchableRelations;
use App\Models\Traits\Payment\HasTargetableDisplay;
use App\Models\Traits\Payment\Relationships as ExclusiveRelationship;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory,
        SoftDeletes,
        Relationships,
        ExclusiveRelationship,
        HasSearchableRelations,
        HasComputedAttributes,
        HasTargetableDisplay,
        UserStamps;

    public const TYPE_PAYMENT = 'Payment Status';

    protected $fillable = [
        'payment_no',
        'payment_date',
        'payment_deadline',
        'status_id',
        'payor_id',
        'payee_id',
        'currency_id',
        'targetable_type',
        'targetable_id',
        'payable_amount',
        'total_amount',
        'exchange_rate',
        'bank_charges',
        'beneficiary_name',
        'beneficiary_address',
        'bank_id',
        'bank_address',
        'account_no',
        'swift',
        'iban',
        'notes',
        'user_id',
        'updated_by_id',
    ];
    protected $appends = [
        'calculated_total',
        'total_ratio',
    ];

    public function getAppends(): array
    {
        return array_merge(parent::getAppends(), ['calculated_total', 'total_ratio']);
    }

    protected $casts = [
        'payment_date' => 'date',
        'payment_deadline' => 'date',
        'payable_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:5',
        'bank_charges' => 'decimal:2',
    ];
}
