<?php

namespace App\Models\Traits\BankProfile;

trait HasSearchableRelations
{
    public function scopeSearchAll($query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn($q) => $q->where('bank_profiles.id', 'like', "%{$term}%")
            ->orWhere('bank_profiles.bp_number', 'like', "%{$term}%")
            ->orWhere('bank_profiles.order_number', 'like', "%{$term}%")
        );
    }
}
