<?php

namespace App\Models\Traits\Custom;

use App\Models\Attachment;
use App\Models\Correspondence;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Relationships
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function bankGuaranteeStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'bank_guarantee_status_id')
            ->where('english_type', static::TYPE_BANK_GUARANTEE_STATUS);
    }

    public function clearanceStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'clearance_status_id')
            ->where('english_type', static::TYPE_CLEARANCE_STATUS);
    }

    public function commitmentStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'commitment_status_id')
            ->where('english_type', static::TYPE_COMMITMENT_STATUS);
    }

    public function correspondences(): MorphMany
    {
        return $this->morphMany(Correspondence::class, 'correspondable');
    }

    public function registeredOrder(): BelongsTo
    {
        return $this->belongsTo(RegisteredOrder::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
