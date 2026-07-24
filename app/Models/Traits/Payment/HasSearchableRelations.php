<?php

namespace App\Models\Traits\Payment;

trait HasSearchableRelations
{
    public function scopeSearchAll($query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('payments.id', 'like', "%{$term}%")
            ->orWhere('payments.payment_no', 'like', "%{$term}%")
        );
    }
}
