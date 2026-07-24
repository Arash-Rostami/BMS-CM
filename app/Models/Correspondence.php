<?php

namespace App\Models;

use App\Models\Traits\Correspondence\HasCorrespondenceBodyField;
use App\Models\Traits\Correspondence\HasThreadGroup;
use App\Models\Traits\Correspondence\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Correspondence extends Model
{
    use ExclusiveRelationships,
        HasCorrespondenceBodyField,
        HasFactory,
        HasThreadGroup,
        Relationships,
        SoftDeletes,
        UserStamps;

    public const TYPE_CORRESPONDENCE_STATUS = 'Correspondence Status';

    protected $fillable = [
        'correspondable_type',
        'correspondable_id',
        'parent_id',
        'subject',
        'body',
        'type',
        'priority',
        'is_internal',
        'is_private',
        'status_id',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_private' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    protected function conversationId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->parent_id ?? $this->id,
        );
    }

    public function markReadBy(int $userId): void
    {
        $this->recipients()
            ->wherePivot('user_id', $userId)
            ->wherePivotNull('read_at')
            ->first()
            ?->pivot
            ?->markAsRead();
    }
}
