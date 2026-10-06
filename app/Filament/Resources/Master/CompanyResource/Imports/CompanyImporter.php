<?php

namespace App\Filament\Resources\Master\CompanyResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Company;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use App\Services\Imports\LocalizedMatcher;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class CompanyImporter extends Importer implements Importable
{
    use ImportDefaults;

    public const COLUMN_LABEL_KEYS = [
        'english_name' => 'resources/company/strings.form.english_name',
        'name' => 'resources/company/strings.form.name',
        'description' => 'resources/company/strings.form.description',
        'is_active' => 'resources/company/strings.form.is_active',
        'types' => 'resources/company/strings.form.company_types',
    ];

    protected static ?string $model = Company::class;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::manualSet('english_name', $labels['english_name']),
            ImportColumnDefinition::manualSet('name', $labels['name']),
            ImportColumnDefinition::optional('description', $labels['description']),
        ];
    }

    public static function getColumns(): array
    {
        return static::assertColumnNamesAllowed([
            ...array_map(fn (ImportColumnDefinition $definition) => ImportColumnFactory::build($definition), static::importColumns()),
            static::isActiveColumn(),
            static::typesColumn(),
        ]);
    }

    protected static function isActiveColumn(): ImportColumn
    {
        $labelKey = self::COLUMN_LABEL_KEYS['is_active'];
        $label = LocalizedMatcher::columnLabel('is_active', $labelKey);

        return ImportColumn::make('is_active')
            ->label($label)
            ->exampleHeader($label)
            ->guess(LocalizedMatcher::localizedGuesses($labelKey))
            ->ignoreBlankState()
            ->castStateUsing(fn ($state) => static::parseBoolean($state));
    }

    protected static function parseBoolean(mixed $state): ?bool
    {
        if (! is_string($state) || trim($state) === '') {
            return null;
        }

        $value = mb_strtolower(trim($state));

        return match (true) {
            in_array($value, ['1', 'true', 'yes', 'active'], true) => true,
            in_array($value, ['0', 'false', 'no', 'inactive'], true) => false,
            default => null,
        };
    }

    protected static function typesColumn(): ImportColumn
    {
        $labelKey = self::COLUMN_LABEL_KEYS['types'];
        $label = LocalizedMatcher::columnLabel('types', $labelKey);

        return ImportColumn::make('types')
            ->label($label)
            ->exampleHeader($label)
            ->guess(LocalizedMatcher::localizedGuesses($labelKey))
            ->ignoreBlankState()
            ->castStateUsing(fn ($state) => is_string($state) ? trim($state) : $state);
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
        return 'import-examples/company-filled-example.csv';
    }

    public function resolveRecord(): ?Model
    {
        $englishName = $this->data['english_name'] ?? null;

        if (blank($englishName)) {
            throw new RowImportFailedException(__('resources/company/strings.import.english_name_required'));
        }

        if (Company::where('english_name', $englishName)->exists()) {
            throw new RowImportFailedException(__('resources/company/strings.import.duplicate_company', ['name' => $englishName]));
        }

        $this->assertRequiredColumnsForNewRecordsPresent();

        return new Company;
    }

    public function beforeFill(): void
    {
        $raw = $this->data['types'] ?? null;
        unset($this->data['types']);

        $this->record->types = $this->parseTypes($raw);
    }

    protected function parseTypes(mixed $raw): ?array
    {
        if (blank($raw) || ! is_string($raw)) {
            return null;
        }

        $available = array_keys(Company::getAvailableTypes());
        $valid = [];

        foreach (explode(',', $raw) as $value) {
            $value = trim($value);

            if ($value === '') {
                continue;
            }

            $key = str_replace(' ', '_', mb_strtolower($value));

            if (in_array($key, $available, true)) {
                $valid[] = $key;

                continue;
            }

            Log::warning(__('resources/company/strings.import.invalid_type_dropped', ['value' => $value]));
        }

        return $valid ?: null;
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
