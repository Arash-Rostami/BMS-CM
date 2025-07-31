<?php

namespace App\Models\Traits\PurchaseRequest;

use App\Models\Attachment;
use App\Models\Department;
use App\Models\ProformaInvoice;
use App\Models\PurchaseItem;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Relationships
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function proformaInvoices(): BelongsToMany
    {
        return $this->belongsToMany(ProformaInvoice::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'cost_center_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class)
            ->where('english_type', static::TYPE_PURCHASE_REQUEST);
    }
}
