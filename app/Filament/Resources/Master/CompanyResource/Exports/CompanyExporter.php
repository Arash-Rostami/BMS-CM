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
                ->label(__('resources/company/strings.export.id')),
            ExportColumn::make('name')
                ->label(__('resources/company/strings.export.name')),
            ExportColumn::make('english_name')
                ->label(__('resources/company/strings.export.english_name')),
            ExportColumn::make('types')
                ->label(__('resources/company/strings.export.types'))
                ->formatStateUsing(fn ($state, Company $record) => implode(', ', $record->getFormattedTypesAttribute())),
            ExportColumn::make('description')
                ->label(__('resources/company/strings.export.description')),
            ExportColumn::make('is_active')
                ->label(__('resources/company/strings.export.is_active')),
            ExportColumn::make('creator.name')
                ->label(__('resources/company/strings.export.creator')),
            ExportColumn::make('updater.name')
                ->label(__('resources/company/strings.export.updater')),
            ExportColumn::make('created_at')
                ->label(__('resources/company/strings.export.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('resources/company/strings.export.updated_at')),
        ];
    }
}
