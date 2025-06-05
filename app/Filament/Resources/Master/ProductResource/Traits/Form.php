<?php


namespace App\Filament\Resources\Master\ProductResource\Traits;

use App\Models\Product;

use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Str;


trait Form
{
    public static function getNameField(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/product/strings.form.name'))
            ->required(fn(Get $get) => $get('use_custom_name') === true)
            ->visible(fn($get) => $get('use_custom_name'))
            ->maxLength(255)
            ->rule('regex:/^[\x{0600}-\x{06FF}\s]+$/u')
            ->unique(table: 'products', column: 'name', ignoreRecord: true)
            ->placeholder(__('resources/product/strings.form.validation_name_placeholder'))
            ->validationMessages([
                'regex' => __('resources/product/strings.form.validation_name'),
                'unique' => __('resources/product/strings.form.validation_name_unique'),
                'required' => __('resources/product/strings.form.validation_name_required')
            ])
            ->live()
            ->validationAttribute(__('resources/product/strings.form.name'));
    }

    public static function getEnglishNameField(): TextInput
    {
        return TextInput::make('english_name')
            ->label(__('resources/product/strings.form.english_name'))
            ->required(fn(Get $get) => $get('use_custom_name') === true)
            ->visible(fn($get) => $get('use_custom_name'))
            ->maxLength(255)
            ->rule('regex:/^[A-Za-z0-9\s-]+$/')
            ->unique(table: 'products', column: 'english_name', ignoreRecord: true)
            ->placeholder(__('resources/product/strings.form.validation_english_name_placeholder'))
            ->validationMessages([
                'regex' => __('resources/product/strings.form.validation_english_name'),
                'unique' => __('resources/product/strings.form.validation_english_name_unique'),
                'required' => __('resources/product/strings.form.validation_english_name_required')
            ])
            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state) {
                if (($get('slug') ?? '') === Str::slug($old)) {
                    $set('slug', Str::slug($state));
                }
            })
            ->live(onBlur: true)
            ->dehydrateStateUsing(fn($state) => ucwords(strtolower($state)))
            ->validationAttribute(__('resources/product/strings.form.english_name'));
    }

    public static function getSlugField(): Placeholder
    {
        return Placeholder::make('slug')
            ->label(__('resources/product/strings.form.slug'))
            ->content(fn(?Product $record): string => $record?->slug ?? 'N/A')
            ->hidden(fn(?Product $record): bool => $record === null);
    }


    public static function getDescriptionField(): Textarea
    {
        return Textarea::make('description')
            ->label(__('resources/product/strings.form.description'))
            ->maxLength(65535)
            ->nullable();
    }

    public static function getCodeField(): TextInput
    {
        return TextInput::make('code')
            ->label(__('resources/product/strings.form.code'))
            ->unique(table: 'products', column: 'code', ignoreRecord: true)
            ->maxLength(255)
            ->required()
            ->placeholder(__('resources/product/strings.form.validation_code_placeholder'))
            ->validationMessages([
                'unique' => __('resources/product/strings.form.validation_code_unique'),
                'required' => __('resources/product/strings.form.validation_code_required')
            ])
            ->validationAttribute(__('resources/product/strings.form.code'));
    }

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
            ->onColor('success')
            ->onIcon('heroicon-m-check')
            ->offIcon('heroicon-m-x-mark')
            ->reactive()
            ->dehydrated(false)
            ->default(false)
            ->afterStateHydrated(function (Toggle $component) {
                $record = $component->getRecord();
                if ($record && filled($record->name) && filled($record->english_name)) {
                    $component->state(true);
                }
            });
    }
}
