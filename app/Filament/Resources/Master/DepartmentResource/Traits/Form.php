<?php

namespace App\Filament\Resources\Master\DepartmentResource\Traits;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

trait Form
{
    public static function getCode(): TextInput
    {
        return TextInput::make('code')
            ->label(__('resources/department/strings.form.code'))
            ->required()
            ->maxLength(255)
            ->unique(column: 'code', ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())
            ->placeholder(__('resources/department/strings.form.validation_code'))
            ->validationMessages([
                'required' => __('resources/department/strings.form.validation_code_required'),
                'unique' => __('resources/department/strings.form.validation_code_unique'),
                'max' => __('resources/department/strings.form.validation_code_max'),
            ])
            ->validationAttribute(__('resources/department/strings.form.code'))
            ->helperText(__('resources/department/strings.form.helper_code'));
    }

    public static function getName(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/department/strings.form.name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[\x{0600}-\x{06FF}\s\p{P}\d\*]+$/u')
            ->placeholder(__('resources/department/strings.form.validation_name'))
            ->validationMessages([
                'required' => __('resources/department/strings.form.validation_name_required'),
                'regex' => __('resources/department/strings.form.validation_name'),
                'max' => __('resources/department/strings.form.validation_name_max'),
            ])
            ->validationAttribute(__('resources/department/strings.form.name'))
            ->helperText(__('resources/department/strings.form.helper_name'));
    }

    public static function getEnglishName(): TextInput
    {
        return TextInput::make('english_name')
            ->label(__('resources/department/strings.form.english_name'))
            ->maxLength(255)
            ->rule('regex:/^[A-Za-z\s\p{P}\d\*]+$/u')
            ->nullable()
            ->placeholder(__('resources/department/strings.form.validation_english_name'))
            ->validationMessages([
                'regex' => __('resources/department/strings.form.validation_english_name'),
                'max' => __('resources/department/strings.form.validation_english_name_max'),
            ])
            ->validationAttribute(__('resources/department/strings.form.english_name'))
            ->helperText(__('resources/department/strings.form.helper_english_name'));
    }

    public static function getDescription(): Textarea
    {
        return Textarea::make('description')
            ->label(__('resources/department/strings.form.description'))
            ->maxLength(65535)
            ->columnSpanFull()
            ->rows(5)
            ->nullable()
            ->validationMessages([
                'max' => __('resources/department/strings.form.validation_description_max'),
            ]);
    }

    public static function getIsActive(): Toggle
    {
        return Toggle::make('is_active')
            ->label(__('resources/department/strings.form.is_active'))
            ->default(true)
            ->inline(false)
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->onColor('success')
            ->offColor('danger')
            ->helperText(__('resources/department/strings.form.helper_is_active'));
    }
}
