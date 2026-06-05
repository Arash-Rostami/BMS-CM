<?php

namespace App\Models;

use App\Contracts\HasDocumentChecklist;
use App\Models\Traits\Attachment\Relationships as ExclusiveRelationships;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Services\DocChecklistMatcher;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use ExclusiveRelationships,
        HasFactory,
        Relationships,
        SoftDeletes,
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
        $sync = function (Attachment $attachment): void {
            if ($attachment->attachable instanceof HasDocumentChecklist) {
                rescue(fn () => DocChecklistMatcher::sync($attachment->attachable), report: false);
            }
        };

        static::saved($sync);
        static::deleted($sync);
        static::restored($sync);

        static::forceDeleted(function (Attachment $attachment) use ($sync): void {
            if ($attachment->path && Storage::disk('public')->exists($attachment->path)) {
                Storage::disk('public')->delete($attachment->path);
            }
            $sync($attachment);
        });
    }
}
