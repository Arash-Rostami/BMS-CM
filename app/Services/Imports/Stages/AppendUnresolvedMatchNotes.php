<?php

namespace App\Services\Imports\Stages;

use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportRowContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppendUnresolvedMatchNotes
{
    private static array $hasNotesColumn = [];

    public function handle(ImportRowContext $context, Closure $next): ImportRowContext
    {
        $lines = [];

        foreach ($context->columns as $column) {
            if (! $column->hasMatchConfig() || $column->rejectOnMismatch()) {
                continue;
            }

            $raw = $context->rawData[$column->name] ?? null;

            if (blank($raw) || ImportColumnFactory::build($column)->castState($raw) !== null) {
                continue;
            }

            $lines[] = __('resources/general/strings.import.unresolved_match_left_blank', [
                'label' => __($column->labelKey ?? $column->name),
                'value' => $raw,
            ]);
        }

        if ($lines && $this->hasNotesColumn($context->record)) {
            $context->record->notes = trim(implode("\n", array_filter([$context->record->notes, ...$lines])));
        }

        return $next($context);
    }

    private function hasNotesColumn(Model $record): bool
    {
        $table = $record->getTable();

        return self::$hasNotesColumn[$table] ??= Schema::hasColumn($table, 'notes');
    }
}
