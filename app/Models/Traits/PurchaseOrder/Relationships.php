<?php

namespace App\Models\Traits\PurchaseOrder;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
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

    public function buyerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_id')
            ->buyers()
            ->where('is_active', 1);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class)
            ->where('is_active', 1);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'targetable');
    }

    public function proformaInvoices(): BelongsToMany
    {
        return $this->belongsToMany(ProformaInvoice::class, 'proforma_invoice_purchase_order');
    }

    public function purchaseRequests(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseRequest::class, 'purchase_order_purchase_request');
    }

    public function registeredOrders()
    {
        return $this->belongsToMany(
            RegisteredOrder::class,
            'registered_order_purchase_order',
            'purchase_order_id',
            'registered_order_id'
        )->withTimestamps();
    }

    public function sellerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'seller_id')
            ->ofAnyType(Company::TYPE_SERVICE_ALL_SELLERS)
            ->where('is_active', 1);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class)
            ->where('english_type', static::TYPE_PURCHASE_ORDER);
    }
}
