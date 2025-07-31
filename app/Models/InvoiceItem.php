<?php

namespace App\Models;

use App\Models\Traits\General\HasLocalizedAttributes;
use App\Models\Traits\General\Localization;
use App\Models\Traits\InvoiceItem\Relationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes, Relationships, Localization, HasLocalizedAttributes;

    protected array $localizedAttributesMap = [
        'description' => [
            'fa' => 'description',
            'en' => 'english_description',
        ],
    ];

    protected $fillable = [
        'proforma_invoice_id',
        'product_id',
        'description',
        'english_description',
        'origin',
        'hs_code',
        'quantity',
        'net_weight',
        'gross_weight',
        'unit_price',
        'total_amount',
    ];

    protected $casts = [
        'unit_net_weight' => 'decimal:3',
        'unit_gross_weight' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'total_fob_amount' => 'decimal:2',
    ];
}
