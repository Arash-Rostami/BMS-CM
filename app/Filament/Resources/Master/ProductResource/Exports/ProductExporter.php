<?php

namespace App\Filament\Resources\Master\ProductResource\Exports;

use App\Filament\Traits\ExportDefaults;
use App\Models\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;

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
                ->state(fn(Product $record): string => implode(', ', Arr::wrap($record->attributes))),
            ExportColumn::make('slug'),
            ExportColumn::make('description'),
            ExportColumn::make('in_stock')->label('Stock'),
            ExportColumn::make('is_active')->label('Active'),
            ExportColumn::make('roll_sheet_type')->label('Roll/Sheet Type')
                ->state(fn(Product $record): ?string => $record->determineRollOrSheetType() ?? '-'),
            //Specifications
            ExportColumn::make('specifications.hs_code')->label('HS Code')
                ->state(fn(Product $record): ?string => $record->specifications->first()?->hs_code),
            ExportColumn::make('specifications.import_duty')->label('Import Duty (%)')
                ->state(fn(Product $record): ?string => $record->specifications->first()?->import_duty),
            ExportColumn::make('specifications.packing_type')->label('Packing Type')
                ->state(fn(Product $record): ?string => $record->specifications->first()?->packing_type),
            ExportColumn::make('specifications.vat_exempt')->label('VAT Exempt')
                ->state(fn(Product $record): string => $record->specifications->first()?->vat_exempt ? 'Yes' : 'No'),
            ExportColumn::make('specifications.tax_id')->label('Tax ID')
                ->state(fn(Product $record): ?string => $record->specifications->first()?->tax_id),
            ExportColumn::make('specifications.manufacturer')->label('Manufacturer')
                ->state(fn(Product $record): ?string => $record->specifications->first()?->manufacturer),
            ExportColumn::make('specifications.import_licenses')->label('Licenses')
                ->state(function (Product $record): string {
                    $licenses = $record->specifications->first()?->import_licenses;
                    if (!is_string($licenses) || empty($licenses)) {
                        return '';
                    }
                    $allLicenses = Lang::get('resources/product/strings.form.licenses');
                    $licensesArray = array_map('trim', explode(',', $licenses));

                    return collect($licensesArray)
                        ->map(fn($licenseKey) => $allLicenses[$licenseKey] ?? $licenseKey)
                        ->implode('| ');
                }),
            ExportColumn::make('specifications.extra')->label('Extra Specifications')
                ->state(function (Product $record): string {
                    $extra = $record->specifications->first()?->extra;
                    if (empty($extra) || !is_array($extra)) {
                        return '';
                    }
                    return collect($extra)
                        ->map(fn($value, $key) => "$key: $value")
                        ->implode('| ');
                }),
            ExportColumn::make('creator.name'),
            ExportColumn::make('updater.name'),
            ExportColumn::make('created_at'),
            ExportColumn::make('updated_at'),
        ];
    }
}
