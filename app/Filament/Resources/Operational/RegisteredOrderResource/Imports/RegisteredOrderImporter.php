<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProformaInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\RegisteredOrder;
use App\Models\Status;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use App\Services\Imports\LocalizedMatcher;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

class RegisteredOrderImporter extends Importer implements Importable
{
    use ImportDefaults;

    private const ITEM_COLUMN_MAP = [];

    private const PIVOT_CONFIG = [
        'pr_numbers' => ['relation' => 'purchaseRequests', 'model' => PurchaseRequest::class, 'identifierColumn' => 'pr_number'],
        'invoice_nos' => ['relation' => 'proformaInvoices', 'model' => ProformaInvoice::class, 'identifierColumn' => 'invoice_no'],
        'po_numbers' => ['relation' => 'purchaseOrders', 'model' => PurchaseOrder::class, 'identifierColumn' => 'po_number'],
    ];

    public const COLUMN_LABEL_KEYS = [
        'ro_number' => 'resources/registeredOrder/strings.form.ro_number',
        'contract_no' => 'resources/registeredOrder/strings.form.contract_number',
        'official_registration_no' => 'resources/registeredOrder/strings.form.official_registration_no',
        'seller_id' => 'resources/registeredOrder/strings.form.seller',
        'buyer_id' => 'resources/registeredOrder/strings.form.buyer',
        'status_id' => 'resources/registeredOrder/strings.form.status',
        'order_date' => 'resources/registeredOrder/strings.form.order_date',
        'validity_date' => 'resources/registeredOrder/strings.form.validity_date',
        'expected_delivery_date' => 'resources/registeredOrder/strings.form.expected_delivery_date',
        'incoterms' => 'resources/registeredOrder/strings.form.incoterms',
        'currency_id' => 'resources/registeredOrder/strings.form.currency',
        'currency_type' => 'resources/registeredOrder/strings.form.currency_type',
        'insurance_number' => 'resources/registeredOrder/strings.form.insurance_number',
        'insurance_provider' => 'resources/registeredOrder/strings.form.insurance_provider',
        'insurance_date' => 'resources/registeredOrder/strings.form.insurance_date',
        'notes' => 'resources/registeredOrder/strings.form.notes',
        'pr_numbers' => 'resources/registeredOrder/strings.import.pr_numbers',
        'invoice_nos' => 'resources/registeredOrder/strings.import.invoice_nos',
        'po_numbers' => 'resources/registeredOrder/strings.import.po_numbers',
        'product_id' => 'resources/registeredOrder/strings.form.product',
        'quantity' => 'resources/registeredOrder/strings.form.quantity',
        'unit' => 'resources/registeredOrder/strings.form.unit',
        'unit_price' => 'resources/registeredOrder/strings.form.unit_price',
        'net_weight' => 'resources/registeredOrder/strings.form.net_weight',
        'gross_weight' => 'resources/registeredOrder/strings.form.gross_weight',
        'entrance_fee' => 'resources/registeredOrder/strings.form.entrance_fee',
        'shipping_cost' => 'resources/registeredOrder/strings.form.shipping_cost',
        'extra_cost' => 'resources/registeredOrder/strings.form.extra_cost',
        'packing_details' => 'resources/registeredOrder/strings.form.packing_details',
        'description' => 'resources/registeredOrder/strings.form.item_description',
    ];

    protected static ?string $model = RegisteredOrder::class;

    protected array $pendingItemRows = [];

    protected array $pendingPivotAttach = [];

    protected ?RegisteredOrderItemImporter $itemImporter = null;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::optional('ro_number', $labels['ro_number']),
            ImportColumnDefinition::optional('contract_no', $labels['contract_no']),
            ImportColumnDefinition::optional('official_registration_no', $labels['official_registration_no']),
            ImportColumnDefinition::match('seller_id', $labels['seller_id'], 'sellerCompany', Company::class, ['name', 'english_name']),
            ImportColumnDefinition::match('buyer_id', $labels['buyer_id'], 'buyerCompany', Company::class, ['name', 'english_name']),
            ImportColumnDefinition::matchStatus('status_id', $labels['status_id'], RegisteredOrder::TYPE_REGISTERED_ORDER)
                ->withFallback(fn (ImportRowContext $ctx) => Status::findBy(RegisteredOrder::TYPE_REGISTERED_ORDER, 'Submitted')?->id),
            ImportColumnDefinition::optional('order_date', $labels['order_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->toDateString()),
            ImportColumnDefinition::optional('validity_date', $labels['validity_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->addWeeks(2)->format('Y-m-d')),
            ImportColumnDefinition::optional('expected_delivery_date', $labels['expected_delivery_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->addMonth()->format('Y-m-d')),
            ImportColumnDefinition::matchEnum('incoterms', $labels['incoterms'], 'resources/registeredOrder/strings.general.delivery_terms')->allowNullOnMismatch(),
            ImportColumnDefinition::match('currency_id', $labels['currency_id'], 'currency', Currency::class, ['name', 'english_name'])
                ->withFallback(fn (ImportRowContext $ctx) => null, rejectIfStillBlank: true),
            ImportColumnDefinition::matchEnum('currency_type', $labels['currency_type'], 'resources/registeredOrder/strings.general.currency_types')->allowNullOnMismatch(),
            ImportColumnDefinition::optional('insurance_number', $labels['insurance_number']),
            ImportColumnDefinition::optional('insurance_provider', $labels['insurance_provider']),
            ImportColumnDefinition::optional('insurance_date', $labels['insurance_date'], isDate: true),
            ImportColumnDefinition::optional('notes', $labels['notes']),
        ];
    }

    public static function getColumns(): array
    {
        $itemColumns = collect(RegisteredOrderItemImporter::getColumns())
            ->map(function (ImportColumn $column) {
                $renamedTo = array_search($column->getName(), self::ITEM_COLUMN_MAP, true);

                if ($renamedTo === false) {
                    return $column;
                }

                $labelKey = self::COLUMN_LABEL_KEYS[$renamedTo];

                return $column->name($renamedTo)
                    ->label(LocalizedMatcher::columnLabel($renamedTo, $labelKey))
                    ->exampleHeader(LocalizedMatcher::columnLabel($renamedTo, $labelKey))
                    ->guess(LocalizedMatcher::localizedGuesses($labelKey));
            })
            ->all();

        $ownColumns = array_map(
            fn (ImportColumnDefinition $definition) => ImportColumnFactory::build($definition),
            static::importColumns(),
        );

        return static::assertColumnNamesAllowed([
            ...$ownColumns,
            ...static::pivotColumns(),
            ...$itemColumns,
        ]);
    }

    protected static function pivotColumns(): array
    {
        return collect(self::PIVOT_CONFIG)
            ->map(function (array $config, string $name) {
                $labelKey = self::COLUMN_LABEL_KEYS[$name];
                $label = LocalizedMatcher::columnLabel($name, $labelKey);

                return ImportColumn::make($name)
                    ->label($label)
                    ->exampleHeader($label)
                    ->guess(LocalizedMatcher::localizedGuesses($labelKey))
                    ->ignoreBlankState()
                    ->castStateUsing(fn ($originalState) => is_string($originalState) ? trim($originalState) : $originalState);
            })
            ->values()
            ->all();
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
        return __('resources/registeredOrder/strings.import.date_defaults');
    }

    public static function filledExamplePath(): ?string
    {
        return 'import-examples/registered-order-filled-example.csv';
    }

    public function beforeCreate(): void
    {
        $this->fillReservedNumberIfBlank('ro_number');
        $this->fillReservedNumberIfBlank('contract_no');
    }

    public function forGroup(array $itemRows): static
    {
        $this->pendingItemRows = $itemRows;

        return $this;
    }

    public function beforeFill(): void
    {
        foreach (array_keys(self::ITEM_COLUMN_MAP) as $itemColumn) {
            unset($this->data[$itemColumn]);
        }

        foreach (['product_id', 'quantity', 'unit', 'unit_price', 'net_weight', 'gross_weight', 'entrance_fee', 'shipping_cost', 'extra_cost', 'packing_details', 'description'] as $itemColumn) {
            unset($this->data[$itemColumn]);
        }

        foreach (self::PIVOT_CONFIG as $column => $config) {
            $raw = $this->data[$column] ?? null;
            unset($this->data[$column]);

            if (blank($raw)) {
                continue;
            }

            $this->pendingPivotAttach[] = [
                'relation' => $config['relation'],
                'model' => $config['model'],
                'identifierColumn' => $config['identifierColumn'],
                'label' => __(self::COLUMN_LABEL_KEYS[$column]),
                'values' => explode(',', $raw),
            ];
        }
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
        $context->pendingChildRows = $this->pendingItemRows;
        $context->pendingPivotAttaches = $this->pendingPivotAttach;

        $this->pendingItemRows = [];
        $this->pendingPivotAttach = [];

        ImportPipeline::runAfterSave($context);
    }

    public function childImporterInstance(): RegisteredOrderItemImporter
    {
        return $this->itemImporter ??= new RegisteredOrderItemImporter(
            $this->import,
            static::mapItemColumnMap($this->columnMap),
            $this->options,
        );
    }

    public function recalculateAfterPersist(): void {}

    protected static function mapItemColumnMap(array $columnMap): array
    {
        $itemColumnMap = [];

        foreach (RegisteredOrderItemImporter::getColumns() as $column) {
            $name = $column->getName();
            $mergedName = array_search($name, self::ITEM_COLUMN_MAP, true) ?: $name;
            $itemColumnMap[$name] = $columnMap[$mergedName] ?? null;
        }

        return $itemColumnMap;
    }
}
