<?php

namespace App\Filament\Resources\Master\ProductResource\Traits;

use App\Models\Product;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait Form
{
    public static function doubleCheckCode(): TextInput
    {
        return TextInput::make('code')
            ->label(__('resources/product/strings.form.code'))
            ->required()
            ->live()
            ->dehydrated(false)
            ->validationMessages([
                'required' => __('resources/product/strings.form.validation_code_required'),
            ])
            ->validationAttribute(__('resources/product/strings.form.code'))
            ->afterStateUpdated(function ($state, Get $get, Set $set) {
                if ($get('action') !== 'check') {
                    $set('check_result', null);

                    return;
                }

                if (empty($state)) {
                    $set('check_result', null);

                    return;
                }

                $set('check_result', Product::where('code', $state)->exists() ? '✅' : '❌');
            });
    }

    public static function enquiryResponse(): TextEntry
    {
        return TextEntry::make('check_result_view')
            ->label(__('resources/product/strings.form.check_result'))
            ->state(fn (Get $get) => $get('check_result'))
            ->visible(fn (Get $get) => $get('action') === 'check' && $get('check_result') !== null);
    }

    public static function getAttributesJsonField(): TagsInput
    {
        return TagsInput::make('attributes')
            ->label(__('resources/product/strings.form.attributes'))
            ->nullable();
    }

    public static function getClassificationOptions(): Toggle
    {
        return Toggle::make('use_custom_name')
            ->label(__('resources/product/strings.form.classify_by_name'))
            ->helperText(__('resources/product/strings.form.helper_classify_by_name'))
            ->onColor('success')
            ->onIcon('heroicon-m-check')
            ->offIcon('heroicon-m-x-mark')
            ->live()
            ->dehydrated(false)
            ->default(false)
            ->afterStateHydrated(function (Toggle $component) {
                $record = $component->getRecord();
                if ($record && filled($record->name) && filled($record->english_name)) {
                    $component->state(true);
                }
            });
    }

    public static function getCodeField(): TextInput
    {
        return TextInput::make('code')
            ->label(__('resources/product/strings.form.code'))
            ->unique(table: 'products', column: 'code', ignoreRecord: true)
            ->maxLength(255)
            ->required()
            ->placeholder(__('resources/product/strings.form.validation_code_placeholder'))
            ->helperText(__('resources/product/strings.form.helper_code'))
            ->validationMessages([
                'unique' => __('resources/product/strings.form.validation_code_unique'),
                'required' => __('resources/product/strings.form.validation_code_required'),
                'max' => __('resources/product/strings.form.validation_code_max'),
            ])
            ->validationAttribute(__('resources/product/strings.form.code'));
    }

    public static function getDescriptionField(): Textarea
    {
        return Textarea::make('description')
            ->label(__('resources/product/strings.form.description'))
            ->maxLength(65535)
            ->nullable()
            ->validationAttribute(__('resources/product/strings.form.description'))
            ->validationMessages([
                'max' => __('resources/product/strings.form.validation_description_max'),
            ]);
    }

    public static function getEnglishNameField(): TextInput
    {
        return TextInput::make('english_name')
            ->label(__('resources/product/strings.form.english_name'))
            ->required(fn (Get $get) => $get('use_custom_name') === true)
            ->visible(fn ($get) => $get('use_custom_name'))
            ->maxLength(255)
            ->rule(['string', 'max:255'])
            ->unique(table: 'products', column: 'english_name', ignoreRecord: true)
            ->placeholder(__('resources/product/strings.form.validation_english_name_placeholder'))
            ->validationMessages([
                'unique' => __('resources/product/strings.form.validation_english_name_unique'),
                'required' => __('resources/product/strings.form.validation_english_name_required'),
                'max' => __('resources/product/strings.form.validation_english_name_max'),
            ])
            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                if (($get('slug') ?? '') === Str::slug($old)) {
                    $set('slug', Str::slug($state));
                }
            })
            ->live(onBlur: true)
            ->dehydrateStateUsing(fn ($state) => ucwords(strtolower($state)))
            ->helperText(__('resources/product/strings.form.helper_english_name'))
            ->validationAttribute(__('resources/product/strings.form.english_name'));
    }

    public static function getExtra(): KeyValue
    {
        return KeyValue::make('extra')
            ->label(__('resources/product/strings.form.extra'))
            ->keyLabel(__('resources/product/strings.form.key_label'))
            ->valueLabel(__('resources/product/strings.form.value_label'))
            ->addActionLabel(__('resources/product/strings.form.add_spec_button'))
            ->columnSpan(2);
    }

    public static function getHsCode(): TextInput
    {
        return TextInput::make('hs_code')
            ->label(__('resources/product/strings.form.hs_code'))
            ->columnSpan(1)
            ->helperText(__('resources/product/strings.form.helper_hs_code'));
    }

    public static function getImportDuty(): TextInput
    {
        return TextInput::make('import_duty')
            ->label(__('resources/product/strings.form.import_duty'))
            ->columnSpan(1)
            ->helperText(__('resources/product/strings.form.helper_import_duty'));
    }

    public static function getImportLicense(): Select
    {
        return Select::make('import_licenses')
            ->label(__('resources/product/strings.form.import_licenses'))
            ->multiple()
            ->searchable()
            ->options(Lang::get('resources/product/strings.form.licenses'))
            ->columnSpan(2);
    }

    // SPECIFICATIONS

    public static function getInStockField(): Toggle
    {
        return Toggle::make('in_stock')
            ->label(__('resources/product/strings.form.in_stock'))
            ->onColor('success')
            ->onIcon('heroicon-m-check')
            ->offIcon('heroicon-m-x-mark')
            ->inline()
            ->default(true);
    }

    public static function getIsActive(): Toggle
    {
        return Toggle::make('is_active')
            ->label(__('resources/product/strings.form.is_active'))
            ->default(true)
            ->inline(true)
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->onColor('success')
            ->offColor('danger');
    }

    public static function getManufacturer(): TextInput
    {
        return TextInput::make('manufacturer')
            ->label(__('resources/product/strings.form.manufacturer'))
            ->columnSpan(1);
    }

    public static function getNameField(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/product/strings.form.name'))
            ->required(fn (Get $get) => $get('use_custom_name') === true)
            ->visible(fn ($get) => $get('use_custom_name'))
            ->maxLength(255)
            ->rule(['string', 'max:255'])
            ->unique(table: 'products', column: 'name', ignoreRecord: true)
            ->placeholder(__('resources/product/strings.form.validation_name_placeholder'))
            ->validationMessages([
                'unique' => __('resources/product/strings.form.validation_name_unique'),
                'required' => __('resources/product/strings.form.validation_name_required'),
                'max' => __('resources/product/strings.form.validation_name_max'),
            ])
            ->live()
            ->validationAttribute(__('resources/product/strings.form.name'))
            ->helperText(__('resources/product/strings.form.helper_name'));
    }

    public static function getPackagingType(): TextInput
    {
        return TextInput::make('packing_type')
            ->label(__('resources/product/strings.form.packing_type'))
            ->placeholder(__('resources/product/strings.form.packing_type_placeholder'))
            ->columnSpan(1);
    }

    public static function getSlugField(): TextEntry
    {
        return TextEntry::make('slug')
            ->label(__('resources/product/strings.form.slug'))
            ->state(fn (?Product $record): string => $record?->slug ?? __('resources/product/strings.form.na'))
            ->hidden(fn (?Product $record): bool => $record === null);
    }

    public static function getTaxId(): TextInput
    {
        return TextInput::make('tax_id')
            ->label(__('resources/product/strings.form.tax_id'))
            ->columnSpan(1);
    }

    public static function getVAT(): Toggle
    {
        return Toggle::make('vat_exempt')
            ->label(__('resources/product/strings.form.vat_exempt'))
            ->columnSpan(1);
    }
}
