<?php

namespace App\Models\Traits\PurchaseRequest;

use App\Models\Attachment;
use App\Models\Attributes\Indirect;
use App\Models\Custom;
use App\Models\Department;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequestItem;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Relationships
{
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function costCenter(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'cost_center_id');
    }

    #[Indirect]
    public function customs(): BelongsToMany
    {
        return $this->belongsToMany(Custom::class, 'registered_order_purchase_request', 'purchase_request_id', 'registered_order_id', 'id', 'registered_order_id')
            ->distinct();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function proformaInvoices(): BelongsToMany
    {
        return $this->belongsToMany(ProformaInvoice::class, 'proforma_invoice_purchase_request');
    }

    public function purchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class, 'purchase_order_purchase_request');
    }

    #[Indirect]
    public function purchaseOrderPayments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'purchase_order_purchase_request', 'purchase_request_id', 'purchase_order_id', 'id', 'targetable_id')
            ->where('payments.targetable_type', (new PurchaseOrder)->getMorphClass())
            ->distinct();
    }

    public function registeredOrders(): BelongsToMany
    {
        return $this->belongsToMany(
            RegisteredOrder::class,
            'registered_order_purchase_request',
            'purchase_request_id',
            'registered_order_id'
        )->withTimestamps();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    #[Indirect]
    public function shipments(): BelongsToMany
    {
        return $this->belongsToMany(Shipment::class, 'registered_order_purchase_request', 'purchase_request_id', 'registered_order_id', 'id', 'registered_order_id')
            ->distinct();
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class)
            ->where('english_type', static::TYPE_PURCHASE_REQUEST);
    }
}
