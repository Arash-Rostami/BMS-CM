<?php

namespace App\Models\Traits\RegisteredOrder;

use App\Models\Attachment;
use App\Models\BankProfile;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrderItem;
use App\Models\Status;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Relationships
{

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function bankProfiles(): HasMany
    {
        return $this->hasMany(BankProfile::class);
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
        return $this->hasMany(RegisteredOrderItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'targetable');
    }

    public function proformaInvoices()
    {
        return $this->belongsToMany(
            ProformaInvoice::class,
            'proforma_invoice_registered_order',
            'registered_order_id',
            'proforma_invoice_id'
        )->withTimestamps();
    }

    public function purchaseOrders()
    {
        return $this->belongsToMany(
            PurchaseOrder::class,
            'registered_order_purchase_order',
            'registered_order_id',
            'purchase_order_id'
        )->withTimestamps();
    }

    public function purchaseRequests()
    {
        return $this->belongsToMany(
            PurchaseRequest::class,
            'registered_order_purchase_request',
            'registered_order_id',
            'purchase_request_id'
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
            ->where('english_type', static::TYPE_REGISTERED_ORDER);
    }
}
