<?php

namespace App\Filament\Resources\Master\BankResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Bank;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class BankExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Bank::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('resources/bank/strings.export.id')),
            ExportColumn::make('name')
                ->label(__('resources/bank/strings.export.name')),
            ExportColumn::make('english_name')
                ->label(__('resources/bank/strings.export.english_name')),
            ExportColumn::make('description')
                ->label(__('resources/bank/strings.export.description')),
            ExportColumn::make('is_active')
                ->label(__('resources/bank/strings.export.is_active')),
            ExportColumn::make('creator.name')
                ->label(__('resources/bank/strings.export.creator')),
            ExportColumn::make('updater.name')
                ->label(__('resources/bank/strings.export.updater')),
            ExportColumn::make('created_at')
                ->label(__('resources/bank/strings.export.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('resources/bank/strings.export.updated_at')),
        ];
    }
}
