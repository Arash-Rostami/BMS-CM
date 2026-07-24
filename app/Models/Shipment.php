<?php

namespace App\Models;

use App\Contracts\HasDocumentChecklist;
use App\Models\Traits\General\HasCustomAttributes;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\Shipment\HasFormattedName;
use App\Models\Traits\Shipment\HasPartSelection;
use App\Models\Traits\Shipment\HasSearchableRelations;
use App\Models\Traits\Shipment\Relationships as ExclusiveRelationships;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model implements HasDocumentChecklist
{
    use ExclusiveRelationships,
        HasCustomAttributes,
        HasFactory,
        HasFormattedName,
        HasPartSelection,
        HasSearchableRelations,
        Relationships,
        SoftDeletes,
        UserStamps;

    public const SCANNABLE_TABLE = 'shipments';

    public const SCANNABLE_IDENTIFIER = 'shipment_no';

    public const TYPE_SHIPMENT_STATUS = 'Shipment Status';

    public const TYPE_CONTAINER_STATUS = 'Container Status';

    public const TYPE_OPERATION_STATUS = 'Operation Status';

    public const TYPE_TRACKING_STATUS = 'Tracking Status';

    public const TYPE_DOC_STATUS = 'Documentation Status';

    protected $fillable = [
        'registered_order_id',
        'shipment_no',
        'company_id',
        'part',
        'contract_no',
        'warehouse_date',
        'exit_date',
        'remittance_amount',
        'customs_quantity',
        'shipped_quantity',
        'bl_number',
        'booking_no',
        'eta',
        'etd',
        'container_no',
        'container_type',
        'container_status_id',
        'operation_status_id',
        'shipment_status_id',
        'status_id',
        'doc_status_id',
        'docs',
        'notes',
        'user_id',
        'updated_by_id',
    ];

    protected $casts = [
        'warehouse_date' => 'date',
        'exit_date' => 'date',
        'eta' => 'date',
        'etd' => 'date',
        'remittance_amount' => 'decimal:2',
        'customs_quantity' => 'decimal:2',
        'shipped_quantity' => 'decimal:2',
        'docs' => 'array',
    ];

    public function documentChecklist(): array
    {
        $docs = $this->docs ?? [];

        return array_is_list($docs) ? $docs : ($docs['items'] ?? []);
    }

    public function documentChecklistOptions(): string
    {
        return 'resources/shipment/strings.form.docs_options';
    }

    public function isDocumentTrackingEnabled(): bool
    {
        $track = collect($this->documentChecklist())->firstWhere('name', 'track');

        return $track === null ? true : (bool) ($track['received'] ?? false);
    }

    public function setDocumentChecklist(array $rows): void
    {
        $this->docs = array_values($rows);
        $this->saveQuietly();
    }
}
