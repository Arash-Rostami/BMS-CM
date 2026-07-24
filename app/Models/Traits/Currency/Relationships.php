<?php

namespace App\Models\Traits\Currency;

use App\Models\ProformaInvoice;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
    public function proformaInvoicesAsMain(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class, 'main_currency_id');
    }

    public function proformaInvoicesAsSecondary(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class, 'secondary_currency_id');
    }
}
