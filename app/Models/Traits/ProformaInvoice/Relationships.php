<?php

namespace App\Models\Traits\ProformaInvoice;

use App\Models\Attachment;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InvoiceItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
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

    public function consigneeCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'consignee_company_id')
            ->buyers()
            ->where('is_active', 1);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
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

    public function secondaryCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'secondary_currency_id')
            ->where('is_active', 1);
    }

    public function sellerCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'seller_company_id')
            ->hasAnyType(
                Company::TYPE_SUPPLIER,
                Company::TYPE_MANUFACTURER,
                Company::TYPE_SELLER
            )
            ->where('is_active', 1);
    }
}
