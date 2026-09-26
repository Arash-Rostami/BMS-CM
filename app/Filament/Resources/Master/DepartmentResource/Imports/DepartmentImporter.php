<?php

namespace App\Filament\Resources\Master\DepartmentResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Department;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use Filament\Actions\Imports\Importer;

class DepartmentImporter extends Importer implements Importable
{
    use ImportDefaults;

    public const COLUMN_LABEL_KEYS = [
        'code' => 'resources/department/strings.form.code',
        'name' => 'resources/department/strings.form.name',
        'english_name' => 'resources/department/strings.form.english_name',
        'description' => 'resources/department/strings.form.description',
    ];

    protected static ?string $model = Department::class;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::optional('code', $labels['code']),
            ImportColumnDefinition::manualSet('name', $labels['name']),
            ImportColumnDefinition::optional('english_name', $labels['english_name']),
            ImportColumnDefinition::optional('description', $labels['description']),
        ];
    }

    public static function getColumns(): array
    {
        return static::assertColumnNamesAllowed(array_map(
            fn (ImportColumnDefinition $definition) => ImportColumnFactory::build($definition),
            static::importColumns(),
        ));
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
        return 'import-examples/department-filled-example.csv';
    }

    public function beforeCreate(): void
    {
        $this->fillReservedNumberIfBlank('code');
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
