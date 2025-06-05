<?php

namespace App\Models;

use App\Models\Traits\General\HasSlug;
use App\Models\Traits\General\Localization;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\Product\CustomizedLabel;
use App\Models\Traits\Product\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\Product\RollSheetEstimator;
use App\Models\Traits\Product\ValueTypeEstimator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, Relationships, ExclusiveRelationships, HasSlug,
        UserStamps, Localization, ValueTypeEstimator, RollSheetEstimator, CustomizedLabel;

    protected $table = 'products';

    protected $fillable = [
        'name',
        'english_name',
        'slug',
        'description',
        'code',
        'in_stock',
        'user_id',
        'updated_by_id',
        'category_id',
        'attributes',
    ];

    protected $casts = [
        'in_stock' => 'boolean',
        'attributes' => 'array',
    ];
}
