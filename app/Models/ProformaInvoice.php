<?php

namespace App\Models;

use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\ProformaInvoice\HasFormattedName;
use App\Models\Traits\ProformaInvoice\HasSearchableRelations;
use App\Models\Traits\ProformaInvoice\Relationships as ExclusiveRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProformaInvoice extends Model
{
    use HasFactory,
        SoftDeletes,
        Relationships,
        HasFormattedName,
        HasSearchableRelations,
        ExclusiveRelationships,
        UserStamps;


    protected $fillable = [
        'invoice_no',
        'invoice_date',
        'contract_no',
        'buyer_comm_card_num',
        'seller_id',
        'buyer_id',
        'validity_date',
        'beneficiary_country',
        'origin_country',
        'destination_country',
        'transport_mode',
        'port_of_discharge',
        'port_of_loading',
        'delivery_terms',
        'main_currency_id',
        'secondary_currency_id',
        'discount',
        'freight_charges',
        'other_charges',
        'total_amount',
        'notes',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'validity_date' => 'date',
        'allow_trans_shipment' => 'boolean',
        'allow_partial_shipment' => 'boolean',
        'discount' => 'decimal:2',
        'freight_charges' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total_cfr_amount' => 'decimal:2',
    ];
}
