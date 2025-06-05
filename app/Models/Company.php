<?php

namespace App\Models;

use App\Models\Traits\General\Localization;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes, Relationships, UserStamps, Localization;

    protected $fillable = [
        'name',
        'english_name',
        'description',
        'user_id',
        'updated_by_id',
    ];


    protected $casts = [
        'deleted_at' => 'datetime',
    ];
}
