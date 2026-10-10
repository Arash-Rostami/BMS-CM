<?php

namespace App\Models\Traits\Product;

use App\Models\Attributes\Indirect;
use App\Models\BankProfile;
use App\Models\Category;
use App\Models\ProformaInvoiceItem;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\RegisteredOrderItem;
use App\Models\Specification;
use App\Models\Target;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Relationships
{
    public function bankProfiles(): MorphMany
    {
        return $this->morphMany(BankProfile::class, 'targetable');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(ProformaInvoiceItem::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }

    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    #[Indirect]
    public function purchaseRequests(): HasManyThrough
    {
        return $this->hasManyThrough(PurchaseRequest::class, PurchaseRequestItem::class, 'product_id', 'id', 'id', 'purchase_request_id')
            ->distinct();
    }

    public function registeredOrderItems(): HasMany
    {
        return $this->hasMany(RegisteredOrderItem::class);
    }

    public function specifications()
    {
        return $this->morphMany(Specification::class, 'specifiable');
    }

    public function targets()
    {
        return $this->morphMany(Target::class, 'targetable');
    }
}
