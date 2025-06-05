<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use Filament\Forms\Components\TextInput;

trait Form
{
    public static function getType(): TextInput
    {
        return TextInput::make('type')
            ->label(__('resources/status/strings.form.type'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[\x{0600}-\x{06FF}\s_]+$/u')
            ->placeholder(__('resources/status/strings.form.validation_type'))
            ->validationMessages([
                'regex' => __('resources/status/strings.form.validation_type'),
            ])
            ->validationAttribute(__('resources/status/strings.form.type'));
    }


    public static function getEnglishType(): TextInput
    {
        return TextInput::make('english_type')
            ->label(__('resources/status/strings.form.english_type'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[A-Za-z\s]+$/')
            ->placeholder(__('resources/status/strings.form.validation_english_name'))
            ->validationMessages([
                'regex' => __('resources/status/strings.form.validation_english_name'),
            ])
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
