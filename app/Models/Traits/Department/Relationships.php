<?php

namespace App\Models\Traits\Department;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait Relationships
{
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function purchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'department_id');
    }

    public function costCenterForPurchaseRequests(): HasMany
    {
        return $this->hasMany(PurchaseRequest::class, 'cost_center_id');
    }
}
