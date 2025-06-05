<?php

namespace App\Filament\Resources\Master\CategoryResource\Traits;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;

trait Form
{
    public static function getName(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/category/strings.form.name'))
            ->required()
            ->maxLength(255)
            ->maxLength(255)
            ->rule('regex:/^[\x{0600}-\x{06FF}\s]+$/u')
            ->unique(column: 'name', ignoreRecord: true)
            ->placeholder(__('resources/category/strings.form.validation_name'))
            ->validationMessages([
                'regex' => __('resources/category/strings.form.validation_name'),
                'unique' => __('resources/category/strings.form.validation_name_unique'),
            ])
            ->validationAttribute(__('resources/category/strings.form.name'));
    }

    public static function getEnglishName(): TextInput
    {
        return TextInput::make('english_name')
            ->label(__('resources/category/strings.form.english_name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[A-Za-z\s]+$/')
            ->unique(column: 'english_name', ignoreRecord: true)
            ->placeholder(__('resources/category/strings.form.validation_english_name'))
            ->validationMessages([
                'regex' => __('resources/category/strings.form.validation_english_name'),
                'unique' => __('resources/category/strings.form.validation_english_name_unique'),
            ])
            ->validationAttribute(__('resources/category/strings.form.english_name'));
    }

    public static function getDescription(): Textarea
    {
        return Textarea::make('description')
            ->label(__('resources/category/strings.form.description'))
            ->maxLength(65535)
            ->nullable();
    }

    public static function getLevel(): TextInput
    {
        return TextInput::make('level')
            ->label(__('resources/category/strings.form.level'))
            ->default(0)
            ->numeric()
            ->tooltip(__('resources/category/strings.form.level_helper'))
            ->helperText(__('resources/category/strings.form.level_helper'))
            ->rule('integer')
            ->placeholder(__('resources/category/strings.form.validation_level'))
            ->validationMessages([
                'integer' => __('resources/category/strings.form.validation_level'),
            ])
            ->required();
    }

    public static function getParentCategory(): Select
    {
        return Select::make('parent_id')
            ->label(__('resources/category/strings.form.parent'))
            ->relationship('parent', 'name')
            ->searchable()
            ->preload()
            ->nullable();
    }

    public static function getActive(): Toggle
    {
        return Toggle::make('active')
            ->label(__('resources/category/strings.form.active'))
            ->extraAttributes(['style' => 'display:flex; justify-content:center!important; align-items:center;'])
            ->onColor('success')
            ->onIcon('heroicon-m-bolt')
            ->inline()
            ->default(true);
    }
}
