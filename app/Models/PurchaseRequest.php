<?php

namespace App\Models;

use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\PurchaseRequest\HasFormattedName;
use App\Models\Traits\PurchaseRequest\HasSearchableRelations;
use App\Models\Traits\PurchaseRequest\Relationships as ExclusiveRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use HasFactory,
        SoftDeletes,
        Relationships,
        ExclusiveRelationships,
        UserStamps,
        HasFormattedName,
        HasSearchableRelations;

    public const TYPE_PURCHASE_REQUEST = 'Purchase Request Status';

    protected $fillable = [
        'requester_id',
        'department_id',
        'cost_center_id',
        'required_by_date',
        'total_estimated_cost',
        'urgency_level',
        'status_id',
        'approver_id',
        'approval_date',
        'rejection_reason',
        'notes',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'required_by_date' => 'date',
        'approval_date' => 'datetime',
        'total_estimated_cost' => 'decimal:2',
    ];
}
