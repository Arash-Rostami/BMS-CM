<?php

namespace App\Services\Imports\Stages;

use App\Services\Imports\ImportRowContext;
use Closure;

class RunModuleRecalculation
{
    public function handle(ImportRowContext $context, Closure $next): ImportRowContext
    {
        $importer = $context->options['__importer'] ?? null;

        if ($importer && method_exists($importer, 'recalculateAfterPersist')) {
            $importer->recalculateAfterPersist();
        }

        return $next($context);
    }
}
