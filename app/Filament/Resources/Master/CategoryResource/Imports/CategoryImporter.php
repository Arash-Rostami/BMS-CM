<?php

namespace App\Filament\Resources\Master\CategoryResource\Imports;

use App\Contracts\Imports\Importable;
use App\Filament\Traits\ImportDefaults;
use App\Models\Category;
use App\Services\Imports\ImportColumnDefinition;
use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportPipeline;
use App\Services\Imports\ImportRowContext;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Importer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CategoryImporter extends Importer implements Importable
{
    use ImportDefaults;

    public const COLUMN_LABEL_KEYS = [
        'english_name' => 'resources/category/strings.form.english_name',
        'name' => 'resources/category/strings.form.name',
        'parent_id' => 'resources/category/strings.form.parent',
        'description' => 'resources/category/strings.form.description',
        'active' => 'resources/category/strings.form.active',
    ];

    protected static ?string $model = Category::class;

    /**
     * @return ImportColumnDefinition[]
     */
    public static function importColumns(): array
    {
        $labels = self::COLUMN_LABEL_KEYS;

        return [
            ImportColumnDefinition::manualSet('english_name', $labels['english_name']),
            ImportColumnDefinition::optional('name', $labels['name']),
            ImportColumnDefinition::match('parent_id', $labels['parent_id'], 'parent', Category::class, ['name', 'english_name'])
                ->allowNullOnMismatch(),
            ImportColumnDefinition::optional('description', $labels['description']),
            ImportColumnDefinition::optional('active', $labels['active'])
                ->withFallback(fn () => true, rejectIfStillBlank: false),
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
        return 'import-examples/category-filled-example.csv';
    }

    public function resolveRecord(): ?Model
    {
        $englishName = $this->data['english_name'] ?? null;

        if (blank($englishName)) {
            throw new RowImportFailedException(__('resources/category/strings.import.english_name_required'));
        }

        $slug = Str::slug($englishName);

        if (Category::where('slug', $slug)->exists()) {
            throw new RowImportFailedException(__('resources/category/strings.import.duplicate_category', ['name' => $englishName]));
        }

        $this->assertRequiredColumnsForNewRecordsPresent();

        return new Category;
    }

    public function afterFill(): void
    {
        $context = new ImportRowContext($this->record, [...$this->options, '__importer' => $this]);
        $context->columns = static::importColumns();
        $context->rawData = $this->buildRawDataMap();

        ImportPipeline::runBeforeSave($context);

        if (is_string($this->record->active)) {
            $this->record->active = filter_var($this->record->active, FILTER_VALIDATE_BOOLEAN);
        }

        if (Category::wouldCreateCycle($this->record->id, $this->record->parent_id)) {
            $this->record->parent_id = null;
        }

        $this->record->level = $this->record->parent_id
            ? Category::find($this->record->parent_id)->level + 1
            : 0;
    }

    public function afterSave(): void
    {
        $context = new ImportRowContext($this->record, [...$this->options, '__importer' => $this]);
        $context->columns = static::importColumns();

        ImportPipeline::runAfterSave($context);
    }

    public function recalculateAfterPersist(): void {}
}
