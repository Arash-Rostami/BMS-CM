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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

trait Form
{
    public static function getInquiryCodeField(): TextInput
    {
        return TextInput::make('inquiry_code')
            ->label(__('resources/product/strings.form.inquiry_code'))
            ->helperText(__('resources/product/strings.form.helper_inquiry_code'))
            ->live(onBlur: true)
            ->dehydrated(false)
            ->visibleOn('create')
            ->afterStateUpdated(function ($state, Set $set) {
                $set('confirmed_create', false);

                if (blank($state)) {
                    $set('inquiry_result', null);

                    return;
                }

                $code = Product::normalizeCode($state);

                $set('inquiry_result', match (true) {
                    Product::where('code', $code)->exists() => 'found',
                    Product::onlyTrashed()->where('code', $code)->exists() => 'trashed',
                    default => 'not_found',
                });
            });
    }

    public static function getExistingProductDetails(): Section
    {
        return Section::make(__('resources/product/strings.form.existing_product_title'))
            ->icon('heroicon-o-exclamation-triangle')
            ->schema([
                TextEntry::make('existing_name')
                    ->label(__('resources/product/strings.form.name'))
                    ->state(fn (Get $get) => static::findByInquiryCode($get('inquiry_code'))?->getLocalizedNameAttribute() ?? '-'),
                TextEntry::make('existing_category')
                    ->label(__('resources/product/strings.form.category'))
                    ->state(fn (Get $get) => static::findByInquiryCode($get('inquiry_code'))?->category?->getLocalizedNameAttribute() ?? '-'),
                TextEntry::make('existing_in_stock')
                    ->label(__('resources/product/strings.form.in_stock'))
                    ->state(fn (Get $get) => static::findByInquiryCode($get('inquiry_code'))?->in_stock
                        ? __('resources/product/strings.table.in_stock_true')
                        : __('resources/product/strings.table.in_stock_false')),
                TextEntry::make('existing_is_active')
                    ->label(__('resources/product/strings.form.is_active'))
                    ->state(fn (Get $get) => static::findByInquiryCode($get('inquiry_code'))?->is_active
                        ? __('resources/product/strings.table.only_active')
                        : __('resources/product/strings.table.only_inactive')),
            ])
            ->columns(2)
            ->visible(fn (Get $get, $operation) => $operation === 'create' && $get('inquiry_result') === 'found');
    }

    public static function getTrashedProductDetails(): Section
    {
        return Section::make(__('resources/product/strings.form.trashed_product_title'))
            ->description(__('resources/product/strings.form.trashed_product_description'))
            ->icon('heroicon-o-archive-box-x-mark')
            ->schema([
                TextEntry::make('trashed_product_link')
                    ->hiddenLabel()
                    ->state(__('resources/product/strings.form.view_trashed_products'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('warning')
                    ->url(fn () => static::getUrl('index', [
                        'tableFilters' => ['trashed' => ['value' => '0']],
                    ]))
                    ->openUrlInNewTab(),
            ])
            ->visible(fn (Get $get, $operation) => $operation === 'create' && $get('inquiry_result') === 'trashed');
    }

    public static function getNotFoundConfirmation(): Section
    {
        return Section::make(__('resources/product/strings.form.not_found_title'))
            ->description(__('resources/product/strings.form.not_found_description'))
            ->icon('heroicon-o-question-mark-circle')
            ->schema([
                Toggle::make('confirmed_create')
                    ->label(__('resources/product/strings.form.confirm_create'))
                    ->live()
                    ->dehydrated(false)
                    ->default(false)
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        if ($state) {
                            $set('code', Product::normalizeCode($get('inquiry_code')));
                        }
                    }),
            ])
            ->visible(fn (Get $get, $operation) => $operation === 'create' && $get('inquiry_result') === 'not_found' && ! $get('confirmed_create'));
    }

    protected static array $inquiryCodeLookupCache = [];

    public static function findByInquiryCode(?string $code): ?Product
    {
        $normalized = Product::normalizeCode($code);

        if (blank($normalized)) {
            return null;
        }

        return static::$inquiryCodeLookupCache[$normalized] ??= Product::where('code', $normalized)->first();
    }

    public static function specificationHasData(array $data): bool
    {
        foreach (['hs_code', 'import_duty', 'packing_type', 'tax_id', 'manufacturer', 'import_licenses'] as $field) {
            if (filled($data[$field] ?? null)) {
                return true;
            }
        }

        if (($data['vat_exempt'] ?? false) === true) {
            return true;
        }

        return filled($data['extra'] ?? null);
    }

    public static function getAttributesJsonField(): TagsInput
    {
        return TagsInput::make('attributes')
            ->label(__('resources/product/strings.form.attributes'))
            ->helperText(__('resources/product/strings.form.helper_attributes'))
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
            ->live(onBlur: true)
            ->afterStateUpdated(fn ($state, Set $set) => $set('code', Product::normalizeCode($state)))
            ->unique(table: 'products', column: 'code', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())
            ->dehydrateStateUsing(fn ($state) => Product::normalizeCode($state))
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
            ->unique(table: 'products', column: 'english_name', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())
            ->placeholder(__('resources/product/strings.form.validation_english_name_placeholder'))
            ->validationMessages([
                'unique' => __('resources/product/strings.form.validation_english_name_unique'),
                'required' => __('resources/product/strings.form.validation_english_name_required'),
                'max' => __('resources/product/strings.form.validation_english_name_max'),
                'string' => __('resources/product/strings.form.validation_english_name_string'),
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
            ->helperText(__('resources/product/strings.form.helper_extra'))
            ->keyLabel(__('resources/product/strings.form.key_label'))
            ->valueLabel(__('resources/product/strings.form.value_label'))
            ->addActionLabel(__('resources/product/strings.form.add_extra_field_button'))
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
            ->validationMessages([
                '*.in' => __('resources/product/strings.form.validation_import_licenses_in'),
            ])
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
            ->unique(table: 'products', column: 'name', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())
            ->placeholder(__('resources/product/strings.form.validation_name_placeholder'))
            ->validationMessages([
                'unique' => __('resources/product/strings.form.validation_name_unique'),
                'required' => __('resources/product/strings.form.validation_name_required'),
                'max' => __('resources/product/strings.form.validation_name_max'),
                'string' => __('resources/product/strings.form.validation_name_string'),
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
