<?php

namespace App\Models;

use App\Models\Traits\Attachment\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use HasFactory, SoftDeletes, Relationships, ExclusiveRelationships, UserStamps;

    protected $fillable = [
        'attachable_id',
        'attachable_type',
        'name',
        'path',
        'type',
        'status_id',
        'user_id',
        'updated_by_id',
    ];
}
