<?php

namespace App\Models;

use App\Contracts\HasDocumentChecklist;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\Shipment\HasFormattedName;
use App\Models\Traits\Shipment\HasPartSelection;
use App\Models\Traits\Shipment\HasSearchableRelations;
use App\Models\Traits\Shipment\Relationships as ExclusiveRelationships;
use App\Services\DocChecklistMatcher;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model implements HasDocumentChecklist
{
    use ExclusiveRelationships,
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

    protected function docs(): Attribute
    {
        return Attribute::make(
            get: function (?string $value): ?array {
                if ($value === null) {
                    return null;                                  // new record -> repeater seeds defaults
                }
                $data = json_decode($value, true);
                if (! is_array($data)) {
                    return null;
                }

                return array_is_list($data) ? $data : ($data['items'] ?? []);
            },
            set: function (?array $value, array $attributes): array {
                $raw = $attributes['docs'] ?? '{"tracking":true,"items":[]}';
                $current = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
                $tracking = (is_array($current) && ! array_is_list($current))
                    ? (bool) ($current['tracking'] ?? true)
                    : true;

                return ['docs' => json_encode(['tracking' => $tracking, 'items' => array_values($value ?? [])])];
            },
        );
    }

    /** The single Smart Tracer flag, stored beside items in the same column. */
    protected function docTracking(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes): bool {
                $raw = $attributes['docs'] ?? '{"tracking":true,"items":[]}';
                $data = is_string($raw) ? (json_decode($raw, true) ?: []) : [];

                return (is_array($data) && ! array_is_list($data)) ? (bool) ($data['tracking'] ?? true) : true;
            },
            set: function ($value, array $attributes): array {
                $raw = $attributes['docs'] ?? '{"tracking":true,"items":[]}';
                $data = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
                $items = (is_array($data) && ! array_is_list($data)) ? ($data['items'] ?? []) : (is_array($data) ? $data : []);

                return ['docs' => json_encode(['tracking' => (bool) $value, 'items' => array_values($items)])];
            },
        );
    }

    protected static function booted(): void
    {
        $sync = function (Shipment $shipment): void {
            rescue(fn () => DocChecklistMatcher::sync($shipment), report: false);
        };

        static::created($sync);
        static::saved($sync);
    }

    public function documentChecklistOptions(): string
    {
        return 'resources/shipment/strings.form.docs_options';
    }

    public function documentChecklist(): array
    {
        return $this->docs ?? [];
    }

    public function setDocumentChecklist(array $rows): void
    {
        $this->docs = $rows;   // set mutator preserves the tracking key
        $this->saveQuietly();
    }

    public function isDocumentTrackingEnabled(): bool
    {
        return $this->doc_tracking;
    }
}
