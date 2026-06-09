<?php

namespace App\Models;

use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntityAttribute extends Model
{
    use SoftDeletes,
        Relationships,
        UserStamps;


    protected $fillable = [
        'entity_type',
        'entity_id',
        'key',
        'value',
        'user_id',
        'updated_by_id',
        ];

    protected $casts = [
        'value' => 'json',
    ];

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
