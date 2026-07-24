<?php

namespace App\Models\Traits\General;

use DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use Schema;
use Throwable;

trait ModelInspector
{
    public static function getAvailableColumns(string $modelClass): array
    {
        if (! class_exists($modelClass)) {
            return [];
        }

        return array_map(
            fn ($column) => ucfirst(str_replace('_', ' ', $column)),
            (new $modelClass)->getFillable()
        );
    }

    public static function getAvailableModels(): array
    {
        $modelsPath = app_path('Models');
        $models = File::allFiles($modelsPath);
        $tables = [];

        foreach ($models as $modelFile) {
            $modelClass = 'App\\Models\\'.Str::studly(pathinfo($modelFile, PATHINFO_FILENAME));

            if (! class_exists($modelClass)) {
                continue;
            }

            if (defined("$modelClass::SCANNABLE_TABLE")) {
                $tables[$modelClass::SCANNABLE_TABLE] = ucfirst(Str::snake(class_basename($modelClass), ' '));
            }
        }

        return $tables;
    }

    public static function getColumnValuesForSelectedColumns(array $columns, array $selectedTables = []): array
    {
        $values = [];
        $availableTables = self::getAvailableModels();

        if (! empty($selectedTables)) {
            $availableTables = array_filter(
                $availableTables,
                fn ($label, $table) => in_array($table, $selectedTables),
                ARRAY_FILTER_USE_BOTH
            );
        }

        foreach ($availableTables as $table => $label) {
            $modelClass = self::resolveModelClass($table);
            if (! class_exists($modelClass)) {
                continue;
            }

            $model = new $modelClass;
            $relations = self::guessRelationships($model);

            $tableColumns = Schema::getColumnListing($table);
            $selectedColumns = array_intersect($columns, $tableColumns);

            if (empty($selectedColumns)) {
                continue;
            }

            $rows = DB::table($table)
                ->select($selectedColumns)
                ->where(function ($query) use ($selectedColumns) {
                    foreach ($selectedColumns as $column) {
                        $query->orWhereNotNull($column);
                    }
                })
                ->distinct()
                ->get();

            foreach ($selectedColumns as $column) {
                $bestRelation = self::findBestRelationForColumn($column, $relations);
                $groupLabel = Str::headline($table).' → '.Str::headline($bestRelation ?? $column);

                $hasRelation = $bestRelation && method_exists($model, $bestRelation);
                $relatedMap = [];

                if ($hasRelation) {
                    $relatedModel = $model->{$bestRelation}()->getRelated();
                    $displayColumn = $relatedModel->english_name ?? $relatedModel->name ?? 'name';
                    $keyName = $relatedModel->getKeyName();

                    $rawValues = $rows->pluck($column)->filter()->unique()->values()->toArray();

                    if (! empty($rawValues)) {
                        $relatedMap = $relatedModel->whereIn($keyName, $rawValues)
                            ->pluck($displayColumn, $keyName)
                            ->toArray();
                    }
                }

                foreach ($rows as $row) {
                    $rawValue = $row->{$column} ?? null;
                    if ($rawValue === null) {
                        continue;
                    }

                    $value = $hasRelation ? ($relatedMap[$rawValue] ?? $rawValue) : $rawValue;

                    if ($value !== null) {
                        $values[$groupLabel][$rawValue] = (string) $value;
                    }
                }
            }
        }

        return $values;
    }

    public static function getColumnsForSelectedTables(array $selectedTables): array
    {
        $columns = [];

        foreach ($selectedTables as $table) {
            $tableColumns = Schema::getColumnListing($table);
            if (empty($tableColumns)) {
                continue;
            }

            $groupLabel = Str::headline($table);
            foreach ($tableColumns as $column) {
                $columns[$groupLabel][$column] = Str::headline($column);
            }
        }

        return $columns;
    }

    protected static function resolveModelClass(string $table): string
    {
        return 'App\\Models\\'.Str::studly(Str::singular($table));
    }

    private static function findBestRelationForColumn(string $column, array $relations): ?string
    {
        if (! str_ends_with($column, '_id')) {
            return null;
        }

        $best = null;
        $score = 0;
        foreach ($relations as $relation) {
            similar_text(Str::snake($column), Str::snake($relation), $percent);
            if ($percent > $score) {
                $score = $percent;
                $best = $relation;
            }
        }

        return $best;
    }

    private static function guessRelationships(Model $model): array
    {
        $relationships = [];
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->class !== get_class($model) ||
                $method->getNumberOfParameters() !== 0 ||
                in_array($method->name, ['boot', 'booted', 'initializeTraits'])
            ) {
                continue;
            }

            try {
                $result = $method->invoke($model);
                if ($result instanceof Relation) {
                    $relationships[] = $method->name;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        return $relationships;
    }
}
