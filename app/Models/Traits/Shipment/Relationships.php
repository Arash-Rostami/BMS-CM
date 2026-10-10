<?php

namespace App\Models\Traits\Shipment;

use App\Models\Attachment;
use App\Models\Attributes\Indirect;
use App\Models\Company;
use App\Models\Correspondence;
use App\Models\Custom;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
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

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id')
            ->where('is_active', 1);
    }

    public function containerStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'container_status_id')
            ->where('english_type', Shipment::TYPE_CONTAINER_STATUS);
    }

    public function customs(): HasMany
    {
        return $this->hasMany(Custom::class);
    }

    public function docStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'doc_status_id')
            ->where('english_type', Shipment::TYPE_DOC_STATUS);

    }

    public function correspondences(): MorphMany
    {
        return $this->morphMany(Correspondence::class, 'correspondable');
    }

    public function operationStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'operation_status_id')
            ->where('english_type', Shipment::TYPE_OPERATION_STATUS);
    }

    #[Indirect]
    public function purchaseRequests(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseRequest::class, 'registered_order_purchase_request', 'registered_order_id', 'purchase_request_id', 'registered_order_id', 'id')
            ->distinct();
    }

    public function registeredOrder(): BelongsTo
    {
        return $this->belongsTo(RegisteredOrder::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id')
            ->where('english_type', Shipment::TYPE_SHIPMENT_STATUS);
    }

    public function trackingStatus(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'shipment_status_id')
            ->where('english_type', Shipment::TYPE_TRACKING_STATUS);
    }
}
