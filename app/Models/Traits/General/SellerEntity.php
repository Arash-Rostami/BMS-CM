<?php

namespace App\Models\Traits\General;

use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait SellerEntity
{
    public function manufacturerCompanyExclusive(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'seller_id')
            ->manufacturers()
            ->where('is_active', 1);
    }

    public function sellerCompanyExclusive(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'seller_id')
            ->sellers()
            ->where('is_active', 1);
    }

    public function supplierCompanyExclusive(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'seller_id')
            ->suppliers()
            ->where('is_active', 1);
    }
}
