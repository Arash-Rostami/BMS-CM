<?php

namespace App\Models;

use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\Specification\Relationships as ExclusiveRelationships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Specification extends Model
{
    use SoftDeletes, Relationships, ExclusiveRelationships, UserStamps;

    protected $fillable = [
        'specifiable_type',
        'specifiable_id',
        'hs_code',
        'import_duty',
        'packing_type',
        'vat_exempt',
        'tax_id',
        'manufacturer',
        'import_licenses',
        'extra',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'vat_exempt' => 'boolean',
        'import_licenses' => 'array',
        'extra' => 'array',
    ];
}
