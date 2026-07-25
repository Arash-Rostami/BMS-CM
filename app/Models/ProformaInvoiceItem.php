<?php

namespace App\Models;

use App\Models\Traits\ProformaInvoiceItem\Relationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProformaInvoiceItem extends Model
{
    use HasFactory,
        Relationships,
        SoftDeletes;

    protected $fillable = [
        'proforma_invoice_id',
        'product_id',
        'description',
        'origin',
        'hs_code',
        'quantity',
        'net_weight',
        'gross_weight',
        'unit',
        'unit_price',
        'freight_charges',
        'total_amount',
    ];

    protected $casts = [
        'quantity' => 'decimal:5',
        'net_weight' => 'decimal:5',
        'gross_weight' => 'decimal:5',
        'unit_price' => 'decimal:5',
        'freight_charges' => 'decimal:5',
        'total_amount' => 'decimal:5',
    ];
}
