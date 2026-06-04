<?php

namespace App\Models;

use App\Models\Traits\Attachment\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory,
        SoftDeletes,
        Relationships,
        ExclusiveRelationships,
        UserStamps;

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

    protected static function booted(): void
    {
        static::forceDeleted(function (Attachment $attachment): void {
            if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
                Storage::disk('public')->delete($attachment->path);
            }
        });
    }
}
