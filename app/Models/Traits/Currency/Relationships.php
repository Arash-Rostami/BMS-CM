<?php

namespace App\Models\Traits\Currency;

use App\Models\BankProfile;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
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

    public function bankProfiles(): HasMany
    {
        return $this->hasMany(BankProfile::class, 'currency_id');
    }

    public function bankProfilesAsRequested(): HasMany
    {
        return $this->hasMany(BankProfile::class, 'requested_currency_id');
    }

    public function bankProfilesAsPurchased(): HasMany
    {
        return $this->hasMany(BankProfile::class, 'purchased_currency_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'currency_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'currency_id');
    }

    public function registeredOrders(): HasMany
    {
        return $this->hasMany(RegisteredOrder::class, 'currency_id');
    }
}
