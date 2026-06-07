<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class WorkspaceController extends Controller
{
    /**
     * Search the records of a whitelisted resource across all of its columns.
     * Powers the landing-page "Your Workspace" record-pinning UI.
     */
    public function records(Request $request, string $resource): JsonResponse
    {
        $config = config("workspace.resources.{$resource}");

        abort_unless(is_array($config) && isset($config['model'], $config['route']), 404);

        /** @var Model $model */
        $model = new $config['model'];
        $table = $model->getTable();

        $columns = Schema::connection($model->getConnectionName())->getColumnListing($table);
        $blocked = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
        $whitelist = $config['search'] ?? null;

        $searchable = $whitelist
            ? array_values(array_intersect($whitelist, $columns))
            : array_values(array_diff($columns, $blocked));

        $term = trim((string)$request->query('q', ''));
        $escaped = addcslashes($term, '%_\\');

        $records = $model->newQuery()
            ->when($term !== '' && !empty($searchable), function ($query) use ($searchable, $escaped) {
                $query->where(function ($q) use ($searchable, $escaped) {
                    foreach ($searchable as $column) {
                        $q->orWhereRaw("CAST(`{$column}` AS CHAR) LIKE ?", ['%' . $escaped . '%']);
                    }
                });
            })
            ->orderByDesc($model->getKeyName())
            ->limit(25)
            ->get();

        $data = $records->map(fn(Model $record) => [
            'key' => $resource . ':' . $record->getKey(),
            'resourceId' => $resource,
            'recordId' => $record->getKey(),
            'label' => $this->compose($record, $config['title'] ?? []) ?: ('#' . $record->getKey()),
            'subtitle' => $this->compose($record, $config['subtitle'] ?? [], ' · '),
            'url' => route($config['route'], ['record' => $record->getKey()]),
        ]);

        return response()->json(['data' => $data]);
    }

    protected function compose(Model $record, array $columns, string $glue = ' '): string
    {
        $parts = [];

        foreach ($columns as $column) {
            $value = $record->{$column} ?? null;

            if ($value instanceof \DateTimeInterface) {
                $parts[] = $value->format('Y-m-d');
            } elseif ($value instanceof \BackedEnum) {
                $parts[] = (string)$value->value;
            } elseif (is_scalar($value)) {
                $string = trim((string)$value);
                if ($string !== '') {
                    $parts[] = $string;
                }
            } elseif (is_object($value) && method_exists($value, '__toString')) {
                $parts[] = (string)$value;
            }
        }

        return trim(implode($glue, $parts));
    }
}
