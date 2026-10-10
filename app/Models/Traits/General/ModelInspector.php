<?php

namespace App\Models\Traits\General;

use App\Services\Calendar\CalendarPathResolver;
use App\Services\NameSearch;
use App\Services\PermissionLabeler;
use App\Services\PredefinedOptions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

trait ModelInspector
{
    public const SENSITIVE_COLUMN_FRAGMENTS = ['password', 'token', 'secret', 'iban', 'account', 'swift', 'card', 'body'];

    public const VALUE_OPTIONS_LIMIT = 200;

    public const FOREIGN_SEARCH_LIMIT = NameSearch::LIMIT;

    public const VALUES_PER_COLUMN_LIMIT = 50;

    public const VALUE_LENGTH_LIMIT = 255;

    private static ?array $scannableModels = null;

    private static array $morphIdColumns = [];

    private static array $listings = [];

    private static array $columnMeta = [];

    public static function getAvailableColumns(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        $resolver = app(CalendarPathResolver::class);

        return array_map(fn ($column) => $resolver->columnLabel($modelClass, $column), (new $modelClass)->getFillable());
    }

    public static function columnLabel(mixed $tables, string $column): string
    {
        $models = self::scannableModels();

        foreach (is_array($tables) ? array_filter($tables, 'is_string') : [] as $table) {
            if (isset($models[$table]) && in_array($column, self::listing($table), true)) {
                return app(CalendarPathResolver::class)->columnLabel($models[$table], $column);
            }
        }

        return Str::headline($column);
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<int, string>
     */
    public static function columnLabels(mixed $tables, array $columns): array
    {
        return array_map(fn (string $column): string => self::columnLabel($tables, $column), $columns);
    }

    /**
     * @return array<string, class-string<Model>>
     */
    public static function scannableModels(): array
    {
        return self::$scannableModels ??= self::loadScannableModels();
    }

    public static function flushScannableModels(): void
    {
        self::$scannableModels = null;
        self::$morphIdColumns = [];
        self::$listings = [];
        self::$columnMeta = [];
        Cache::forget('notification_scannable_models');
    }

    public static function getAvailableModels(): array
    {
        return array_map(
            fn (string $class) => PermissionLabeler::getEntityLabel($class),
            self::scannableModels()
        );
    }

    public static function getViewableModels(): array
    {
        return array_intersect_key(self::getAvailableModels(), array_flip(self::viewableTables()));
    }

    /**
     * @return array<int, string>
     */
    public static function viewableTables(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        return array_keys(array_filter(
            self::scannableModels(),
            fn (string $class): bool => $user->can(Str::snake(class_basename($class)).'.view')
        ));
    }

    public static function getLocalizedTableLabel(string $table): string
    {
        return self::getAvailableModels()[$table] ?? Str::headline($table);
    }

    public static function isSensitiveColumn(string $column): bool
    {
        return Str::contains(strtolower($column), self::SENSITIVE_COLUMN_FRAGMENTS);
    }

    public static function isMorphIdColumn(string $table, string $column): bool
    {
        return in_array($column, self::$morphIdColumns[$table] ??= self::loadMorphIdColumns($table), true);
    }

    public static function selectableColumns(string $table): array
    {
        return array_values(array_filter(
            self::listing($table),
            fn (string $column): bool => ! self::isMorphIdColumn($table, $column)
        ));
    }

    private static function listing(string $table): array
    {
        return self::$listings[$table] ??= Schema::getColumnListing($table);
    }

    private static function columnMeta(string $table): array
    {
        return self::$columnMeta[$table] ??= collect(Schema::getColumns($table))->keyBy('name')->all();
    }

    public static function getColumnsForSelectedTables(mixed $selectedTables): array
    {
        $columns = [];

        foreach (self::selectedViewableTables($selectedTables) as $table) {
            $groupLabel = self::getLocalizedTableLabel($table);

            foreach (self::selectableColumns($table) as $column) {
                $columns[$groupLabel][$column] = self::columnLabel([$table], $column);
            }
        }

        return $columns;
    }

    /**
     * @return array<int|string, string>
     */
    public static function getColumnValueOptions(mixed $selectedTables, string $column): array
    {
        if (self::isSensitiveColumn($column)) {
            return [];
        }

        $options = [];

        foreach (self::selectedViewableTables($selectedTables) as $table) {
            if (in_array($column, self::selectableColumns($table), true)) {
                $options += self::valueOptionsFor($table, $column);
            }
        }

        return $options;
    }

    public static function columnKind(mixed $selectedTables, string $column): string
    {
        $tables = is_array($selectedTables) ? array_intersect(array_filter($selectedTables, 'is_string'), array_keys(self::scannableModels())) : [];

        foreach ($tables as $table) {
            $meta = self::columnMeta($table)[$column] ?? null;

            if ($meta !== null) {
                return self::kindOf(strtolower($meta['type_name']), strtolower($meta['type']));
            }
        }

        return 'text';
    }

    /**
     * @return array<string, string>|null
     */
    public static function predefinedOptions(mixed $selectedTables, string $column): ?array
    {
        $models = self::scannableModels();
        $options = [];

        foreach (is_array($selectedTables) ? array_filter($selectedTables, 'is_string') : [] as $table) {
            if (isset($models[$table])) {
                $options += app(PredefinedOptions::class)->optionsFor($models[$table], $column) ?? [];
            }
        }

        return $options === [] ? null : $options;
    }

    public static function isValidTypedValue(mixed $selectedTables, string $column, mixed $value): bool
    {
        if (! is_scalar($value) || self::isSensitiveColumn($column)) {
            return false;
        }

        $value = trim((string) $value);

        if ($value === '' || mb_strlen($value) > self::VALUE_LENGTH_LIMIT) {
            return false;
        }

        return match (self::columnKind($selectedTables, $column)) {
            'number' => str_ends_with($column, '_id') ? ctype_digit($value) : preg_match('/^-?\d+(\.\d+)?$/', $value) === 1,
            'boolean' => in_array($value, ['0', '1'], true),
            'date', 'datetime' => self::isIsoDate($value),
            default => true,
        };
    }

    public static function canonicalTypedValue(mixed $selectedTables, string $column, string $value): ?string
    {
        $value = trim($value);

        if (! self::isValidTypedValue($selectedTables, $column, $value)) {
            return null;
        }

        return in_array(self::columnKind($selectedTables, $column), ['date', 'datetime'], true)
            ? Carbon::parse($value)->format(str_contains($value, ':') ? 'Y-m-d H:i:s' : 'Y-m-d')
            : $value;
    }

    /**
     * @return array<int|string, string>
     */
    public static function valueChoices(mixed $selectedTables, string $column, string $term = ''): array
    {
        if (self::columnKind($selectedTables, $column) === 'boolean') {
            return ['1' => __('resources/notificationSetting/strings.form.yes'), '0' => __('resources/notificationSetting/strings.form.no')];
        }

        $term = trim($term);

        if ($term === '' || self::isSensitiveColumn($column)) {
            return $term === '' ? self::getColumnValueOptions($selectedTables, $column) : [];
        }

        $options = [];

        foreach (self::selectedViewableTables($selectedTables) as $table) {
            if (in_array($column, self::selectableColumns($table), true)) {
                $options += self::searchOptionsFor($table, $column, $term);
            }
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function typedValueLabels(mixed $selectedTables, string $column, array $values): array
    {
        $boolean = self::columnKind($selectedTables, $column) === 'boolean' ? self::valueChoices($selectedTables, $column) : [];
        $predefined = self::predefinedOptions($selectedTables, $column) ?? [];
        $labels = [];

        foreach ($values as $value) {
            if (self::isValidTypedValue($selectedTables, $column, $value)) {
                $labels[(string) $value] = $predefined[trim((string) $value)] ?? $boolean[trim((string) $value)] ?? self::displayValue($selectedTables, $column, $value);
            }
        }

        return $labels;
    }

    public static function displayValue(mixed $selectedTables, string $column, mixed $value): string
    {
        $value = trim((string) $value);

        return in_array(self::columnKind($selectedTables, $column), ['date', 'datetime'], true) && self::isIsoDate($value)
            ? adaptiveDate($value, str_contains($value, ':'))
            : $value;
    }

    private static function kindOf(string $name, string $type): string
    {
        return match (true) {
            in_array($name, ['bool', 'boolean'], true), $type === 'tinyint(1)' => 'boolean',
            in_array($name, ['decimal', 'numeric', 'float', 'double', 'real', 'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'year'], true) => 'number',
            $name === 'date' => 'date',
            in_array($name, ['datetime', 'timestamp'], true) => 'datetime',
            default => 'text',
        };
    }

    private static function isIsoDate(string $value): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T]([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?)?$/', $value, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    public static function isForeignKeyColumn(mixed $selectedTables, string $column): bool
    {
        return self::relatedModels($selectedTables, $column) !== [];
    }

    /**
     * @return array<int|string, string>
     */
    public static function searchForeignKeyOptions(mixed $selectedTables, string $column, string $term): array
    {
        $options = [];

        foreach (self::relatedModels($selectedTables, $column) as [$related]) {
            $options += NameSearch::options($related, $term, true);
        }

        return $options;
    }

    /**
     * @return array<int|string, string>
     */
    public static function foreignKeyLabels(mixed $selectedTables, string $column, array $ids): array
    {
        $ids = array_values(array_filter($ids, 'is_scalar'));
        $labels = [];

        foreach (self::selectedViewableTables($selectedTables) as $table) {
            $labels += array_map('strval', self::relatedLabels(self::scannableModels()[$table], $column, $ids));
        }

        return $labels;
    }

    /**
     * @return array<int, array{0: Model, 1: string}>
     */
    private static function relatedModels(mixed $selectedTables, string $column): array
    {
        $models = [];

        foreach (self::foreignModels(self::selectedViewableTables($selectedTables), $column) as $related) {
            $display = self::displayColumn($related);

            if ($display !== null) {
                $models[] = [$related, $display];
            }
        }

        return $models;
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, Model>
     */
    private static function foreignModels(array $tables, string $column): array
    {
        if (self::isSensitiveColumn($column) || ! str_ends_with($column, '_id')) {
            return [];
        }

        $models = [];

        foreach ($tables as $table) {
            if (! isset(self::scannableModels()[$table]) || ! in_array($column, self::listing($table), true)) {
                continue;
            }

            try {
                $related = self::belongsToFor(self::scannableModels()[$table], $column);
            } catch (Throwable) {
                continue;
            }

            if ($related !== null) {
                $models[$related::class] = $related;
            }
        }

        return array_values($models);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, int|string>
     */
    public static function existingForeignIds(mixed $tables, string $column, array $ids): array
    {
        $models = self::foreignModels(array_values(array_filter((array) $tables, 'is_string')), $column);

        if ($models === [] || $ids === []) {
            return $ids;
        }

        $existing = collect($models)
            ->flatMap(fn (Model $model) => $model->newQuery()->withoutGlobalScopes()->whereIn($model->getKeyName(), $ids)->pluck($model->getKeyName()))
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_values(array_filter($ids, fn ($id): bool => in_array((string) $id, $existing, true)));
    }

    public static function foreignKeyId(mixed $selectedTables, string $column, mixed $value): ?int
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return ctype_digit($value) && self::existingForeignIds(self::selectedViewableTables($selectedTables), $column, [$value]) !== [] ? (int) $value : null;
    }

    /**
     * @return array<int, string>
     */
    public static function selectedViewableTables(mixed $selectedTables): array
    {
        if (! is_array($selectedTables)) {
            return [];
        }

        return array_values(array_intersect(array_filter($selectedTables, 'is_string'), self::viewableTables()));
    }

    private static function loadScannableModels(): array
    {
        try {
            return Cache::remember('notification_scannable_models', 3600, fn () => self::scanModels());
        } catch (Throwable) {
            return self::scanModels();
        }
    }

    private static function scanModels(): array
    {
        $tables = [];

        foreach (File::allFiles(app_path('Models')) as $modelFile) {
            $modelClass = 'App\\Models\\'.Str::studly(pathinfo($modelFile, PATHINFO_FILENAME));

            if (class_exists($modelClass) && defined("$modelClass::SCANNABLE_TABLE")) {
                $tables[$modelClass::SCANNABLE_TABLE] = $modelClass;
            }
        }

        return $tables;
    }

    private static function dayOptionsFor(string $table, string $column): array
    {
        return DB::table($table)
            ->whereNotNull($column)
            ->selectRaw('DATE('.DB::getQueryGrammar()->wrap($column).') as day')
            ->distinct()
            ->orderBy('day')
            ->limit(self::VALUE_OPTIONS_LIMIT)
            ->pluck('day')
            ->filter()
            ->mapWithKeys(fn ($day) => [(string) $day => adaptiveDate((string) $day)])
            ->all();
    }

    private static function valueOptionsFor(string $table, string $column): array
    {
        if (in_array(self::columnKind([$table], $column), ['date', 'datetime'], true)) {
            return self::dayOptionsFor($table, $column);
        }

        $raw = DB::table($table)
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->limit(self::VALUE_OPTIONS_LIMIT)
            ->pluck($column)
            ->filter(fn ($value) => is_scalar($value));

        $labels = self::relatedLabels(self::scannableModels()[$table], $column, $raw->all());

        return $raw->mapWithKeys(fn ($value) => [(string) $value => (string) ($labels[$value] ?? $value)])->all();
    }

    private static function searchOptionsFor(string $table, string $column, string $term): array
    {
        $like = '%'.addcslashes($term, '\%_').'%';
        $wrapped = DB::getQueryGrammar()->wrap($column);
        $query = DB::table($table)->whereNotNull($column)->whereRaw("CAST($wrapped AS CHAR) LIKE ?", [$like]);

        if (in_array(self::columnKind([$table], $column), ['date', 'datetime'], true)) {
            return $query->selectRaw("DATE($wrapped) as day")->distinct()->orderBy('day')->limit(self::VALUES_PER_COLUMN_LIMIT)
                ->pluck('day')->filter()->mapWithKeys(fn ($day) => [(string) $day => adaptiveDate((string) $day)])->all();
        }

        $raw = $query->distinct()->orderBy($column)->limit(self::VALUES_PER_COLUMN_LIMIT)->pluck($column)->filter(fn ($value) => is_scalar($value));
        $labels = self::relatedLabels(self::scannableModels()[$table], $column, $raw->all());

        return $raw->mapWithKeys(fn ($value) => [(string) $value => (string) ($labels[$value] ?? $value)])->all();
    }

    private static function loadMorphIdColumns(string $table): array
    {
        $class = self::scannableModels()[$table] ?? null;
        $listing = self::listing($table);

        return array_values(array_filter($listing, function (string $column) use ($class, $listing): bool {
            $prefix = Str::beforeLast($column, '_id');

            if (! str_ends_with($column, '_id') || ! in_array($prefix.'_type', $listing, true)) {
                return false;
            }

            try {
                return $class !== null && method_exists($class, $relation = Str::camel($prefix)) && (new $class)->{$relation}() instanceof MorphTo;
            } catch (Throwable) {
                return false;
            }
        }));
    }

    private static function relatedLabels(string $modelClass, string $column, array $ids): array
    {
        if (! str_ends_with($column, '_id') || $ids === []) {
            return [];
        }

        try {
            $related = self::belongsToFor($modelClass, $column);

            return $related === null || self::displayColumn($related) === null ? [] : NameSearch::labels($related, $ids);
        } catch (Throwable) {
            return [];
        }
    }

    private static function belongsToFor(string $modelClass, string $column): ?Model
    {
        $relation = Str::camel(Str::beforeLast($column, '_id'));

        if (method_exists($modelClass, $relation) && ($instance = (new $modelClass)->{$relation}()) instanceof BelongsTo && ! $instance instanceof MorphTo) {
            return $instance->getRelated();
        }

        foreach (app(CalendarPathResolver::class)->relations($modelClass) as $meta) {
            if ($meta['foreign_key'] === $column) {
                return new $meta['related'];
            }
        }

        return null;
    }

    private static function displayColumn(Model $related): ?string
    {
        return NameSearch::display($related);
    }
}
