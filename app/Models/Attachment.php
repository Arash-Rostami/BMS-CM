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
    use ExclusiveRelationships,
        HasFactory,
        Relationships,
        SoftDeletes,
        UserStamps;

    public const TYPE_ATTACHMENT = 'Attachment Status';

    public const STATUS_UPLOADED = 'Uploaded';

    public const STATUS_SUPERSEDED = 'Superseded';

    public const STATUS_ARCHIVED = 'Archived';

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

    public function isUploaded(): bool
    {
        return $this->status?->english_name === self::STATUS_UPLOADED;
    }

    public function isSuperseded(): bool
    {
        return $this->status?->english_name === self::STATUS_SUPERSEDED;
    }

    public function isArchived(): bool
    {
        return $this->status?->english_name === self::STATUS_ARCHIVED;
    }
}
