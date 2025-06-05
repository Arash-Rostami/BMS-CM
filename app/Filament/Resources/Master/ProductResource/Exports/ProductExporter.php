<?php

namespace App\Filament\Resources\Master\ProductResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Support\Arr;

class ProductExporter extends Exporter
{

    use ExportDefaults;

    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('category_path')
                ->label('Category Path')
                ->state(fn(Product $record): string => $record->category ? $record->category->sortAncestors() : ''),
            ExportColumn::make('category.name')->label('Main Category'),
            ExportColumn::make('name'),
            ExportColumn::make('english_name'),
            ExportColumn::make('code'),
            ExportColumn::make('attributes')
                ->label('Product Attributes')
                ->state(fn(Product $record): string => implode(', ', Arr::wrap($record->attributes))
                ),
            ExportColumn::make('slug'),
            ExportColumn::make('description'),
            ExportColumn::make('in_stock')->label('Stock'),
            ExportColumn::make('creator.name'),
            ExportColumn::make('updater.name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }
}
