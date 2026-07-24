<?php

namespace App\Models\Traits\Status;

use App\Models\Attachment;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }
}
