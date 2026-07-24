<?php

namespace App\Filament\Resources\Master\CurrencyResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Currency;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class CurrencyExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Currency::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('resources/currency/strings.export.id')),
            ExportColumn::make('name')
                ->label(__('resources/currency/strings.export.name')),
            ExportColumn::make('english_name')
                ->label(__('resources/currency/strings.export.english_name')),
            ExportColumn::make('description')
                ->label(__('resources/currency/strings.export.description')),
            ExportColumn::make('is_active')
                ->label(__('resources/currency/strings.export.is_active')),
            ExportColumn::make('creator.name')
                ->label(__('resources/currency/strings.export.creator')),
            ExportColumn::make('updater.name')
                ->label(__('resources/currency/strings.export.updater')),
            ExportColumn::make('created_at')
                ->label(__('resources/currency/strings.export.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('resources/currency/strings.export.updated_at')),
        ];
    }
}
