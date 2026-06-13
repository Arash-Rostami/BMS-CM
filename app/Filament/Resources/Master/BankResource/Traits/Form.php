<?php

namespace App\Filament\Resources\Master\BankResource\Traits;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

trait Form
{
    public static function getName(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/bank/strings.form.name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[\x{0600}-\x{06FF}\s\p{P}\d\*]+$/u')
            ->unique(column: 'name', ignoreRecord: true)
            ->placeholder(__('resources/bank/strings.form.validation_name'))
            ->validationMessages([
                'required' => __('resources/bank/strings.form.validation_name_required'),
                'regex' => __('resources/bank/strings.form.validation_name'),
                'unique' => __('resources/bank/strings.form.validation_name_unique'),
                'max' => __('resources/bank/strings.form.validation_name_max')
            ])
            ->validationAttribute(__('resources/bank/strings.form.name'))
            ->helperText(__('resources/bank/strings.form.helper_name'));
    }

    public static function getEnglishName(): TextInput
    {
        return TextInput::make('english_name')
            ->label(__('resources/bank/strings.form.english_name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[A-Za-z\s\p{P}\d\*]+$/')
            ->unique(column: 'english_name', ignoreRecord: true)
            ->placeholder(__('resources/bank/strings.form.validation_english_name'))
            ->validationMessages([
                'required' => __('resources/bank/strings.form.validation_english_name_required'),
                'regex' => __('resources/bank/strings.form.validation_english_name'),
                'unique' => __('resources/bank/strings.form.validation_english_name_unique'),
                'max' => __('resources/bank/strings.form.validation_english_name_max')
            ])
            ->validationAttribute(__('resources/bank/strings.form.english_name'))
            ->helperText(__('resources/bank/strings.form.helper_english_name'));
    }

    public static function getDescription(): Textarea
    {
        return Textarea::make('description')
            ->label(__('resources/bank/strings.form.description'))
            ->maxLength(65535)
            ->columnSpanFull()
            ->rows(5)
            ->nullable();
    }

    public static function getIsActive(): Toggle
    {
        return Toggle::make('is_active')
            ->label(__('resources/bank/strings.form.is_active'))
            ->default(true)
            ->inline(false)
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->helperText(__('resources/bank/strings.form.helper_is_active'));
    }
}
