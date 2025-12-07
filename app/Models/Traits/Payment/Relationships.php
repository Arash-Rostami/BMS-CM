<?php

namespace App\Models\Traits\Payment;

use App\Models\Attachment;
use App\Models\Bank;
use App\Models\Company;
use App\Models\Currency;
use App\Models\PurchaseOrder;
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

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class)
            ->where('is_active', 1);
    }

    public function payee(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'payee_id')
            ->where('is_active', 1);
    }

    public function payor(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'payor_id')
            ->where('is_active', 1);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'targetable_id');
    }

    public function registeredOrder(): BelongsTo
    {
        return $this->belongsTo(RegisteredOrder::class, 'targetable_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id')
            ->where('english_type', static::TYPE_PAYMENT);
    }

    public function targetable(): MorphTo
    {
        return $this->morphTo();
    }
}
