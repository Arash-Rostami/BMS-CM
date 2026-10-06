<?php

namespace App\Filament\Resources\Master\BankResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Bank;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Importer;
use Illuminate\Database\Eloquent\Model;

class BankImporter extends Importer implements Importable
{
    use ImportDefaults;

    public const COLUMN_LABEL_KEYS = [
        'name' => 'resources/bank/strings.form.name',
        'english_name' => 'resources/bank/strings.form.english_name',
        'description' => 'resources/bank/strings.form.description',
        'is_active' => 'resources/bank/strings.form.is_active',
    ];

    protected static ?string $model = Bank::class;

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
                $column->castStateUsing(fn ($state) => static::castIsActive($state));
            }
        }

        return static::assertColumnNamesAllowed($columns);
    }

    protected static function castIsActive(mixed $state): mixed
    {
        if (! is_string($state) || trim($state) === '') {
            return $state;
        }

        return match (mb_strtolower(trim($state))) {
            '1', 'true', 'yes', 'active' => true,
            '0', 'false', 'no', 'inactive' => false,
            default => null,
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
        return 'import-examples/bank-filled-example.csv';
    }

    public function resolveRecord(): ?Model
    {
        $englishName = $this->data['english_name'] ?? null;
        $name = $this->data['name'] ?? null;

        if (blank($englishName)) {
            throw new RowImportFailedException(__('resources/bank/strings.import.english_name_required'));
        }

        if (Bank::where('english_name', $englishName)->exists()) {
            throw new RowImportFailedException(__('resources/bank/strings.import.duplicate_bank', ['name' => $englishName]));
        }

        if (filled($name) && Bank::where('name', $name)->exists()) {
            throw new RowImportFailedException(__('resources/bank/strings.import.duplicate_bank', ['name' => $name]));
        }

        $this->assertRequiredColumnsForNewRecordsPresent();

        return new Bank;
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
