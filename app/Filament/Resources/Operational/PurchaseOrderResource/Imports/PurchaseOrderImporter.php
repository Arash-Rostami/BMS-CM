<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Imports;

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

class PurchaseOrderImporter extends Importer implements Importable
{
    use ImportDefaults;

    private const ITEM_COLUMN_MAP = [];

    private const PIVOT_CONFIG = [
        'pr_numbers' => ['relation' => 'purchaseRequests', 'model' => PurchaseRequest::class, 'identifierColumn' => 'pr_number'],
        'invoice_nos' => ['relation' => 'proformaInvoices', 'model' => ProformaInvoice::class, 'identifierColumn' => 'invoice_no'],
        'ro_numbers' => ['relation' => 'registeredOrders', 'model' => RegisteredOrder::class, 'identifierColumn' => 'ro_number'],
    ];

    public const COLUMN_LABEL_KEYS = [
        'po_number' => 'resources/purchaseOrder/strings.form.po_number',
        'seller_id' => 'resources/purchaseOrder/strings.form.seller',
        'buyer_id' => 'resources/purchaseOrder/strings.form.buyer',
        'status_id' => 'resources/purchaseOrder/strings.form.status',
        'order_date' => 'resources/purchaseOrder/strings.form.order_date',
        'validity_date' => 'resources/purchaseOrder/strings.form.validity_date',
        'expected_delivery_date' => 'resources/purchaseOrder/strings.form.expected_delivery_date',
        'incoterms' => 'resources/purchaseOrder/strings.form.incoterms',
        'shipping_address' => 'resources/purchaseOrder/strings.form.shipping_address',
        'packing_details' => 'resources/purchaseOrder/strings.form.packing_details',
        'currency_id' => 'resources/purchaseOrder/strings.form.currency',
        'notes' => 'resources/purchaseOrder/strings.form.notes',
        'pr_numbers' => 'resources/purchaseOrder/strings.import.pr_numbers',
        'invoice_nos' => 'resources/purchaseOrder/strings.import.invoice_nos',
        'ro_numbers' => 'resources/purchaseOrder/strings.import.ro_numbers',
        'product_id' => 'resources/purchaseOrder/strings.form.product',
        'quantity' => 'resources/purchaseOrder/strings.form.quantity',
        'unit' => 'resources/purchaseOrder/strings.form.unit',
        'unit_price' => 'resources/purchaseOrder/strings.form.unit_price',
        'net_weight' => 'resources/purchaseOrder/strings.form.net_weight',
        'gross_weight' => 'resources/purchaseOrder/strings.form.gross_weight',
        'item_description' => 'resources/purchaseOrder/strings.form.item_description',
    ];

    protected static ?string $model = PurchaseOrder::class;

    protected array $pendingItemRows = [];

    protected array $pendingPivotAttach = [];

    protected ?PurchaseOrderItemImporter $itemImporter = null;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::optional('po_number', $labels['po_number']),
            ImportColumnDefinition::match('seller_id', $labels['seller_id'], 'sellerCompany', Company::class, ['name', 'english_name'])
                ->withFallback(fn (ImportRowContext $ctx) => null, rejectIfStillBlank: true),
            ImportColumnDefinition::match('buyer_id', $labels['buyer_id'], 'buyerCompany', Company::class, ['name', 'english_name'])
                ->withFallback(fn (ImportRowContext $ctx) => null, rejectIfStillBlank: true),
            ImportColumnDefinition::matchStatus('status_id', $labels['status_id'], PurchaseOrder::TYPE_PURCHASE_ORDER)
                ->withFallback(fn (ImportRowContext $ctx) => Status::findBy(PurchaseOrder::TYPE_PURCHASE_ORDER, 'Submitted')?->id, rejectIfStillBlank: true),
            ImportColumnDefinition::optional('order_date', $labels['order_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->toDateString()),
            ImportColumnDefinition::optional('validity_date', $labels['validity_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->addWeeks(2)->format('Y-m-d')),
            ImportColumnDefinition::optional('expected_delivery_date', $labels['expected_delivery_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->addMonth()->format('Y-m-d')),
            ImportColumnDefinition::matchEnum('incoterms', $labels['incoterms'], 'resources/purchaseOrder/strings.general.delivery_terms')->allowNullOnMismatch(),
            ImportColumnDefinition::optional('shipping_address', $labels['shipping_address']),
            ImportColumnDefinition::optional('packing_details', $labels['packing_details']),
            ImportColumnDefinition::match('currency_id', $labels['currency_id'], 'currency', Currency::class, ['name', 'english_name'])
                ->withFallback(fn (ImportRowContext $ctx) => null, rejectIfStillBlank: true),
            ImportColumnDefinition::optional('notes', $labels['notes']),
        ];
    }

    public static function getColumns(): array
    {
        $itemColumns = collect(PurchaseOrderItemImporter::getColumns())
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
        return __('resources/purchaseOrder/strings.import.date_defaults');
    }

    public static function filledExamplePath(): ?string
    {
        return 'import-examples/purchase-order-filled-example.csv';
    }

    public function beforeCreate(): void
    {
        $this->fillReservedNumberIfBlank('po_number');
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

        foreach (['product_id', 'quantity', 'unit', 'unit_price', 'net_weight', 'gross_weight', 'description'] as $itemColumn) {
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

    public function childImporterInstance(): PurchaseOrderItemImporter
    {
        return $this->itemImporter ??= new PurchaseOrderItemImporter(
            $this->import,
            static::mapItemColumnMap($this->columnMap),
            $this->options,
        );
    }

    public function recalculateAfterPersist(): void {}

    protected static function mapItemColumnMap(array $columnMap): array
    {
        $itemColumnMap = [];

        foreach (PurchaseOrderItemImporter::getColumns() as $column) {
            $name = $column->getName();
            $mergedName = array_search($name, self::ITEM_COLUMN_MAP, true) ?: $name;
            $itemColumnMap[$name] = $columnMap[$mergedName] ?? null;
        }

        return $itemColumnMap;
    }
}
