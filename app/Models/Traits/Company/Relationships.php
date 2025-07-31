<?php

namespace App\Models\Traits\Company;

use App\Models\ProformaInvoice;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
    public function soldProformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class, 'seller_company_id');
    }

    public function consignedProformaInvoices(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class, 'consignee_company_id');
    }
}
