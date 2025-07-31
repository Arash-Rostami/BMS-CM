<?php

namespace App\Models;

use App\Models\Traits\Department\HasSearchableRelations;
use App\Models\Traits\Department\Relationships;
use App\Models\Traits\General\Localization;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory, Relationships, Localization, HasSearchableRelations;


    protected $fillable = [
        'name',
        'code',
        'english_name',
        'description',
    ];
}
