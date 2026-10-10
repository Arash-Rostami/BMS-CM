<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class NameSearch
{
    public const LIMIT = 50;

    private const NAMES = ['name', 'english_name'];

    private const JOINER = ' — ';

    /**
     * @var array<string, array<int, string>>
     */
    private static array $listings = [];

    /**
     * @return array<int, string>
     */
    public static function columns(Model $model): array
    {
        $table = $model->getTable();
        $names = app()->getLocale() === 'fa' ? ['name', 'english_name'] : ['english_name', 'name'];
        $identifier = defined($model::class.'::SCANNABLE_IDENTIFIER') ? [$model::SCANNABLE_IDENTIFIER] : [];

        return array_values(array_unique(array_intersect(
            [...$names, ...$identifier, 'title', 'code'],
            self::$listings[$table] ??= Schema::getColumnListing($table)
        )));
    }

    public static function display(Model $model): ?string
    {
        return self::columns($model)[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function nameColumns(Model $model): array
    {
        return array_values(array_intersect(self::columns($model), self::NAMES));
    }

    public static function label(Model $model): string
    {
        $parts = collect(self::nameColumns($model))
            ->map(fn (string $column): string => trim((string) $model->getAttribute($column)))
            ->filter()
            ->unique(fn (string $part): string => mb_strtolower($part));

        return $parts->isNotEmpty() ? $parts->implode(self::JOINER) : (string) $model->getAttribute(self::display($model) ?? $model->getKeyName());
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int|string, string>
     */
    public static function labels(Model $model, array $ids): array
    {
        return $ids === [] ? [] : $model->newQuery()
            ->whereIn($model->getKeyName(), $ids)
            ->get([$model->getKeyName(), ...self::columns($model)])
            ->mapWithKeys(fn (Model $record): array => [$record->getKey() => self::label($record)])
            ->all();
    }

    public static function apply(Builder $query, Model $model, string $term, bool $orKey = false): Builder
    {
        $term = trim($term);
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(function (Builder $group) use ($model, $term, $like, $orKey): void {
            foreach (self::columns($model) as $column) {
                $group->orWhere($column, 'like', $like);
            }

            if ($orKey && ctype_digit($term)) {
                $group->orWhere($model->getKeyName(), (int) $term);
            }
        });
    }

    /**
     * @return array<int|string, string>
     */
    public static function options(Model $model, string $term, bool $orKey = false): array
    {
        $display = self::display($model);

        if ($display === null) {
            return [];
        }

        return self::apply($model->newQuery(), $model, $term, $orKey)
            ->orderBy($display)
            ->limit(self::LIMIT)
            ->get([$model->getKeyName(), ...self::columns($model)])
            ->mapWithKeys(fn (Model $record): array => [$record->getKey() => self::label($record)])
            ->all();
    }
}
