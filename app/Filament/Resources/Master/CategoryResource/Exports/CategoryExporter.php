<?php
namespace App\Filament\Resources\Master\CategoryResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Category;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;

class CategoryExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = Category::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label('ID'),
            ExportColumn::make('name'),
            ExportColumn::make('english_name'),
            ExportColumn::make('level'),
            ExportColumn::make('parent.name'),
            ExportColumn::make('active'),
            ExportColumn::make('creator.name'),
            ExportColumn::make('updater.name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }
}
