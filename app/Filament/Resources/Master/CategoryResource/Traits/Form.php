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
            ->rule(['string', 'max:255'])
            ->unique(column: 'name', ignoreRecord: true)
            ->placeholder(__('resources/category/strings.form.validation_name'))
            ->validationMessages([
                'required' => __('resources/category/strings.form.validation_name_required'),
                'max' => __('resources/category/strings.form.validation_name_max'),
                'regex' => __('resources/category/strings.form.validation_name'),
                'unique' => __('resources/category/strings.form.validation_name_unique'),
            ])
            ->validationAttribute(__('resources/category/strings.form.name'))
            ->helperText(__('resources/category/strings.form.helper_name'));
    }

    public static function getEnglishName(): TextInput
    {
        return TextInput::make('english_name')
            ->label(__('resources/category/strings.form.english_name'))
            ->required()
            ->maxLength(255)
            ->rule(['string', 'max:255'])
            ->unique(column: 'english_name', ignoreRecord: true)
            ->placeholder(__('resources/category/strings.form.validation_english_name'))
            ->validationMessages([
                'required' => __('resources/category/strings.form.validation_english_name_required'),
                'max' => __('resources/category/strings.form.validation_english_name_max'),
                'regex' => __('resources/category/strings.form.validation_english_name'),
                'unique' => __('resources/category/strings.form.validation_english_name_unique'),
            ])
            ->validationAttribute(__('resources/category/strings.form.english_name'))
            ->helperText(__('resources/category/strings.form.helper_english_name'));
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
                'required' => __('resources/category/strings.form.validation_level_required'),
                'numeric' => __('resources/category/strings.form.validation_level_numeric'),
                'integer' => __('resources/category/strings.form.validation_level'),
            ])
            ->validationAttribute(__('resources/category/strings.form.level'))
            ->required();
    }

    public static function getParentCategory(): Select
    {
        return Select::make('parent_id')
            ->label(__('resources/category/strings.form.parent'))
            ->relationship('parent', 'name')
            ->searchable()
            ->preload()
            ->helperText(__('resources/category/strings.form.helper_parent'))
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
