<?php

namespace App\Models;

use App\Models\Traits\Custom\HasFormattedName;
use App\Models\Traits\Custom\HasSearchableRelations;
use App\Models\Traits\Custom\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Custom extends Model
{
    use HasFactory,
        SoftDeletes,
        Relationships,
        ExclusiveRelationships,
        HasFormattedName,
        HasSearchableRelations,
        UserStamps;

    public const TYPE_CLEARANCE_STATUS = 'Clearance Status';
    public const TYPE_BANK_GUARANTEE_STATUS = 'Guarantee Status';
    public const TYPE_COMMITMENT_STATUS = 'Commitment Fulfillment Status';

    protected $table = 'customs';

    protected $fillable = [
        'custom_no',
        'shipment_id',
        'registered_order_id',
        'shipment_no',
        'contract_no',
        'declaration_no',
        'clearance_type',
        'commitment_balance',
        'clearance_date',
        'doc_submission_date',
        'ten_percent_exit_date',
        'payment_due_date',
        'commitment_payment_date',
        'rial_return_date',
        'clearance_status_id',
        'bank_guarantee_status_id',
        'commitment_status_id',
        'notes',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'commitment_balance' => 'decimal:2',
        'clearance_date' => 'date',
        'doc_submission_date' => 'date',
        'ten_percent_exit_date' => 'date',
        'payment_due_date' => 'date',
        'commitment_payment_date' => 'date',
        'rial_return_date' => 'date',
    ];
}
