<?php

namespace App\Filament\Resources\Master\ProductResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Resources\ProductResource;
use App\Filament\Traits\ImportDefaults;
use App\Models\Category;
use App\Models\Product;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use App\Services\Imports\LocalizedMatcher;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Lang;

class ProductImporter extends Importer implements Importable
{
    use ImportDefaults;

    public const COLUMN_LABEL_KEYS = [
        'code' => 'resources/product/strings.form.code',
        'category_id' => 'resources/product/strings.form.category',
        'name' => 'resources/product/strings.form.name',
        'english_name' => 'resources/product/strings.form.english_name',
        'attributes' => 'resources/product/strings.form.attributes',
        'description' => 'resources/product/strings.form.description',
        'in_stock' => 'resources/product/strings.form.in_stock',
        'is_active' => 'resources/product/strings.form.is_active',
        'hs_code' => 'resources/product/strings.form.hs_code',
        'import_duty' => 'resources/product/strings.form.import_duty',
        'packing_type' => 'resources/product/strings.form.packing_type',
        'vat_exempt' => 'resources/product/strings.form.vat_exempt',
        'tax_id' => 'resources/product/strings.form.tax_id',
        'manufacturer' => 'resources/product/strings.form.manufacturer',
        'import_licenses' => 'resources/product/strings.form.import_licenses',
        'extra' => 'resources/product/strings.form.extra',
    ];

    protected static ?string $model = Product::class;

    protected array $pendingSpecification = [];

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::manualSet('code', $labels['code']),
            ImportColumnDefinition::match('category_id', $labels['category_id'], 'category', Category::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
            ImportColumnDefinition::optional('name', $labels['name']),
            ImportColumnDefinition::optional('english_name', $labels['english_name']),
            ImportColumnDefinition::optional('description', $labels['description']),
            ImportColumnDefinition::boolean('in_stock', $labels['in_stock'])
                ->withFallback(fn () => true, rejectIfStillBlank: false),
            ImportColumnDefinition::boolean('is_active', $labels['is_active'])
                ->withFallback(fn () => true, rejectIfStillBlank: false),
        ];
    }

    public static function getColumns(): array
    {
        return static::assertColumnNamesAllowed([
            ...array_map(fn (ImportColumnDefinition $definition) => ImportColumnFactory::build($definition), static::importColumns()),
            static::attributesColumn(),
            ...static::specificationColumns(),
        ]);
    }

    protected static function attributesColumn(): ImportColumn
    {
        $labelKey = self::COLUMN_LABEL_KEYS['attributes'];
        $label = LocalizedMatcher::columnLabel('attributes', $labelKey);

        return ImportColumn::make('attributes')
            ->label($label)
            ->exampleHeader($label)
            ->guess(LocalizedMatcher::localizedGuesses($labelKey))
            ->ignoreBlankState()
            ->castStateUsing(fn ($state) => is_string($state)
                ? array_values(array_filter(array_map('trim', explode(',', $state)), fn ($piece) => $piece !== ''))
                : $state);
    }

    /**
     * @return ImportColumn[]
     */
    protected static function specificationColumns(): array
    {
        return collect(['hs_code', 'import_duty', 'packing_type', 'vat_exempt', 'tax_id', 'manufacturer', 'import_licenses', 'extra'])
            ->map(function (string $name) {
                $labelKey = self::COLUMN_LABEL_KEYS[$name];
                $label = LocalizedMatcher::columnLabel($name, $labelKey);

                return ImportColumn::make($name)
                    ->label($label)
                    ->exampleHeader($label)
                    ->guess(LocalizedMatcher::localizedGuesses($labelKey))
                    ->ignoreBlankState()
                    ->castStateUsing(fn ($state) => is_string($state) ? trim($state) : $state);
            })
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

    public static function filledExamplePath(): ?string
    {
        return 'import-examples/product-filled-example.csv';
    }

    public function resolveRecord(): ?Model
    {
        $code = Product::normalizeCode($this->data['code'] ?? null);

        if (blank($code)) {
            throw new RowImportFailedException(__('resources/product/strings.import.code_required'));
        }

        if (Product::where('code', $code)->exists()) {
            throw new RowImportFailedException(__('resources/product/strings.import.duplicate_code', ['code' => $code]));
        }

        $this->assertRequiredColumnsForNewRecordsPresent();

        return new Product;
    }

    public function beforeFill(): void
    {
        $extraRaw = $this->readRaw('extra');

        $this->pendingSpecification = [
            'hs_code' => $this->readRaw('hs_code'),
            'import_duty' => $this->readRaw('import_duty'),
            'packing_type' => $this->readRaw('packing_type'),
            'vat_exempt' => static::parseBoolean($this->readRaw('vat_exempt'), false),
            'tax_id' => $this->readRaw('tax_id'),
            'manufacturer' => $this->readRaw('manufacturer'),
            'import_licenses' => static::parseImportLicenses($this->readRaw('import_licenses')),
            'extra' => $this->parseExtra($extraRaw),
        ];

        foreach (['hs_code', 'import_duty', 'packing_type', 'vat_exempt', 'tax_id', 'manufacturer', 'import_licenses', 'extra'] as $field) {
            unset($this->data[$field]);
        }
    }

    protected function readRaw(string $field): ?string
    {
        $value = $this->data[$field] ?? null;

        return is_string($value) ? trim($value) : $value;
    }

    protected static function parseBoolean(?string $value, bool $default): bool
    {
        if (blank($value)) {
            return $default;
        }

        return match (mb_strtolower(trim($value))) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }

    protected static function parseImportLicenses(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $allLicenses = Lang::get('resources/product/strings.form.licenses');

        return collect(explode(',', $value))
            ->map(fn ($piece) => trim($piece))
            ->filter(fn ($piece) => $piece !== '')
            ->map(fn ($piece) => array_key_exists($piece, $allLicenses) ? $piece : (array_search($piece, $allLicenses, true) ?: $piece))
            ->unique()
            ->values()
            ->all();
    }

    protected function parseExtra(?string $value): array
    {
        if (blank($value)) {
            return [];
        }

        $pairs = [];
        $skipped = [];

        foreach (explode('|', $value) as $piece) {
            $piece = trim($piece);

            if ($piece === '') {
                continue;
            }

            $colon = strpos($piece, ':');

            if ($colon === false || trim(substr($piece, 0, $colon)) === '') {
                $skipped[] = $piece;

                continue;
            }

            $key = trim(substr($piece, 0, $colon));
            $pairs[$key] = trim(substr($piece, $colon + 1));
        }

        if ($skipped) {
            $lines = collect($skipped)->map(fn ($piece) => __('resources/product/strings.import.extra_pair_skipped', ['pair' => $piece]));
            $this->record->notes = trim(implode("\n", array_filter([$this->record->notes, ...$lines])));
        }

        return $pairs;
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

        if (ProductResource::specificationHasData($this->pendingSpecification)) {
            $this->record->specifications()->updateOrCreate([], $this->pendingSpecification);
        }
    }

    public function recalculateAfterPersist(): void {}
}
