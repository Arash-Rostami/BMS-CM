<?php

namespace App\Filament\Resources\Master\CompanyResource\Traits;


use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;

trait Form
{
    public static function getName(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/company/strings.form.name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[\x{0600}-\x{06FF}\s]+$/u')
            ->unique(column: 'name', ignoreRecord: true)
            ->placeholder(__('resources/company/strings.form.validation_name'))
            ->validationMessages([
                'regex' => __('resources/company/strings.form.validation_name'),
                'unique' => __('resources/company/strings.form.validation_name_unique')
            ])
            ->validationAttribute(__('resources/company/strings.form.name'));
    }

    public static function getEnglishName(): TextInput
    {
        return TextInput::make('english_name')
            ->label(__('resources/company/strings.form.english_name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[A-Za-z\s]+$/')
            ->unique(column: 'english_name', ignoreRecord: true)
            ->placeholder(__('resources/company/strings.form.validation_english_name'))
            ->validationMessages([
                'regex' => __('resources/company/strings.form.validation_english_name'),
                'unique' => __('resources/company/strings.form.validation_english_name_unique')
            ])
            ->validationAttribute(__('resources/company/strings.form.english_name'));
    }

    public static function getDescription(): Textarea
    {
        return Textarea::make('description')
            ->label(__('resources/company/strings.form.description'))
            ->maxLength(65535)
            ->nullable();
    }
}
