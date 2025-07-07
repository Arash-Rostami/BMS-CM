<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use App\Models\Status;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;

trait Form
{

    public static function getCustomType(): Hidden
    {
        return Hidden::make('custom_type')
            ->default(false);
    }

    public static function getCustomEnglishType(): Hidden
    {
        return Hidden::make('custom_english_type')
            ->default(false);
    }

    public static function getType(): Select
    {
        return Select::make('type')
            ->label(__('resources/status/strings.form.type'))
            ->required(fn(Get $get) => !$get('custom_type'))
            ->options(fn() => Status::query()
                    ->distinct()
                    ->pluck('type', 'type')
                    ->toArray() + ['...' => __('resources/status/strings.form.custom')]
            )
            ->reactive()
            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                $isCustom = $state === '...';
                $set('custom_type', $isCustom);
                $set('custom_english_type', $isCustom);

                if (!$isCustom) {
                    $english = Status::where('type', $state)
                        ->value('english_type');
                    $set('custom_english_type', false);
                    $set('english_type', $english);
                }
            })
            ->visible(fn(Get $get) => !$get('custom_type'))
            ->dehydrated(fn(Get $get) => !$get('custom_type'));
    }

    public static function getTypeCustomField(): TextInput
    {
        return TextInput::make('type')
            ->label(__('resources/status/strings.form.type') . ' (' . __('resources/status/strings.form.custom') . ')')
            ->placeholder(__('resources/status/strings.form.custom'))
            ->maxLength(255)
            ->required(fn(Get $get) => $get('custom_type'))
            ->visible(fn(Get $get) => $get('custom_type'))
            ->dehydrated(fn(Get $get) => $get('custom_type'))
            ->validationAttribute(__('resources/status/strings.form.type'));
    }

    public static function getEnglishType(): Select
    {
        return Select::make('english_type')
            ->label(__('resources/status/strings.form.english_type'))
            ->required(fn(Get $get) => !$get('custom_english_type'))
            ->options(fn() => Status::query()
                    ->distinct()
                    ->pluck('english_type', 'english_type')
                    ->toArray()
                + ['...' => __('resources/status/strings.form.english_custom')]
            )
            ->searchable()
            ->reactive()
            ->afterStateUpdated(fn(Get $get, Set $set, $state) => $set('custom_english_type', $state === '...'))
            ->visible(fn(Get $get) => !$get('custom_english_type'))
            ->dehydrated(fn(Get $get) => !$get('custom_english_type'));
    }

    public static function getEnglishTypeCustomField(): TextInput
    {
        return TextInput::make('english_type')
            ->label(
                __('resources/status/strings.form.english_type')
                . ' (' . __('resources/status/strings.form.custom') . ')'
            )
            ->placeholder(__('resources/status/strings.form.custom'))
            ->maxLength(255)
            ->required(fn(Get $get) => $get('custom_english_type'))
            ->visible(fn(Get $get) => $get('custom_english_type'))
            ->dehydrated(fn(Get $get) => $get('custom_english_type'))
            ->validationAttribute(__('resources/status/strings.form.english_type'));
    }

    public static function getName(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/status/strings.form.name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[\x{0600}-\x{06FF}\s]+$/u')
            ->unique(column: 'name', ignoreRecord: true)
            ->placeholder(__('resources/status/strings.form.validation_name'))
            ->validationMessages([
                'regex' => __('resources/status/strings.form.validation_name'),
            ])
            ->validationAttribute(__('resources/status/strings.form.name'));
    }

    public static function getEnglishName(): TextInput
    {
        return TextInput::make('english_name')
            ->label(__('resources/status/strings.form.english_name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[A-Za-z\s]+$/')
            ->unique(column: 'english_name', ignoreRecord: true)
            ->placeholder(__('resources/status/strings.form.validation_english_name'))
            ->validationMessages([
                'regex' => __('resources/status/strings.form.validation_english_name'),
            ])
            ->validationAttribute(__('resources/status/strings.form.english_name'));
    }
}
