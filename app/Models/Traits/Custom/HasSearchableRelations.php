<?php

namespace App\Models\Traits\Custom;

use Illuminate\Database\Eloquent\Builder;

trait HasSearchableRelations
{
    public function scopeSearchAll(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') return $query;

        return $query->where(fn($q) => $q->whereAny([
            'customs.contract_no',
            'customs.declaration_no',
            'customs.clearance_type'
        ], 'like', "%{$term}%")
            ->orWhereHas('registeredOrder', fn(Builder $query) => $query->whereAny([
                'official_registration_no',
                'contract_no',
                'ro_number',
            ], 'like', "%{$term}%"))
            ->orWhereHas('shipment', fn(Builder $query) => $query->whereAny([
                'shipment_no',
                'bl_number',
                'booking_no',
            ], 'like', "%{$term}%"))
        );
    }
}
