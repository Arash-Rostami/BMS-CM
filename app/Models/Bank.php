<?php

namespace App\Models;

use App\Models\Traits\General\HasNameSearch;
use App\Models\Traits\General\HasScope;
use App\Models\Traits\General\Localization;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bank extends Model
{
    use SoftDeletes,
        Relationships,
        UserStamps,
        Localization,
        HasNameSearch,
        HasScope;

    protected $fillable = [
        'name',
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
