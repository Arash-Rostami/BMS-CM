<?php

namespace App\Models;

use App\Models\Traits\General\HasNameSearch;
use App\Models\Traits\General\Localization;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\Status\HasSearchableRelations;
use App\Models\Traits\Status\Relationships as ExclusiveRelationships;
use App\Models\Traits\Status\StatusFinder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Status extends Model
{
    use ExclusiveRelationships,
        HasFactory,
        HasNameSearch,
        HasSearchableRelations,
        Localization,
        Relationships,
        SoftDeletes,
        StatusFinder,
        UserStamps;

    protected $table = 'statuses';

    protected $fillable = [
        'type',
        'english_type',
        'name',
        'english_name',
        'user_id',
        'updated_by_id',
    ];
}
