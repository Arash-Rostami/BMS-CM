<?php

namespace App\Models;

use App\Models\Traits\Currency\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\HasScope;
use App\Models\Traits\General\Localization;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Currency extends Model
{
    use ExclusiveRelationships,
        HasFactory,
        HasScope,
        Localization,
        Relationships,
        SoftDeletes,
        UserStamps;

    protected $fillable = [
        'name',
        'english_name',
        'description',
        'user_id',
        'is_active',
        'updated_by_id',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];
}
