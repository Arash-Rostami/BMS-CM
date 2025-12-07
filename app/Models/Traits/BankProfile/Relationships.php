<?php

namespace App\Models\Traits\BankProfile;

use App\Models\Attachment;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Correspondence;
use App\Models\Currency;
use App\Models\RegisteredOrder;
use App\Models\Status;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

trait Relationships
{
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class, 'bank_id')
            ->where('is_active', 1);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id')
            ->where('is_active', 1);
    }

    public function correspondences(): MorphMany
    {
        return $this->morphMany(Correspondence::class, 'correspondable');
    }

    public function purchasedCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'purchased_currency_id')
            ->where('is_active', 1);
    }

    public function registeredOrder(): BelongsTo
    {
        return $this->belongsTo(RegisteredOrder::class);
    }

    public function requestedCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'requested_currency_id')
            ->where('is_active', 1);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class)
            ->where('english_type', static::TYPE_BANK_PROFILE);
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}
