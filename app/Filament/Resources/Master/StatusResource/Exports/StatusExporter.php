<?php

namespace App\Filament\Resources\Master\StatusResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Status;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;


class StatusExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Status::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('english_name'),
            ExportColumn::make('type'),
            ExportColumn::make('english_type'),
            ExportColumn::make('creator.name'),
            ExportColumn::make('updater.name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }
}
