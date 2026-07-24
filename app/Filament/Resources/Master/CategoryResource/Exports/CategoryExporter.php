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

    protected static function eagerLoadRelations(): array
    {
        return ['parent'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('resources/category/strings.export.id')),
            ExportColumn::make('name')
                ->label(__('resources/category/strings.export.name')),
            ExportColumn::make('english_name')
                ->label(__('resources/category/strings.export.english_name')),
            ExportColumn::make('level')
                ->label(__('resources/category/strings.export.level')),
            ExportColumn::make('parent.name')
                ->label(__('resources/category/strings.export.parent')),
            ExportColumn::make('active')
                ->label(__('resources/category/strings.export.active')),
            ExportColumn::make('creator.name')
                ->label(__('resources/category/strings.export.creator')),
            ExportColumn::make('updater.name')
                ->label(__('resources/category/strings.export.updater')),
            ExportColumn::make('created_at')
                ->label(__('resources/category/strings.export.created_at')),
            ExportColumn::make('updated_at')
                ->label(__('resources/category/strings.export.updated_at')),
        ];
    }
}
