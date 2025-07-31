<?php

namespace App\Models\Traits\Attachment;

use App\Models\Status;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;


trait Relationships
{
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }
}
