<?php

namespace App\Services\Imports\Stages;

use App\Services\Imports\ImportColumnFactory;
use App\Services\Imports\ImportRowContext;
use Closure;
use Illuminate\Support\Facades\Schema;

class AppendUnresolvedMatchNotes
{
    protected static array $hasNotesColumn = [];

    public function handle(ImportRowContext $context, Closure $next): ImportRowContext
    {
        if (! static::recordHasNotesColumn($context)) {
            return $next($context);
        }

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

        if ($lines) {
            $context->record->notes = trim(implode("\n", array_filter([$context->record->notes, ...$lines])));
        }

        return $next($context);
    }

    protected static function recordHasNotesColumn(ImportRowContext $context): bool
    {
        $table = $context->record->getTable();

        return static::$hasNotesColumn[$table] ??= Schema::hasColumn($table, 'notes');
    }
}
