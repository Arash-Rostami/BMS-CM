<?php

namespace App\Services\Imports\Stages;

use App\Services\Imports\ImportRowContext;
use App\Services\StatusWorkflow;
use Closure;

class ApplyColumnFallbacks
{
    public function handle(ImportRowContext $context, Closure $next): ImportRowContext
    {
        if ($context->record->exists) {
            return $next($context);
        }

        foreach ($context->columns as $column) {
            if ($column->statusType !== null && ($initial = StatusWorkflow::initialFor($column->statusType))) {
                $context->record->{$column->name} = $initial->id;

                continue;
            }

            if (! $column->hasFallback()) {
                continue;
            }

            if (blank($context->record->{$column->name})) {
                $context->record->{$column->name} = $column->resolveFallback($context);
            }
        }

        return $next($context);
    }
}
