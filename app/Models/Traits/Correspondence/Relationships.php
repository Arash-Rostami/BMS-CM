<?php

namespace App\Models\Traits\Correspondence;

use App\Models\Attachment;
use App\Models\Correspondence;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait Relationships
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the parent model (RegisteredOrder, etc.).
     */
    public function correspondable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Parent message if this is a reply.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class, 'parent_id');
    }

    /**
     * Targeted recipients with pivot data (type, read_at).
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'correspondence_recipients')
            ->withPivot(['type', 'read_at'])
            ->withTimestamps();
    }

    /**
     * Replies to this message.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Correspondence::class, 'parent_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class)
            ->where('english_type', static::TYPE_CORRESPONDENCE_STATUS);
    }

    public function unreadRecipients(): BelongsToMany
    {
        return $this->recipients()->wherePivotNull('read_at');
    }
}
