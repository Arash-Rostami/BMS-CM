<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Category;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Department;
use App\Models\NotificationSetting;
use App\Models\Product;
use App\Models\Status;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Contracts\TranslatableContentDriver;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

class PredefinedOptions
{
    public const LOOKUP_MODELS = [Bank::class, Category::class, Company::class, Currency::class, Department::class, Product::class, Status::class];

    private const DISTINCT_LIMIT = 40;

    private const MIN_ROWS = 10;

    private const REPEAT_RATIO = 3;

    private const CACHE_MINUTES = 10;

    private const FREE_ENTRY = '/(^|_)(no|number|num|name|title|subject|key|notes?|description|address|code|email|phone|url|link|path|slug|ref|reference)$/';

    /**
     * @var array<string, array<string, string>|null>
     */
    private static array $memo = [];

    /**
     * @var array<string, array<string, array<string, string>>>
     */
    private static array $formMemo = [];

    /**
     * @var array<string, array<string, array<string, mixed>>>
     */
    private static array $columnMemo = [];

    /**
     * @var array<int, string>
     */
    private static array $queries = [];

    private static bool $capturing = false;

    private static bool $listening = false;

    /**
     * @return array<string, string>|null
     */
    public function optionsFor(string $modelClass, string $column): ?array
    {
        $key = $modelClass.'|'.$column.'|'.app()->getLocale();

        if (! array_key_exists($key, self::$memo)) {
            self::$memo[$key] = $this->eligible($modelClass, $column) ? $this->resolve($modelClass, $column) : null;
        }

        return self::$memo[$key];
    }

    public static function flush(): void
    {
        self::$memo = [];
        self::$formMemo = [];
        self::$columnMemo = [];

        foreach (['en', 'fa', 'fr'] as $locale) {
            Cache::forget('predefined_options:sources:'.$locale);
        }
    }

    public static function flushScan(string $table): void
    {
        Cache::forget('predefined_options:scan:'.$table);
        self::$memo = [];
    }

    private function eligible(string $modelClass, string $column): bool
    {
        return is_subclass_of($modelClass, Model::class)
            && $column !== 'id'
            && ! str_ends_with($column, '_id')
            && ! NotificationSetting::isSensitiveColumn($column)
            && ! $this->isStructured($modelClass, $column);
    }

    private function isStructured(string $modelClass, string $column): bool
    {
        $cast = (new $modelClass)->getCasts()[$column] ?? null;

        return is_string($cast) && (in_array($cast, ['array', 'json', 'object', 'collection'], true) || (class_exists($cast) && ! enum_exists($cast)));
    }

    /**
     * @return array<string, string>|null
     */
    private function resolve(string $modelClass, string $column): ?array
    {
        $options = $this->enumOptions($modelClass, $column)
            ?? $this->sourceOptions($modelClass)[$column]
            ?? $this->booleanOptions($modelClass, $column)
            ?? $this->storedOptions($modelClass, $column);

        return $options === null || $options === [] ? null : $options;
    }

    /**
     * @return array<string, string>|null
     */
    private function enumOptions(string $modelClass, string $column): ?array
    {
        $enum = (new $modelClass)->getCasts()[$column] ?? null;

        if (! is_string($enum) || ! enum_exists($enum) || ! is_subclass_of($enum, BackedEnum::class)) {
            return null;
        }

        $options = [];

        foreach ($enum::cases() as $case) {
            $options[(string) $case->value] = $case instanceof HasLabel ? (string) ($case->getLabel() ?? $case->name) : Str::headline($case->name);
        }

        return $options;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function sourceOptions(string $modelClass): array
    {
        $locale = app()->getLocale();

        return (self::$formMemo[$locale] ??= Cache::remember(
            'predefined_options:sources:'.$locale,
            now()->addMinutes(self::CACHE_MINUTES),
            fn (): array => $this->collectSources()
        ))[$modelClass] ?? [];
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     */
    private function collectSources(): array
    {
        $found = [];
        $this->captureQueries();

        foreach (Filament::getResources() as $resource) {
            $model = $resource::getModel();

            try {
                $this->collectSchema($resource::form(Schema::make($this->livewireStub())->model($model)), $model, $found);
                $this->collectFilters($resource::table(Table::make($this->tableStub())), $model, $found);
            } catch (Throwable) {
                continue;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $found
     */
    private function collectSchema(Schema $schema, string $model, array &$found): void
    {
        foreach ($schema->getFlatComponents(withActions: false, withHidden: true) as $field) {
            if ($field instanceof Repeater && $field->getRelationshipName() !== null) {
                $child = $field->getChildSchema();

                if ($child !== null) {
                    $this->collectSchema($child, (new $model)->{$field->getRelationshipName()}()->getRelated()::class, $found);
                }
            } elseif ($field instanceof Select || $field instanceof Radio || $field instanceof ToggleButtons) {
                $this->collectField($field, $field->getName(), $model, $found);
            }
        }
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $found
     */
    private function collectFilters(Table $table, string $model, array &$found): void
    {
        foreach ($table->getFilters() as $filter) {
            if ($filter instanceof SelectFilter && ! preg_match(self::FREE_ENTRY, $filter->getAttribute())) {
                $this->collectField($filter, $filter->getAttribute(), $model, $found, (new $model)->getTable());
            }
        }
    }

    /**
     * @param  array<string, array<string, array<string, string>>>  $found
     */
    private function collectField(object $field, string $column, string $model, array &$found, ?string $ownTable = null): void
    {
        if (isset($found[$model][$column]) || $this->isRelationship($field)) {
            return;
        }

        $options = $this->staticOptions($field, $ownTable);

        if ($options !== null) {
            $found[$model][$column] = $options;
        }
    }

    /**
     * @return array<string, string>|null
     */
    private function staticOptions(object $field, ?string $ownTable): ?array
    {
        self::$queries = [];
        self::$capturing = true;

        try {
            $flat = $this->flatten($field->getOptions());
        } catch (Throwable) {
            return null;
        } finally {
            self::$capturing = false;
        }

        return $flat === [] || ! $this->onlyOwnTable($ownTable) || Str::contains(implode('', array_keys($flat)), '\\') ? null : $flat;
    }

    private function onlyOwnTable(?string $table): bool
    {
        return self::$queries === [] || ($table !== null && collect(self::$queries)->every(
            fn (string $query): bool => preg_match('/\bfrom\s+[`"]?'.preg_quote($table, '/').'[`"]?(\s|$)/i', $query) === 1 && ! str_contains(strtolower($query), ' join ')
        ));
    }

    private function captureQueries(): void
    {
        if (! self::$listening) {
            self::$listening = true;
            DB::listen(function (QueryExecuted $query): void {
                if (self::$capturing) {
                    self::$queries[] = $query->sql;
                }
            });
        }
    }

    private function tableStub(): Component&HasTable
    {
        return new class extends Component implements HasSchemas, HasTable
        {
            use InteractsWithSchemas;
            use InteractsWithTable;

            public function makeFilamentTranslatableContentDriver(): ?TranslatableContentDriver
            {
                return null;
            }

            public function render(): string
            {
                return '';
            }
        };
    }

    private function isRelationship(object $field): bool
    {
        return method_exists($field, 'getRelationshipName') && $field->getRelationshipName() !== null;
    }

    /**
     * @return array<string, string>
     */
    private function flatten(mixed $options): array
    {
        $options = $options instanceof Arrayable ? $options->toArray() : (array) $options;
        $flat = [];

        foreach ($options as $value => $label) {
            if (is_array($label)) {
                $flat += $this->flatten($label);
            } elseif (is_scalar($label) || $label instanceof \Stringable) {
                $flat[(string) $value] = (string) $label;
            }
        }

        return $flat;
    }

    private function livewireStub(): Component&HasSchemas
    {
        return new class extends Component implements HasSchemas
        {
            use InteractsWithSchemas;

            public function render(): string
            {
                return '';
            }
        };
    }

    /**
     * @return array<string, string>|null
     */
    private function booleanOptions(string $modelClass, string $column): ?array
    {
        $table = (new $modelClass)->getTable();
        $meta = (self::$columnMemo[$table] ??= collect(DbSchema::getColumns($table))->keyBy('name')->all())[$column] ?? null;

        return $meta !== null && ($meta['type_name'] === 'boolean' || $meta['type'] === 'tinyint(1)')
            ? ['1' => __('resources/general/strings.yes'), '0' => __('resources/general/strings.no')]
            : null;
    }

    /**
     * @return array<string, string>|null
     */
    private function storedOptions(string $modelClass, string $column): ?array
    {
        $values = $this->scan((new $modelClass)->getTable())[$column] ?? null;

        return $values === null ? null : collect($values)->mapWithKeys(fn (string $value): array => [$value => $this->storedLabel($modelClass, $column, $value)])->all();
    }

    private function storedLabel(string $modelClass, string $column, string $value): string
    {
        $resource = 'resources/'.Str::camel(class_basename($modelClass)).'/strings.general.';

        foreach ([Str::plural($column), $column] as $group) {
            if (Lang::has($key = $resource.$group.'.'.$value)) {
                return (string) __($key);
            }
        }

        foreach (['options.'.Str::snake(class_basename($modelClass)).'.'.$column.'.'.$value, 'columns.'.$value] as $shared) {
            if (Lang::has($key = 'resources/general/strings.'.$shared)) {
                return (string) __($key);
            }
        }

        return $value;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function scan(string $table): array
    {
        return Cache::remember('predefined_options:scan:'.$table, now()->addMinutes(self::CACHE_MINUTES), function () use ($table): array {
            $columns = collect(DbSchema::getColumns($table))
                ->filter(fn (array $meta): bool => in_array($meta['type_name'], ['varchar', 'char'], true)
                    && ! str_ends_with($meta['name'], '_id')
                    && ! NotificationSetting::isSensitiveColumn($meta['name'])
                    && ! preg_match(self::FREE_ENTRY, $meta['name']))
                ->pluck('name')
                ->values();

            return $columns->isEmpty() ? [] : $this->qualifying($table, $columns->all());
        });
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, array<int, string>>
     */
    private function qualifying(string $table, array $columns): array
    {
        $grammar = DB::getQueryGrammar();
        $selects = [];

        foreach ($columns as $index => $name) {
            $wrapped = $grammar->wrap($name);
            $selects[] = "COUNT(DISTINCT {$wrapped}) AS d{$index}";
            $selects[] = "COUNT({$wrapped}) AS n{$index}";
        }

        $counts = (array) DB::table($table)->selectRaw(implode(', ', $selects))->first();
        $found = [];

        foreach ($columns as $index => $name) {
            $distinct = (int) $counts["d{$index}"];
            $rows = (int) $counts["n{$index}"];

            if ($distinct > 0 && $distinct <= self::DISTINCT_LIMIT && $rows >= self::MIN_ROWS && $rows >= self::REPEAT_RATIO * $distinct) {
                $found[$name] = DB::table($table)->whereNotNull($name)->distinct()->orderBy($name)->pluck($name)
                    ->map(fn ($value): string => trim((string) $value))->filter()->unique()->values()->all();
            }
        }

        return array_filter($found, fn (array $values): bool => $values !== [] && ! Str::contains(implode('', $values), '\\'));
    }
}
