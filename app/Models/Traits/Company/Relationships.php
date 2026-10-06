<?php

namespace App\Models\Traits\Company;

use App\Models\BankProfile;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
    public function bankProfiles(): HasMany
    {
        return $this->hasMany(BankProfile::class, 'company_id');
    }

    public function paymentsAsPayee(): HasMany
    {
        return $this->hasMany(Payment::class, 'payee_id');
    }

    public function paymentsAsPayor(): HasMany
    {
        return $this->hasMany(Payment::class, 'payor_id');
    }

    public function proformaInvoicesAsBuyer(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class, 'buyer_id');
    }

    public function proformaInvoicesAsSeller(): HasMany
    {
        return $this->hasMany(ProformaInvoice::class, 'seller_id');
    }

    public function purchaseOrdersAsBuyer(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'buyer_id');
    }

    public function purchaseOrdersAsSeller(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'seller_id');
    }

    public function registeredOrdersAsBuyer(): HasMany
    {
        return $this->hasMany(RegisteredOrder::class, 'buyer_id');
    }

    public function registeredOrdersAsSeller(): HasMany
    {
        return $this->hasMany(RegisteredOrder::class, 'seller_id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class, 'company_id');
    }
}
