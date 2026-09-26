<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\Status;
use App\Models\User;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportDefaultsShared;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use App\Services\Imports\LocalizedMatcher;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;

class PurchaseRequestImporter extends Importer implements Importable
{
    use ImportDefaults;

    private const ITEM_COLUMN_MAP = [
        'item_status_id' => 'status_id',
        'item_notes' => 'notes',
    ];

    public const COLUMN_LABEL_KEYS = [
        'pr_number' => 'resources/purchaseRequest/strings.form.pr_number',
        'requester_id' => 'resources/purchaseRequest/strings.form.requester',
        'department_id' => 'resources/purchaseRequest/strings.form.department',
        'cost_center_id' => 'resources/purchaseRequest/strings.form.cost_center',
        'required_by_date' => 'resources/purchaseRequest/strings.form.required_by_date',
        'urgency_level' => 'resources/purchaseRequest/strings.form.urgency_level',
        'total_estimated_cost' => 'resources/purchaseRequest/strings.form.total_estimated_cost',
        'status_id' => 'resources/purchaseRequest/strings.form.status',
        'notes' => 'resources/purchaseRequest/strings.form.notes',
        'product_id' => 'resources/purchaseRequest/strings.form.product',
        'quantity' => 'resources/purchaseRequest/strings.form.quantity',
        'unit' => 'resources/target/strings.form.metrics',
        'estimated_cost' => 'resources/purchaseRequest/strings.form.estimated_cost',
        'item_status_id' => 'resources/purchaseRequest/strings.form.item_status',
        'item_notes' => 'resources/purchaseRequest/strings.form.item_notes',
    ];

    protected static ?string $model = PurchaseRequest::class;

    protected array $pendingItemRows = [];

    protected array $pendingExtraAttributes = [];

    protected ?PurchaseRequestItemImporter $itemImporter = null;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        return [
            ImportColumnDefinition::optional('pr_number', self::COLUMN_LABEL_KEYS['pr_number']),

            ImportColumnDefinition::match('requester_id', self::COLUMN_LABEL_KEYS['requester_id'], 'requester', User::class, ['name', 'email'])
                ->withFallback(fn (ImportRowContext $ctx) => auth()->id(), includeInTemplate: false),

            ImportColumnDefinition::match('department_id', self::COLUMN_LABEL_KEYS['department_id'], 'department', Department::class, ['name', 'english_name'])
                ->withFallback(fn (ImportRowContext $ctx) => $ctx->record->requester?->department_id, includeInTemplate: false, rejectIfStillBlank: true),

            ImportColumnDefinition::match('cost_center_id', self::COLUMN_LABEL_KEYS['cost_center_id'], 'costCenter', Department::class, ['name', 'english_name'])
                ->allowNullOnMismatch()
                ->withFallback(fn (ImportRowContext $ctx) => $ctx->record->department_id),

            ImportColumnDefinition::optional('required_by_date', self::COLUMN_LABEL_KEYS['required_by_date'], isDate: true)
                ->withFallback(fn (ImportRowContext $ctx) => now()->addMonth()->format('Y-m-d')),

            ImportColumnDefinition::matchEnum('urgency_level', self::COLUMN_LABEL_KEYS['urgency_level'], 'resources/purchaseRequest/strings.general.urgency'),

            ImportColumnDefinition::optional('total_estimated_cost', self::COLUMN_LABEL_KEYS['total_estimated_cost'], isNumber: true),

            ImportColumnDefinition::matchStatus('status_id', self::COLUMN_LABEL_KEYS['status_id'], PurchaseRequest::TYPE_PURCHASE_REQUEST)
                ->withFallback(fn (ImportRowContext $ctx) => Status::findBy(PurchaseRequest::TYPE_PURCHASE_REQUEST, 'Under Review')?->id),

            ImportColumnDefinition::optional('notes', self::COLUMN_LABEL_KEYS['notes']),
        ];
    }

    public static function getColumns(): array
    {
        $itemColumns = collect(PurchaseRequestItemImporter::getColumns())
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
            ...(static::eavEnabled() ? ImportDefaultsShared::extraAttributeColumns() : []),
        ]);
    }

    public static function filledExamplePath(): ?string
    {
        return 'import-examples/purchase-request-filled-example.csv';
    }

    public static function dateDefaultsSummary(): ?string
    {
        return __('resources/purchaseRequest/strings.import.date_defaults');
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
        return true;
    }

    public function beforeCreate(): void
    {
        $this->fillReservedNumberIfBlank('pr_number');
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

        foreach (['product_id', 'quantity', 'unit', 'estimated_cost'] as $itemColumn) {
            unset($this->data[$itemColumn]);
        }

        $this->pendingExtraAttributes = ImportDefaultsShared::extraAttributeMapFromData($this->data);

        for ($i = 1; $i <= 5; $i++) {
            unset($this->data["extra_key_{$i}"], $this->data["extra_value_{$i}"]);
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
        $context->pendingExtraAttributes = $this->pendingExtraAttributes;

        $this->pendingItemRows = [];
        $this->pendingExtraAttributes = [];

        ImportPipeline::runAfterSave($context);
    }

    public function childImporterInstance(): PurchaseRequestItemImporter
    {
        return $this->itemImporter ??= new PurchaseRequestItemImporter(
            $this->import,
            static::mapItemColumnMap($this->columnMap),
            $this->options,
        );
    }

    public function recalculateAfterPersist(): void
    {
        $items = $this->record->items()->get(['quantity', 'estimated_cost']);

        if ($items->every(fn ($item) => blank($item->estimated_cost))) {
            return;
        }

        $total = $items->sum(fn ($item) => (float) ($item->quantity ?? 0) * (float) ($item->estimated_cost ?? 0));

        $this->record->forceFill(['total_estimated_cost' => $total])->save();
    }

    protected static function mapItemColumnMap(array $columnMap): array
    {
        $itemColumnMap = [];

        foreach (PurchaseRequestItemImporter::getColumns() as $column) {
            $name = $column->getName();
            $mergedName = array_search($name, self::ITEM_COLUMN_MAP, true) ?: $name;
            $itemColumnMap[$name] = $columnMap[$mergedName] ?? null;
        }

        return $itemColumnMap;
    }
}
