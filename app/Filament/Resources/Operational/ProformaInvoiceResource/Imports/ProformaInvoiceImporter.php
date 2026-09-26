<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Company;
use App\Models\Currency;
use App\Models\ProformaInvoice;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use App\Services\Imports\LocalizedMatcher;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

class ProformaInvoiceImporter extends Importer implements Importable
{
    use ImportDefaults;

    private const ITEM_COLUMN_MAP = [
        'item_freight_charges' => 'freight_charges',
        'item_total_amount' => 'total_amount',
    ];

    public const COLUMN_LABEL_KEYS = [
        'invoice_no' => 'resources/proformaInvoice/strings.form.invoice_no',
        'invoice_date' => 'resources/proformaInvoice/strings.form.invoice_date',
        'contract_no' => 'resources/proformaInvoice/strings.form.contract_no',
        'buyer_comm_card_num' => 'resources/proformaInvoice/strings.form.buyer_comm_card_num',
        'seller_id' => 'resources/proformaInvoice/strings.form.seller_company',
        'buyer_id' => 'resources/proformaInvoice/strings.form.buyer_company',
        'validity_date' => 'resources/proformaInvoice/strings.form.validity_date',
        'beneficiary_country' => 'resources/proformaInvoice/strings.form.beneficiary_country',
        'origin_country' => 'resources/proformaInvoice/strings.form.origin_country',
        'destination_country' => 'resources/proformaInvoice/strings.form.destination_country',
        'transport_mode' => 'resources/proformaInvoice/strings.form.transport_mode',
        'port_of_discharge' => 'resources/proformaInvoice/strings.form.port_of_discharge',
        'port_of_loading' => 'resources/proformaInvoice/strings.form.port_of_loading',
        'delivery_terms' => 'resources/proformaInvoice/strings.form.delivery_terms',
        'main_currency_id' => 'resources/proformaInvoice/strings.form.main_currency',
        'secondary_currency_id' => 'resources/proformaInvoice/strings.form.secondary_currency',
        'discount' => 'resources/proformaInvoice/strings.form.discount',
        'freight_charges' => 'resources/proformaInvoice/strings.form.freight_charges',
        'other_charges' => 'resources/proformaInvoice/strings.form.other_charges',
        'total_amount' => 'resources/proformaInvoice/strings.form.total_amount',
        'notes' => 'resources/proformaInvoice/strings.form.notes',
        'product_id' => 'resources/proformaInvoice/strings.form.product',
        'origin' => 'resources/proformaInvoice/strings.form.origin',
        'hs_code' => 'resources/proformaInvoice/strings.form.hs_code',
        'unit' => 'resources/proformaInvoice/strings.form.unit',
        'quantity' => 'resources/proformaInvoice/strings.form.quantity',
        'unit_price' => 'resources/proformaInvoice/strings.form.unit_price',
        'net_weight' => 'resources/proformaInvoice/strings.form.net_weight',
        'gross_weight' => 'resources/proformaInvoice/strings.form.gross_weight',
        'item_freight_charges' => 'resources/proformaInvoice/strings.form.item_freight_charges',
        'item_total_amount' => 'resources/proformaInvoice/strings.form.item_total_amount',
        'description' => 'resources/proformaInvoice/strings.form.item_description',
    ];

    protected static ?string $model = ProformaInvoice::class;

    protected array $pendingItemRows = [];

    protected ?ProformaInvoiceItemImporter $itemImporter = null;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::optional('invoice_no', $labels['invoice_no']),
            ImportColumnDefinition::optional('invoice_date', $labels['invoice_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->toDateString()),
            ImportColumnDefinition::optional('contract_no', $labels['contract_no']),
            ImportColumnDefinition::optional('buyer_comm_card_num', $labels['buyer_comm_card_num']),
            ImportColumnDefinition::match('seller_id', $labels['seller_id'], 'sellerCompany', Company::class, ['name', 'english_name']),
            ImportColumnDefinition::match('buyer_id', $labels['buyer_id'], 'buyerCompany', Company::class, ['name', 'english_name']),
            ImportColumnDefinition::optional('validity_date', $labels['validity_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->addWeeks(2)->format('Y-m-d')),
            ImportColumnDefinition::matchCountry('beneficiary_country', $labels['beneficiary_country'])->allowNullOnMismatch(),
            ImportColumnDefinition::matchCountry('origin_country', $labels['origin_country'])->allowNullOnMismatch(),
            ImportColumnDefinition::matchCountry('destination_country', $labels['destination_country'])->allowNullOnMismatch(),
            ImportColumnDefinition::matchEnum('transport_mode', $labels['transport_mode'], 'resources/proformaInvoice/strings.general.transport_modes')->allowNullOnMismatch(),
            ImportColumnDefinition::optional('port_of_discharge', $labels['port_of_discharge']),
            ImportColumnDefinition::optional('port_of_loading', $labels['port_of_loading']),
            ImportColumnDefinition::matchEnum('delivery_terms', $labels['delivery_terms'], 'resources/proformaInvoice/strings.general.delivery_terms')->allowNullOnMismatch(),
            ImportColumnDefinition::match('main_currency_id', $labels['main_currency_id'], 'mainCurrency', Currency::class, ['name', 'english_name'])
                ->withFallback(fn (ImportRowContext $ctx) => null, rejectIfStillBlank: true),
            ImportColumnDefinition::match('secondary_currency_id', $labels['secondary_currency_id'], 'secondaryCurrency', Currency::class, ['name', 'english_name'])->allowNullOnMismatch(),
            ImportColumnDefinition::optional('discount', $labels['discount'], isNumber: true),
            ImportColumnDefinition::optional('freight_charges', $labels['freight_charges'], isNumber: true),
            ImportColumnDefinition::optional('other_charges', $labels['other_charges'], isNumber: true),
            ImportColumnDefinition::optional('total_amount', $labels['total_amount'], isNumber: true),
            ImportColumnDefinition::optional('notes', $labels['notes']),
        ];
    }

    public static function getColumns(): array
    {
        $itemColumns = collect(ProformaInvoiceItemImporter::getColumns())
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
            ...$itemColumns,
        ]);
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
        return 'import-examples/proforma-invoice-filled-example.csv';
    }

    public static function dateDefaultsSummary(): ?string
    {
        return __('resources/proformaInvoice/strings.import.date_defaults');
    }

    public function beforeCreate(): void
    {
        $this->fillReservedNumberIfBlank('invoice_no');
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

        foreach (['product_id', 'origin', 'hs_code', 'unit', 'quantity', 'unit_price', 'net_weight', 'gross_weight', 'description'] as $itemColumn) {
            unset($this->data[$itemColumn]);
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

        $this->pendingItemRows = [];

        ImportPipeline::runAfterSave($context);
    }

    public function childImporterInstance(): ProformaInvoiceItemImporter
    {
        return $this->itemImporter ??= new ProformaInvoiceItemImporter(
            $this->import,
            static::mapItemColumnMap($this->columnMap),
            $this->options,
        );
    }

    public function recalculateAfterPersist(): void {}

    protected static function mapItemColumnMap(array $columnMap): array
    {
        $itemColumnMap = [];

        foreach (ProformaInvoiceItemImporter::getColumns() as $column) {
            $name = $column->getName();
            $mergedName = array_search($name, self::ITEM_COLUMN_MAP, true) ?: $name;
            $itemColumnMap[$name] = $columnMap[$mergedName] ?? null;
        }

        return $itemColumnMap;
    }
}
