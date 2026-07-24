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

    protected static function eagerLoadRelations(): array
    {
        return ['department'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('resources/user/strings.export.id')),
            ExportColumn::make('name')
                ->label(__('resources/user/strings.export.name')),
            ExportColumn::make('phone')
                ->label(__('resources/user/strings.export.phone')),
            ExportColumn::make('email')
                ->label(__('resources/user/strings.export.email')),
            ExportColumn::make('company')
                ->label(__('resources/user/strings.export.company')),
            ExportColumn::make('department.name')
                ->label(__('resources/user/strings.export.department')),
            ExportColumn::make('position')
                ->label(__('resources/user/strings.export.position')),
            ExportColumn::make('role')
                ->label(__('resources/user/strings.export.role')),
            ExportColumn::make('image')
                ->label(__('resources/user/strings.export.image')),
            ExportColumn::make('status')
                ->label(__('resources/user/strings.export.status')),
            ExportColumn::make('ip')
                ->label(__('resources/user/strings.export.ip')),
            ExportColumn::make('last_log_in')
                ->label(__('resources/user/strings.export.last_log_in')),
            ExportColumn::make('last_log_out')
                ->label(__('resources/user/strings.export.last_log_out')),
            ExportColumn::make('created_at')
                ->label(__('resources/user/strings.export.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('resources/user/strings.export.updated_at')),
        ];
    }
}
