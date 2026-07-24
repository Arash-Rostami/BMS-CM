<?php

namespace App\Models\Traits\ProformaInvoice;

trait HasSearchableRelations
{
    public function scopeSearchAll($query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('proforma_invoices.id', 'like', "%{$term}%")
            ->orWhere('proforma_invoices.invoice_no', 'like', "%{$term}%")
            ->orWhere('proforma_invoices.contract_no', 'like', "%{$term}%")
        );
    }
}
