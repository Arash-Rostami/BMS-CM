<?php

namespace App\Filament\Resources\Operational\CustomResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Custom;
use App\Models\Shipment;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use Filament\Actions\Imports\Importer;

class CustomImporter extends Importer implements Importable
{
    use ImportDefaults;

    public const COLUMN_LABEL_KEYS = [
        'custom_no' => 'resources/custom/strings.form.custom_no',
        'shipment_id' => 'resources/custom/strings.form.shipment',
        'declaration_no' => 'resources/custom/strings.form.declaration_no',
        'clearance_type' => 'resources/custom/strings.form.clearance_type',
        'commitment_balance' => 'resources/custom/strings.form.commitment_balance',
        'clearance_date' => 'resources/custom/strings.form.clearance_date',
        'doc_submission_date' => 'resources/custom/strings.form.doc_submission_date',
        'ten_percent_exit_date' => 'resources/custom/strings.form.ten_percent_exit_date',
        'rial_return_date' => 'resources/custom/strings.form.rial_return_date',
        'clearance_status_id' => 'resources/custom/strings.form.clearance_status',
        'bank_guarantee_status_id' => 'resources/custom/strings.form.bank_guarantee_status',
        'commitment_status_id' => 'resources/custom/strings.form.commitment_status',
        'notes' => 'resources/custom/strings.form.notes',
    ];

    protected static ?string $model = Custom::class;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::optional('custom_no', $labels['custom_no']),
            ImportColumnDefinition::match('shipment_id', $labels['shipment_id'], 'shipment', Shipment::class, ['shipment_no'])
                ->withFallback(fn () => null, rejectIfStillBlank: true),
            ImportColumnDefinition::optional('declaration_no', $labels['declaration_no']),
            ImportColumnDefinition::matchEnum('clearance_type', $labels['clearance_type'], 'resources/custom/strings.general.clearance_types')
                ->allowNullOnMismatch(),
            ImportColumnDefinition::optional('commitment_balance', $labels['commitment_balance'], isNumber: true),
            ImportColumnDefinition::optional('clearance_date', $labels['clearance_date'], isDate: true),
            ImportColumnDefinition::optional('doc_submission_date', $labels['doc_submission_date'], isDate: true),
            ImportColumnDefinition::optional('ten_percent_exit_date', $labels['ten_percent_exit_date'], isDate: true),
            ImportColumnDefinition::optional('rial_return_date', $labels['rial_return_date'], isDate: true),
            ImportColumnDefinition::matchStatus('clearance_status_id', $labels['clearance_status_id'], Custom::TYPE_CLEARANCE_STATUS)
                ->allowNullOnMismatch(),
            ImportColumnDefinition::matchStatus('bank_guarantee_status_id', $labels['bank_guarantee_status_id'], Custom::TYPE_BANK_GUARANTEE_STATUS)
                ->allowNullOnMismatch(),
            ImportColumnDefinition::matchStatus('commitment_status_id', $labels['commitment_status_id'], Custom::TYPE_COMMITMENT_STATUS)
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

    public static function filledExamplePath(): ?string
    {
        return 'import-examples/custom-filled-example.csv';
    }

    public function beforeCreate(): void
    {
        $this->fillReservedNumberIfBlank('custom_no');
    }

    public function afterFill(): void
    {
        if ($shipment = $this->record->shipment) {
            $this->record->registered_order_id = $shipment->registered_order_id;
            $this->record->contract_no = $shipment->contract_no;
        }

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
