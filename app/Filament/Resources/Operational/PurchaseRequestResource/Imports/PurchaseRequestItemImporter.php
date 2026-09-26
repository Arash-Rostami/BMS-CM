<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\Imports;

use App\Filament\Traits\ImportDefaults;
use App\Models\Product;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Status;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Importer;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class PurchaseRequestItemImporter extends Importer
{
    use ImportDefaults;

    protected static ?string $model = PurchaseRequestItem::class;

    protected ?PurchaseRequest $parent = null;

    protected array $seenProductIds = [];

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = PurchaseRequestImporter::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::match('product_id', $labels['product_id'], 'product', Product::class, ['name', 'english_name', 'code']),
            ImportColumnDefinition::optional('quantity', $labels['quantity'], isNumber: true),
            ImportColumnDefinition::matchEnum('unit', $labels['unit'], 'resources/general/strings.metrics'),
            ImportColumnDefinition::optional('estimated_cost', $labels['estimated_cost'], isNumber: true),
            ImportColumnDefinition::matchStatus('status_id', $labels['item_status_id'], PurchaseRequestItem::TYPE_PURCHASE_REQUEST)
                ->withFallback(fn (ImportRowContext $ctx) => Status::findBy(PurchaseRequestItem::TYPE_PURCHASE_REQUEST, 'Under Review')?->id),
            ImportColumnDefinition::optional('notes', $labels['item_notes']),
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

    public function forParent(PurchaseRequest $parent): static
    {
        $this->parent = $parent;
        $this->seenProductIds = [];

        return $this;
    }

    public function resolveRecord(): ?Model
    {
        if (! $this->parent) {
            throw new LogicException('PurchaseRequestItemImporter must be given a parent via forParent() before use.');
        }

        $productValue = $this->data['product_id'] ?? null;

        if (blank($productValue)) {
            throw new RowImportFailedException(__('resources/general/strings.import.lookup_not_found', [
                'label' => __('resources/purchaseRequest/strings.form.product'),
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
                'label' => __('resources/purchaseRequest/strings.form.product'),
                'value' => $productValue,
            ]));
        }

        if (array_key_exists($product->id, $this->seenProductIds)) {
            throw new RowImportFailedException(__('resources/general/strings.import.duplicate_product_in_group', ['value' => $productValue]));
        }

        $this->seenProductIds[$product->id] = true;

        $item = PurchaseRequestItem::withTrashed()
            ->where('purchase_request_id', $this->parent->id)
            ->where('product_id', $product->id)
            ->first();

        if ($item?->trashed()) {
            throw new RowImportFailedException(__('resources/general/strings.import.record_trashed', ['value' => $productValue]));
        }

        $item ??= new PurchaseRequestItem;
        $item->purchase_request_id = $this->parent->id;

        return $item;
    }

    public function afterFill(): void
    {
        $context = new ImportRowContext($this->record, $this->options);
        $context->columns = static::importColumns();

        ImportPipeline::runBeforeSave($context);
    }
}
