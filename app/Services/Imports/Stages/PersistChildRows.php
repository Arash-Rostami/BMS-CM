<?php

namespace App\Services\Imports\Stages;

use App\Services\Imports\GroupRowFailedException;
use App\Services\Imports\ImportRowContext;
use Closure;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Illuminate\Validation\ValidationException;
use LogicException;

class PersistChildRows
{
    public function handle(ImportRowContext $context, Closure $next): ImportRowContext
    {
        if (empty($context->pendingChildRows)) {
            return $next($context);
        }

        $importer = $context->options['__importer'] ?? null;

        if (! $importer || ! method_exists($importer, 'childImporterInstance')) {
            throw new LogicException(
                'Import row has pending child rows but '.($importer ? $importer::class : 'the importer').' does not implement childImporterInstance() — child rows would be silently dropped.'
            );
        }

        $childImporter = $importer->childImporterInstance();
        $childImporter->forParent($context->record);

        foreach ($context->pendingChildRows as $offset => $rowData) {
            try {
                $childImporter($rowData);
            } catch (RowImportFailedException $exception) {
                throw new GroupRowFailedException($offset, $exception->getMessage());
            } catch (ValidationException $exception) {
                throw new GroupRowFailedException($offset, collect($exception->errors())->flatten()->implode(' '));
            }
        }

        return $next($context);
    }
}
