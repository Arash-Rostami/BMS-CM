<?php

namespace App\Configurators;

use Filament\Actions\Exports\ExportColumn;

class FilamentExportDefaults
{
    public static function configure(): void
    {
        ExportColumn::configureUsing(fn (ExportColumn $column) => $column->preventFormulaInjection());
    }
}
