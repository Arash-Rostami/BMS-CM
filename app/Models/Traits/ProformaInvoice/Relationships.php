<?php

namespace App\Models\Traits\ProformaInvoice;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProformaInvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
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

    public function items(): HasMany
    {
        return $this->hasMany(ProformaInvoiceItem::class);
    }

    public function mainCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'main_currency_id')
            ->where('is_active', 1);
    }

    public function purchaseOrders(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseOrder::class, 'proforma_invoice_purchase_order');
    }

    public function purchaseRequests(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseRequest::class, 'proforma_invoice_purchase_request');
    }

    public function registeredOrders()
    {
        return $this->belongsToMany(
            RegisteredOrder::class,
            'proforma_invoice_registered_order',
            'proforma_invoice_id',
            'registered_order_id'
        )->withTimestamps();
    }

    public function secondaryCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'secondary_currency_id')
            ->where('is_active', 1);
    }

    public function sellerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'seller_id')
            ->ofAnyType( Company::TYPE_SERVICE_ALL_SELLERS)
            ->where('is_active', 1);
    }
}
