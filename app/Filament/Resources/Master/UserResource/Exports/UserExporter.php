<?php

namespace App\Filament\Resources\Master\UserResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class UserExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('phone'),
            ExportColumn::make('email'),
            ExportColumn::make('company'),
            ExportColumn::make('department.name'),
            ExportColumn::make('position'),
            ExportColumn::make('role'),
            ExportColumn::make('image'),
            ExportColumn::make('status'),
            ExportColumn::make('ip'),
            ExportColumn::make('last_log_in'),
            ExportColumn::make('last_log_out'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }
}
