<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\Imports;

use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\ItemCalculation;
use App\Filament\Traits\ImportDefaults;
use App\Models\Product;
use App\Models\RegisteredOrder;
use App\Models\RegisteredOrderItem;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Importer;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class RegisteredOrderItemImporter extends Importer
{
    use ImportDefaults, ItemCalculation;

    protected static ?string $model = RegisteredOrderItem::class;

    protected ?RegisteredOrder $parent = null;

    protected array $seenProductIds = [];

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = RegisteredOrderImporter::COLUMN_LABEL_KEYS;

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
            ImportColumnDefinition::optional('entrance_fee', $labels['entrance_fee'], isNumber: true),
            ImportColumnDefinition::optional('shipping_cost', $labels['shipping_cost'], isNumber: true),
            ImportColumnDefinition::optional('extra_cost', $labels['extra_cost'], isNumber: true),
            ImportColumnDefinition::optional('packing_details', $labels['packing_details']),
            ImportColumnDefinition::optional('description', $labels['description']),
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

    public function forParent(RegisteredOrder $parent): static
    {
        $this->parent = $parent;
        $this->seenProductIds = [];

        return $this;
    }

    public function resolveRecord(): ?Model
    {
        if (! $this->parent) {
            throw new LogicException('RegisteredOrderItemImporter must be given a parent via forParent() before use.');
        }

        $productValue = $this->data['product_id'] ?? null;

        if (blank($productValue)) {
            throw new RowImportFailedException(__('resources/general/strings.import.lookup_not_found', [
                'label' => __('resources/registeredOrder/strings.form.product'),
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
                'label' => __('resources/registeredOrder/strings.form.product'),
                'value' => $productValue,
            ]));
        }

        if (array_key_exists($product->id, $this->seenProductIds)) {
            throw new RowImportFailedException(__('resources/general/strings.import.duplicate_product_in_group', ['value' => $productValue]));
        }

        $this->seenProductIds[$product->id] = true;

        $item = RegisteredOrderItem::withTrashed()
            ->where('registered_order_id', $this->parent->id)
            ->where('product_id', $product->id)
            ->first();

        if ($item?->trashed()) {
            throw new RowImportFailedException(__('resources/general/strings.import.record_trashed', ['value' => $productValue]));
        }

        $item ??= new RegisteredOrderItem;
        $item->registered_order_id = $this->parent->id;

        return $item;
    }

    public function afterFill(): void
    {
        $context = new ImportRowContext($this->record, $this->options);
        $context->columns = static::importColumns();

        ImportPipeline::runBeforeSave($context);

        $this->record->line_total = static::computeItemLineTotalFromState([
            'quantity' => $this->record->quantity,
            'unit_price' => $this->record->unit_price,
            'shipping_cost' => $this->record->shipping_cost,
            'extra_cost' => $this->record->extra_cost,
        ]);
    }
}
