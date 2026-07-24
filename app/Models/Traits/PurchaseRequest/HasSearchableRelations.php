<?php

namespace App\Models\Traits\PurchaseRequest;

trait HasSearchableRelations
{
    public function scopeSearchAll($query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('purchase_requests.id', 'like', "%{$term}%")
            ->orWhere('purchase_requests.pr_number', 'like', "%{$term}%")
            ->orWhereRelation('requester', 'name', 'like', "%{$term}%")
            ->orWhereRelation('costCenter', 'name', 'like', "%{$term}%")
            ->orWhereRelation('costCenter', 'english_name', 'like', "%{$term}%")
            ->orWhereRelation('department', 'name', 'like', "%{$term}%")
            ->orWhereRelation('department', 'english_name', 'like', "%{$term}%")
        );
    }
}
