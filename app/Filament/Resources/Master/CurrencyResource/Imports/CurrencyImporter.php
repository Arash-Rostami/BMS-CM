<?php

namespace App\Filament\Resources\Master\CurrencyResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Currency;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use Closure;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Importer;
use Illuminate\Database\Eloquent\Model;

class CurrencyImporter extends Importer implements Importable
{
    use ImportDefaults;

    public const COLUMN_LABEL_KEYS = [
        'name' => 'resources/currency/strings.form.name',
        'english_name' => 'resources/currency/strings.form.english_name',
        'description' => 'resources/currency/strings.form.description',
        'is_active' => 'resources/currency/strings.form.is_active',
    ];

    protected static ?string $model = Currency::class;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::manualSet('name', $labels['name']),
            ImportColumnDefinition::manualSet('english_name', $labels['english_name']),
            ImportColumnDefinition::optional('description', $labels['description']),
            ImportColumnDefinition::optional('is_active', $labels['is_active'])
                ->withFallback(fn () => true, rejectIfStillBlank: false),
        ];
    }

    public static function getColumns(): array
    {
        $columns = array_map(
            fn (ImportColumnDefinition $definition) => ImportColumnFactory::build($definition),
            static::importColumns(),
        );

        foreach ($columns as $column) {
            if ($column->getName() === 'is_active') {
                $column
                    ->castStateUsing(fn ($originalState) => static::castIsActive($originalState))
                    ->rules([
                        function (string $attribute, mixed $state, Closure $fail): void {
                            if (blank($state) || is_bool($state)) {
                                return;
                            }

                            $fail(__('resources/general/strings.import.invalid_option', ['value' => $state]));
                        },
                    ]);
            }
        }

        return static::assertColumnNamesAllowed($columns);
    }

    protected static function castIsActive(mixed $originalState): mixed
    {
        if (! is_string($originalState) || trim($originalState) === '') {
            return $originalState;
        }

        return match (mb_strtolower(trim($originalState))) {
            '1', 'true', 'yes', 'y', 'active' => true,
            '0', 'false', 'no', 'n', 'inactive' => false,
            default => mb_strtolower(trim($originalState)),
        };
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
        return 'import-examples/currency-filled-example.csv';
    }

    public function resolveRecord(): ?Model
    {
        $englishName = $this->data['english_name'] ?? null;
        $name = $this->data['name'] ?? null;

        if (blank($englishName)) {
            throw new RowImportFailedException(__('resources/currency/strings.import.english_name_required'));
        }

        if (Currency::where('english_name', $englishName)->exists()) {
            throw new RowImportFailedException(__('resources/currency/strings.import.duplicate_currency', ['name' => $englishName]));
        }

        if (filled($name) && Currency::where('name', $name)->exists()) {
            throw new RowImportFailedException(__('resources/currency/strings.import.duplicate_currency', ['name' => $name]));
        }

        $this->assertRequiredColumnsForNewRecordsPresent();

        return new Currency;
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
