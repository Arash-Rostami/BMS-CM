<?php

namespace App\Models;

use App\Models\Traits\General\Localization;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Status extends Model
{
    use HasFactory, SoftDeletes, Relationships, UserStamps, Localization;

    protected $table = 'statuses';

    protected $fillable = [
        'type',
        'name',
        'english_name',
        'user_id',
        'updated_by_id',
    ];

}
