<?php

namespace App\Services\Imports\Stages;

use App\Services\Imports\ImportRowContext;
use Closure;

class SyncEavAttributes
{
    public function handle(ImportRowContext $context, Closure $next): ImportRowContext
    {
        if ($context->pendingExtraAttributes) {
            $context->record->syncCustomAttributes($context->pendingExtraAttributes);
        }

        return $next($context);
    }
}
