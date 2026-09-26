<?php

namespace App\Services\Imports\Stages;

use App\Services\Imports\ImportRowContext;
use Closure;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;

class RejectMissingManualColumns
{
    public function handle(ImportRowContext $context, Closure $next): ImportRowContext
    {
        if ($context->record->exists) {
            return $next($context);
        }

        foreach ($context->columns as $column) {
            if (! $column->hasFallback() || ! $column->rejectIfStillBlank()) {
                continue;
            }

            if (blank($context->record->{$column->name})) {
                throw new RowImportFailedException(__('resources/general/strings.import.required_for_new_record', [
                    'label' => __($column->labelKey ?? $column->name),
                ]));
            }
        }

        return $next($context);
    }
}
