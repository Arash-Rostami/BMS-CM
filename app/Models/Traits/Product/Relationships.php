<?php

namespace App\Models\Traits\Product;

use App\Models\Category;
use App\Models\ProformaInvoiceItem;
use App\Models\PurchaseRequestItem;
use App\Models\Specification;
use App\Models\Target;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
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

    public function specifications()
    {
        return $this->morphMany(Specification::class, 'specifiable');
    }

    public function targets()
    {
        return $this->morphMany(Target::class, 'targetable');
    }
}
