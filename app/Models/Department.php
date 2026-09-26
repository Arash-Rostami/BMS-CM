<?php

namespace App\Models;

use App\Models\Traits\Department\HasSearchableRelations;
use App\Models\Traits\Department\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\HasScope;
use App\Models\Traits\General\Localization;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use ExclusiveRelationships,
        HasFactory,
        HasScope,
        HasSearchableRelations,
        Localization,
        Relationships,
        SoftDeletes,
        UserStamps;

    public const SCANNABLE_IDENTIFIER = 'code';

    protected $fillable = [
        'name',
        'code',
        'english_name',
        'description',
        'is_active',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'deleted_at' => 'datetime',
    ];
}
