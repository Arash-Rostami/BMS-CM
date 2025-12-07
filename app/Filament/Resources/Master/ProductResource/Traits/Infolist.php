<?php

namespace App\Filament\Resources\Master\ProductResource\Traits;

use App\Filament\Resources\Master\ProductResource\Enums\InStockStatus;
use App\Filament\Resources\Master\ProductResource\Enums\IsActiveStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Lang;

trait Infolist
{
    public static function viewAttributesJson(): KeyValueEntry
    {
        return KeyValueEntry::make('attributes')
            ->label(__('resources/product/strings.table.attributes'))
            ->getStateUsing(function ($record): array {
                static $cached = null;

                if ($cached === null) {
                    $cached = __('resources/product/strings.attributes_manager');
                }

                $typed = $record->typedAttributes();
                $out = [];

                foreach ($typed as $k => $v) {
                    $lk = strtolower($k);
                    $lbl = $cached[$lk] ?? $k;
                    $out[$lbl] = $v;
                }

                return $out;
            })
            ->valueLabel('')
            ->keyLabel(__('resources/product/strings.table.attributes'))
            ->hiddenLabel()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewCategory(): TextEntry
    {
        return TextEntry::make('category.name')
            ->label(__('resources/product/strings.form.category'))
            ->formatStateUsing(fn($record, $state) => app()->getLocale() != 'fa' ? optional($record->category)->english_name : $state)
            ->icon('heroicon-m-folder')
            ->placeholder('-');
    }

    public static function viewCode(): TextEntry
    {
        return TextEntry::make('code')
            ->label(__('resources/product/strings.form.code'))
            ->icon('heroicon-m-qr-code')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/product/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/product/strings.form.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewDescription(): TextEntry
    {
        return TextEntry::make('description')
            ->label(__('resources/product/strings.form.description'))
            ->markdown()
            ->prose()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewEnglishName(): TextEntry
    {
        return TextEntry::make('english_name')
            ->label(__('resources/product/strings.form.english_name'))
            ->icon('heroicon-m-language')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewExtra(): KeyValueEntry
    {
        return KeyValueEntry::make('specifications.0.extra')
            ->columnSpanFull()
            ->keyLabel(__('resources/product/strings.form.extra'))
            ->valueLabel('')
            ->hiddenLabel()
            ->placeholder('-');
    }

    public static function viewHsCode(): TextEntry
    {
        return TextEntry::make('specifications.0.hs_code')
            ->label(__('resources/product/strings.form.hs_code'))
            ->icon('heroicon-m-hashtag')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewImportDuty(): TextEntry
    {
        return TextEntry::make('specifications.0.import_duty')
            ->label(__('resources/product/strings.form.import_duty'))
            ->icon('heroicon-m-receipt-percent')
            ->placeholder('-');
    }

    public static function viewImportLicenses(): TextEntry
    {
        return TextEntry::make('specifications.0.import_licenses')
            ->label(__('resources/product/strings.form.import_licenses'))
            ->columnSpanFull()
            ->icon('heroicon-m-document-check')
            ->formatStateUsing(function ($state) {
                if (!is_string($state) || empty($state)) {
                    return $state;
                }
                $allLicenses = Lang::get('resources/product/strings.form.licenses');
                $licenseKeys = array_map('trim', explode(',', $state));

                return collect($licenseKeys)
                    ->map(fn($licenseKey) => $allLicenses[$licenseKey] ?? $licenseKey)
                    ->implode(' | ');
            })
            ->placeholder('-');
    }

    public static function viewInStock(): TextEntry
    {
        return TextEntry::make('in_stock')
            ->label(__('resources/product/strings.form.in_stock'))
            ->badge()
            ->formatStateUsing(fn(bool $state): string => InStockStatus::tryFrom((int)$state)?->getLabel() ?? (string)$state)
            ->color(fn(bool $state): string => InStockStatus::tryFrom((int)$state)?->getColor() ?? 'gray')
            ->placeholder('-');
    }

    public static function viewIsActive(): TextEntry
    {
        return TextEntry::make('is_active')
            ->label(__('resources/product/strings.form.is_active'))
            ->badge()
            ->formatStateUsing(fn(bool $state): string => IsActiveStatus::tryFrom((int)$state)?->getLabel() ?? (string)$state)
            ->color(fn(bool $state): string => IsActiveStatus::tryFrom((int)$state)?->getColor() ?? 'gray')
            ->placeholder('-');
    }

    public static function viewManufacturer(): TextEntry
    {
        return TextEntry::make('specifications.0.manufacturer')
            ->label(__('resources/product/strings.form.manufacturer'))
            ->icon('heroicon-m-building-storefront')
            ->placeholder('-');
    }

    public static function viewName(): TextEntry
    {
        return TextEntry::make('name')
            ->label(__('resources/product/strings.form.name'))
            ->icon('heroicon-m-tag')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewPackingType(): TextEntry
    {
        return TextEntry::make('specifications.0.packing_type')
            ->label(__('resources/product/strings.form.packing_type'))
            ->icon('heroicon-m-archive-box')
            ->placeholder('-');
    }

    public static function viewSlug(): TextEntry
    {
        return TextEntry::make('slug')
            ->label(__('resources/product/strings.form.slug'))
            ->icon('heroicon-m-link')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewSpecCreatedAt(): TextEntry
    {
        return TextEntry::make('specifications.0.created_at')
            ->label(__('resources/product/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewSpecCreator(): TextEntry
    {
        return TextEntry::make('specifications.0.creator.name')
            ->label(__('resources/product/strings.form.creator'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewSpecUpdatedAt(): TextEntry
    {
        return TextEntry::make('specifications.0.updated_at')
            ->label(__('resources/product/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewSpecUpdater(): TextEntry
    {
        return TextEntry::make('specifications.0.updater.name')
            ->label(__('resources/product/strings.form.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }

    public static function viewTaxId(): TextEntry
    {
        return TextEntry::make('specifications.0.tax_id')
            ->label(__('resources/product/strings.form.tax_id'))
            ->icon('heroicon-m-identification')
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/product/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/product/strings.form.updater'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }

    public static function viewVatExempt(): IconEntry
    {
        return IconEntry::make('specifications.0.vat_exempt')
            ->label(__('resources/product/strings.form.vat_exempt'))
            ->boolean()
            ->trueIcon('heroicon-m-check-circle')
            ->falseIcon('heroicon-m-x-circle')
            ->trueColor('success')
            ->falseColor('danger');
    }
}
