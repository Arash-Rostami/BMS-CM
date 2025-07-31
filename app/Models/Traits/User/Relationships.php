<?php

namespace App\Models\Traits\User;

use App\Models\Attachment;
use App\Models\Department;
use App\Models\ProformaInvoice;
use App\Models\PurchaseRequest;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }


    public function PurchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'user_id');
    }


    public function purchaseRequestRequester(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'requester_id');
    }

    public function approvedPurchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'approver_id');
    }


    public function proformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class, 'user_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'user_id');
    }
}
