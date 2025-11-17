<?php

namespace App\Models\Traits\PurchaseOrder;

trait HasSearchableRelations
{
    public function scopeSearchAll($query, string $term)
    {
        $term = trim($term);
        if ($term === '') return $query;

        return $query->where(fn($q) => $q->where('purchase_orders.id', 'like', "%{$term}%")
            ->orWhere('purchase_orders.po_number', 'like', "%{$term}%")
        );
    }
}
