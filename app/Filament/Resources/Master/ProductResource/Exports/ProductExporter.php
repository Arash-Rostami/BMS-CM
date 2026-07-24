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

    protected static function eagerLoadRelations(): array
    {
        return ['category', 'specifications'];
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label(__('resources/product/strings.export.id')),
            ExportColumn::make('category_path')
                ->label(__('resources/product/strings.export.category_path'))
                ->state(fn (Product $record): string => $record->category ? $record->category->sortAncestors() : ''),
            ExportColumn::make('category.name')->label(__('resources/product/strings.export.main_category')),
            ExportColumn::make('name')->label(__('resources/product/strings.export.name')),
            ExportColumn::make('english_name')->label(__('resources/product/strings.export.english_name')),
            ExportColumn::make('code')->label(__('resources/product/strings.export.code')),
            ExportColumn::make('attributes')
                ->label(__('resources/product/strings.export.attributes'))
                ->state(fn (Product $record): string => implode(', ', Arr::wrap($record->attributes))),
            ExportColumn::make('slug')->label(__('resources/product/strings.export.slug')),
            ExportColumn::make('description')->label(__('resources/product/strings.export.description')),
            ExportColumn::make('in_stock')->label(__('resources/product/strings.export.in_stock')),
            ExportColumn::make('is_active')->label(__('resources/product/strings.export.is_active')),
            ExportColumn::make('roll_sheet_type')->label(__('resources/product/strings.export.roll_sheet_type'))
                ->state(fn (Product $record): ?string => $record->determineRollOrSheetType() ?? '-'),
            ExportColumn::make('specifications.hs_code')->label(__('resources/product/strings.export.hs_code'))
                ->state(fn (Product $record): ?string => $record->specifications->first()?->hs_code),
            ExportColumn::make('specifications.import_duty')->label(__('resources/product/strings.export.import_duty'))
                ->state(fn (Product $record): ?string => $record->specifications->first()?->import_duty),
            ExportColumn::make('specifications.packing_type')->label(__('resources/product/strings.export.packing_type'))
                ->state(fn (Product $record): ?string => $record->specifications->first()?->packing_type),
            ExportColumn::make('specifications.vat_exempt')->label(__('resources/product/strings.export.vat_exempt'))
                ->state(fn (Product $record): string => $record->specifications->first()?->vat_exempt ? __('resources/product/strings.export.yes') : __('resources/product/strings.export.no')),
            ExportColumn::make('specifications.tax_id')->label(__('resources/product/strings.export.tax_id'))
                ->state(fn (Product $record): ?string => $record->specifications->first()?->tax_id),
            ExportColumn::make('specifications.manufacturer')->label(__('resources/product/strings.export.manufacturer'))
                ->state(fn (Product $record): ?string => $record->specifications->first()?->manufacturer),
            ExportColumn::make('specifications.import_licenses')->label(__('resources/product/strings.export.import_licenses'))
                ->state(function (Product $record): string {
                    $licenses = $record->specifications->first()?->import_licenses;
                    if (! is_string($licenses) || empty($licenses)) {
                        return '';
                    }
                    $allLicenses = Lang::get('resources/product/strings.form.licenses');
                    $licensesArray = array_map('trim', explode(',', $licenses));

                    return collect($licensesArray)
                        ->map(fn ($licenseKey) => $allLicenses[$licenseKey] ?? $licenseKey)
                        ->implode('| ');
                }),
            ExportColumn::make('specifications.extra')->label(__('resources/product/strings.export.extra'))
                ->state(function (Product $record): string {
                    $extra = $record->specifications->first()?->extra;
                    if (empty($extra) || ! is_array($extra)) {
                        return '';
                    }

                    return collect($extra)
                        ->map(fn ($value, $key) => __('resources/product/strings.export.extra_template', ['key' => $key, 'value' => $value]))
                        ->implode('| ');
                }),
            ExportColumn::make('creator.name')->label(__('resources/product/strings.export.creator')),
            ExportColumn::make('updater.name')->label(__('resources/product/strings.export.updater')),
            ExportColumn::make('created_at')->label(__('resources/product/strings.export.created_at')),
            ExportColumn::make('updated_at')->label(__('resources/product/strings.export.updated_at')),
        ];
    }
}
