<?php

namespace App\Models;

use App\Models\Traits\Category\BreadCrumbs;
use App\Models\Traits\Category\NestedOptions;
use App\Models\Traits\Category\Relationships as ExclusiveRelationships;
use App\Models\Traits\Category\Scopables;
use App\Models\Traits\General\HasSlug;
use App\Models\Traits\General\Localization;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use BreadCrumbs,
        ExclusiveRelationships,
        HasFactory,
        HasSlug,
        Localization,
        NestedOptions,
        Relationships,
        Scopables,
        SoftDeletes,
        UserStamps;

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'slug',
        'english_name',
        'description',
        'parent_id',
        'level',
        'active',
        'user_id',
        'updated_by_id',
    ];
}
