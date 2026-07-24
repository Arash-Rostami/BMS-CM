<?php

namespace App\Models\Traits\Shipment;

use Illuminate\Database\Eloquent\Builder;

trait HasSearchableRelations
{
    public function scopeSearchAll(Builder $query, string $term): Builder
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q->whereAny([
            'shipments.shipment_no',
            'shipments.bl_number',
            'shipments.booking_no',
            'shipments.contract_no',
        ], 'like', "%{$term}%"
        )->orWhereHas('registeredOrder', fn (Builder $query) => $query->whereAny([
            'official_registration_no',
            'contract_no',
            'ro_number',
        ], 'like', "%{$term}%")
        ));
    }
}
