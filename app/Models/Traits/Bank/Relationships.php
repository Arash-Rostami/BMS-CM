<?php

namespace App\Models\Traits\Bank;

use App\Models\BankProfile;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
    public function bankProfiles(): HasMany
    {
        return $this->hasMany(BankProfile::class, 'bank_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'bank_id');
    }
}
