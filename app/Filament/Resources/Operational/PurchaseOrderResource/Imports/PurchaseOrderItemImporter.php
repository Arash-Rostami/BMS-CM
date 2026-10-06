<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\Imports;

use App\Filament\Traits\ImportDefaults;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Importer;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PurchaseOrderItemImporter extends Importer
{
    use ImportDefaults;

    protected static ?string $model = PurchaseOrderItem::class;

    protected ?PurchaseOrder $parent = null;

    protected array $seenProductIds = [];

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = PurchaseOrderImporter::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::match('product_id', $labels['product_id'], 'product', Product::class, ['name', 'english_name', 'code']),
            ImportColumnDefinition::optional('quantity', $labels['quantity'], isNumber: true)
                ->withFallback(fn (ImportRowContext $ctx) => null, rejectIfStillBlank: true),
            ImportColumnDefinition::matchEnum('unit', $labels['unit'], 'resources/general/strings.metrics')
                ->withFallback(fn (ImportRowContext $ctx) => null, rejectIfStillBlank: true),
            ImportColumnDefinition::optional('unit_price', $labels['unit_price'], isNumber: true)
                ->withFallback(fn (ImportRowContext $ctx) => null, rejectIfStillBlank: true),
            ImportColumnDefinition::optional('net_weight', $labels['net_weight'], isNumber: true),
            ImportColumnDefinition::optional('gross_weight', $labels['gross_weight'], isNumber: true),
            ImportColumnDefinition::optional('description', $labels['item_description']),
        ];
    }

    public static function getColumns(): array
    {
        $columns = array_map(
            fn (ImportColumnDefinition $definition) => ImportColumnFactory::build($definition),
            static::importColumns(),
        );

        $columns[0] = $columns[0]->requiredMapping();

        return static::assertColumnNamesAllowed($columns);
    }

    public function forParent(PurchaseOrder $parent): static
    {
        $this->parent = $parent;
        $this->seenProductIds = [];

        return $this;
    }

    public function resolveRecord(): ?Model
    {
        if (! $this->parent) {
            throw new LogicException('PurchaseOrderItemImporter must be given a parent via forParent() before use.');
        }

        $productValue = $this->data['product_id'] ?? null;

        if (blank($productValue)) {
            throw new RowImportFailedException(__('resources/general/strings.import.lookup_not_found', [
                'label' => __('resources/purchaseOrder/strings.form.product'),
                'value' => '',
            ]));
        }

        $product = Product::query()
            ->where('name', $productValue)
            ->orWhere('english_name', $productValue)
            ->orWhere('code', $productValue)
            ->first();

        if (! $product) {
            throw new RowImportFailedException(__('resources/general/strings.import.lookup_not_found', [
                'label' => __('resources/purchaseOrder/strings.form.product'),
                'value' => $productValue,
            ]));
        }

        if (array_key_exists($product->id, $this->seenProductIds)) {
            throw new RowImportFailedException(__('resources/general/strings.import.duplicate_product_in_group', ['value' => $productValue]));
        }

        $this->seenProductIds[$product->id] = true;

        $item = PurchaseOrderItem::withTrashed()
            ->where('purchase_order_id', $this->parent->id)
            ->where('product_id', $product->id)
            ->first();

        if ($item?->trashed()) {
            throw new RowImportFailedException(__('resources/general/strings.import.record_trashed', ['value' => $productValue]));
        }

        $item ??= new PurchaseOrderItem;
        $item->purchase_order_id = $this->parent->id;

        return $item;
    }

    public function afterFill(): void
    {
        $context = new ImportRowContext($this->record, $this->options);
        $context->columns = static::importColumns();

        ImportPipeline::runBeforeSave($context);
    }
}
