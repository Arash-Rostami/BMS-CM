<?php

namespace App\Models\Traits\RegisteredOrder;

trait HasSearchableRelations
{
    public function scopeSearchAll($query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('registered_orders.id', 'like', "%{$term}%")
            ->orWhere('registered_orders.ro_number', 'like', "%{$term}%")
            ->orWhere('registered_orders.contract_no', 'like', "%{$term}%")
        );
    }
}
