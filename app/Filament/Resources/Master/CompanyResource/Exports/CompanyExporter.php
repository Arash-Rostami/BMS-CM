<?php

namespace App\Filament\Resources\Master\CompanyResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Company;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;


class CompanyExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Company::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('english_name'),
            ExportColumn::make('description'),
            ExportColumn::make('creator.name'),
            ExportColumn::make('updater.name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }
}
