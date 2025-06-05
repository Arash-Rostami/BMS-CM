<?php

namespace App\Models;

use App\Models\Traits\General\Relationships;
use App\Models\Traits\Target\HasMetricAttribute;
use App\Models\Traits\Target\HasTargetableLabel;
use App\Models\Traits\Target\HasYearAttribute;
use App\Models\Traits\Target\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\Target\SearchTargetable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Target extends Model
{
    use HasFactory, SoftDeletes, Relationships, ExclusiveRelationships, UserStamps,
        HasMetricAttribute, HasTargetableLabel, HasYearAttribute, SearchTargetable;

    protected $fillable = [
        'targetable_type',
        'targetable_id',
        'year',
        'start_from',
        'end_in',
        'quantity',
        'amount',
        'metrics',
        'description',
        'tags',
        'status',
        'user_id',
        'updated_by_id',
        'achieved_quantity',
        'achieved_amount',
    ];

    protected $casts = [
        'year' => 'integer',
        'start_from' => 'date',
        'end_in' => 'date',
        'quantity' => 'decimal:2',
        'amount' => 'decimal:2',
        'achieved_quantity' => 'decimal:2',
        'achieved_amount' => 'decimal:2',
        'tags' => 'array',
    ];
}
