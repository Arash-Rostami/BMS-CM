<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Company;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use Filament\Actions\Imports\Importer;

class ShipmentImporter extends Importer implements Importable
{
    use ImportDefaults;

    public const COLUMN_LABEL_KEYS = [
        'shipment_no' => 'resources/shipment/strings.form.shipment_no',
        'registered_order_id' => 'resources/shipment/strings.form.registered_order',
        'company_id' => 'resources/shipment/strings.form.carrier',
        'contract_no' => 'resources/shipment/strings.form.contract_no',
        'part' => 'resources/shipment/strings.form.part',
        'status_id' => 'resources/shipment/strings.form.status',
        'container_status_id' => 'resources/shipment/strings.form.container_status',
        'operation_status_id' => 'resources/shipment/strings.form.operation_status',
        'shipment_status_id' => 'resources/shipment/strings.form.shipment_status',
        'doc_status_id' => 'resources/shipment/strings.form.doc_status',
        'warehouse_date' => 'resources/shipment/strings.form.warehouse_date',
        'exit_date' => 'resources/shipment/strings.form.exit_date',
        'eta' => 'resources/shipment/strings.form.eta',
        'etd' => 'resources/shipment/strings.form.etd',
        'remittance_amount' => 'resources/shipment/strings.form.remittance_amount',
        'customs_quantity' => 'resources/shipment/strings.form.customs_quantity',
        'shipped_quantity' => 'resources/shipment/strings.form.shipped_quantity',
        'bl_number' => 'resources/shipment/strings.form.bl_number',
        'booking_no' => 'resources/shipment/strings.form.booking_no',
        'container_no' => 'resources/shipment/strings.form.container_no',
        'container_type' => 'resources/shipment/strings.form.container_type',
        'notes' => 'resources/shipment/strings.form.notes',
    ];

    protected static ?string $model = Shipment::class;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::optional('shipment_no', $labels['shipment_no']),
            ImportColumnDefinition::match('registered_order_id', $labels['registered_order_id'], 'registeredOrder', RegisteredOrder::class, ['ro_number'])
                ->withFallback(fn () => null, rejectIfStillBlank: true),
            ImportColumnDefinition::match('company_id', $labels['company_id'], 'carrier', Company::class, ['name', 'english_name'])
                ->withFallback(fn () => null, rejectIfStillBlank: true),
            ImportColumnDefinition::optional('contract_no', $labels['contract_no'])
                ->withFallback(fn (ImportRowContext $ctx) => $ctx->record->registeredOrder?->contract_no, rejectIfStillBlank: false),
            ImportColumnDefinition::optional('part', $labels['part']),
            ImportColumnDefinition::matchStatus('status_id', $labels['status_id'], Shipment::TYPE_SHIPMENT_STATUS)
                ->withFallback(fn (ImportRowContext $ctx) => Status::findBy(Shipment::TYPE_SHIPMENT_STATUS, 'Processing')?->id, rejectIfStillBlank: true),
            ImportColumnDefinition::matchStatus('container_status_id', $labels['container_status_id'], Shipment::TYPE_CONTAINER_STATUS)
                ->allowNullOnMismatch(),
            ImportColumnDefinition::matchStatus('operation_status_id', $labels['operation_status_id'], Shipment::TYPE_OPERATION_STATUS)
                ->allowNullOnMismatch(),
            ImportColumnDefinition::matchStatus('shipment_status_id', $labels['shipment_status_id'], Shipment::TYPE_TRACKING_STATUS)
                ->allowNullOnMismatch(),
            ImportColumnDefinition::matchStatus('doc_status_id', $labels['doc_status_id'], Shipment::TYPE_DOC_STATUS)
                ->allowNullOnMismatch(),
            ImportColumnDefinition::optional('warehouse_date', $labels['warehouse_date'], isDate: true),
            ImportColumnDefinition::optional('exit_date', $labels['exit_date'], isDate: true),
            ImportColumnDefinition::optional('eta', $labels['eta'], isDate: true),
            ImportColumnDefinition::optional('etd', $labels['etd'], isDate: true),
            ImportColumnDefinition::optional('remittance_amount', $labels['remittance_amount'], isNumber: true),
            ImportColumnDefinition::optional('customs_quantity', $labels['customs_quantity'], isNumber: true),
            ImportColumnDefinition::optional('shipped_quantity', $labels['shipped_quantity'], isNumber: true),
            ImportColumnDefinition::optional('bl_number', $labels['bl_number']),
            ImportColumnDefinition::optional('booking_no', $labels['booking_no']),
            ImportColumnDefinition::optional('container_no', $labels['container_no']),
            ImportColumnDefinition::matchEnum('container_type', $labels['container_type'], 'resources/shipment/strings.form.container_types')
                ->allowNullOnMismatch(),
            ImportColumnDefinition::optional('notes', $labels['notes']),
        ];
    }

    public static function getColumns(): array
    {
        return static::assertColumnNamesAllowed(array_map(
            fn (ImportColumnDefinition $definition) => ImportColumnFactory::build($definition),
            static::importColumns(),
        ));
    }

    /**
     * @return array<string, string>
     */
    public static function columnLabels(): array
    {
        return array_map(__(...), self::COLUMN_LABEL_KEYS);
    }

    public static function eavEnabled(): bool
    {
        return false;
    }

    public static function dateDefaultsSummary(): ?string
    {
        return null;
    }

    public static function filledExamplePath(): ?string
    {
        return 'import-examples/shipment-filled-example.csv';
    }

    public function beforeCreate(): void
    {
        $this->fillReservedNumberIfBlank('shipment_no');
    }

    public function afterFill(): void
    {
        $context = new ImportRowContext($this->record, [...$this->options, '__importer' => $this]);
        $context->columns = static::importColumns();
        $context->rawData = $this->buildRawDataMap();

        ImportPipeline::runBeforeSave($context);
    }

    public function afterSave(): void
    {
        $context = new ImportRowContext($this->record, [...$this->options, '__importer' => $this]);
        $context->columns = static::importColumns();

        ImportPipeline::runAfterSave($context);
    }

    public function recalculateAfterPersist(): void {}
}
