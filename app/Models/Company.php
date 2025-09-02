<?php

namespace App\Models;

use App\Models\Traits\Company\HasSearchableRelations;
use App\Models\Traits\Company\Relationships as ExclusiveRelationships;
use App\Models\Traits\Company\TypeScopes;
use App\Models\Traits\General\HasScope;
use App\Models\Traits\General\Localization;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory,
        SoftDeletes,
        Relationships,
        ExclusiveRelationships,
        UserStamps,
        Localization,
        HasScope,
        TypeScopes,
        HasSearchableRelations;

    protected $fillable = [
        'name',
        'english_name',
        'description',
        'types',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'types' => 'array',
    ];
}
