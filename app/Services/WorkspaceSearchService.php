<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class WorkspaceSearchService
{
    protected const BLOCKED_COLUMNS = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    public function search(string $resource, string $term): array
    {
        $config = config("workspace.resources.{$resource}");

        abort_unless(is_array($config) && isset($config['model'], $config['route']), 404);

        $permissionPrefix = Str::snake(class_basename($config['model']));
        abort_unless(auth()->user()?->can($permissionPrefix.'.view') ?? false, 403);

        /** @var Model $model */
        $model = new $config['model'];

        $columns = $this->columns($model);
        $whitelist = $config['search'] ?? null;

        $searchable = $whitelist
            ? array_values(array_intersect($whitelist, $columns))
            : array_values(array_diff($columns, self::BLOCKED_COLUMNS));

        $escaped = addcslashes($term, '%_\\');

        $records = $model->newQuery()
            ->when($term !== '' && ! empty($searchable), function ($query) use ($searchable, $escaped) {
                $query->where(function ($q) use ($searchable, $escaped) {
                    foreach ($searchable as $column) {
                        $q->orWhereRaw("CAST(`{$column}` AS CHAR) LIKE ?", ['%'.$escaped.'%']);
                    }
                });
            })
            ->orderByDesc($model->getKeyName())
            ->limit(25)
            ->get();

        return $records->map(fn (Model $record) => [
            'key' => $resource.':'.$record->getKey(),
            'resourceId' => $resource,
            'recordId' => $record->getKey(),
            'label' => $this->compose($record, $config['title'] ?? []) ?: ('#'.$record->getKey()),
            'subtitle' => $this->compose($record, $config['subtitle'] ?? [], ' · '),
            'url' => route($config['route'], ['record' => $record->getKey()]),
        ])->all();
    }

    protected function columns(Model $model): array
    {
        $connection = $model->getConnectionName() ?? 'default';
        $table = $model->getTable();

        return Cache::remember(
            "workspace_columns:{$connection}:{$table}",
            now()->addDay(),
            fn () => Schema::connection($model->getConnectionName())->getColumnListing($table),
        );
    }

    protected function compose(Model $record, array $columns, string $glue = ' '): string
    {
        $parts = [];

        foreach ($columns as $column) {
            $value = $record->{$column} ?? null;

            if ($value instanceof \DateTimeInterface) {
                $parts[] = $value->format('Y-m-d');
            } elseif ($value instanceof \BackedEnum) {
                $parts[] = (string) $value->value;
            } elseif (is_scalar($value)) {
                $string = trim((string) $value);
                if ($string !== '') {
                    $parts[] = $string;
                }
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $parts[] = (string) $value;
            }
        }

        return trim(implode($glue, $parts));
    }
}
