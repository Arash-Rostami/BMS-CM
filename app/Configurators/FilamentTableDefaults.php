<?php

namespace App\Configurators;

use Filament\Tables\Table;

class FilamentTableDefaults
{
    public static function configure(): void
    {
        Table::configureUsing(fn (Table $table) => $table
            ->paginated([25, 50, 100])
            ->persistInSession(fn (): bool => (bool) session('persist_table_state', false))
            ->stackedOnMobile());
    }
}
