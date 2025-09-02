<?php

namespace App\Models\Traits\PurchaseOrder;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
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

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_id')
            ->buyers();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function proformaInvoices(): BelongsToMany
    {
        return $this->belongsToMany(ProformaInvoice::class, 'proforma_invoice_purchase_order');
    }

    public function purchaseRequests(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseRequest::class, 'purchase_order_purchase_request');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class)
            ->where('english_type', static::TYPE_PURCHASE_ORDER);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id')
            ->hasAnyType(
                Company::TYPE_SUPPLIER,
                Company::TYPE_MANUFACTURER,
                Company::TYPE_SELLER
            );
    }
}
