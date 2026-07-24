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
                ->label(__('resources/status/strings.export.id')),
            ExportColumn::make('name')
                ->label(__('resources/status/strings.export.name')),
            ExportColumn::make('english_name')
                ->label(__('resources/status/strings.export.english_name')),
            ExportColumn::make('type')
                ->label(__('resources/status/strings.export.type')),
            ExportColumn::make('english_type')
                ->label(__('resources/status/strings.export.english_type')),
            ExportColumn::make('creator.name')
                ->label(__('resources/status/strings.export.creator')),
            ExportColumn::make('updater.name')
                ->label(__('resources/status/strings.export.updater')),
            ExportColumn::make('created_at')
                ->label(__('resources/status/strings.export.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('resources/status/strings.export.updated_at')),
        ];
    }
}
