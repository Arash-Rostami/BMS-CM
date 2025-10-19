<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Utilities\Get;
use App\Models\Status;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

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
            ->options(fn() => Status::query()
                    ->distinct()
                    ->pluck('type', 'type')
                    ->toArray() + ['write' => __('resources/status/strings.form.custom')]
            )
            ->reactive()
            ->afterStateUpdated(function (Set $set, $state) {
                if ($state === 'write') {
                    $set('custom_type', true);
                    $set('custom_english_type', true);
                } else {
                    $set('custom_type', false);
                    $english = Status::where('type', $state)->value('english_type');
                    $set('english_type', $english);
                    $set('custom_english_type', false);
                }
            })
            ->required(fn(Get $get) => !$get('custom_type'))
            ->visible(fn(Get $get) => !$get('custom_type'));
    }

    public static function getTypeCustomField(): TextInput
    {
        return TextInput::make('type_custom')
            ->label(__('resources/status/strings.form.type') . ' (' . __('resources/status/strings.form.custom') . ')')
            ->required(fn(Get $get) => $get('custom_type'))
            ->visible(fn(Get $get) => $get('custom_type'));
    }

    public static function getEnglishType(): Select
    {
        return Select::make('english_type')
            ->label(__('resources/status/strings.form.english_type'))
            ->options(fn() => Status::query()
                    ->distinct()
                    ->pluck('english_type', 'english_type')
                    ->toArray() + ['write' => __('resources/status/strings.form.english_custom')]
            )
            ->reactive()
            ->afterStateUpdated(function (Set $set, $state) {
                if ($state === 'write') {
                    $set('custom_type', true);
                    $set('custom_english_type', true);
                } else {
                    $set('custom_english_type', false);
                    $farsi = Status::where('english_type', $state)->value('type');
                    $set('type', $farsi);
                    $set('custom_type', false);
                }
            })
            ->required(fn(Get $get) => !$get('custom_english_type'))
            ->visible(fn(Get $get) => !$get('custom_english_type'));
    }

    public static function getEnglishTypeCustomField(): TextInput
    {
        return TextInput::make('english_type_custom')
            ->label(__('resources/status/strings.form.english_type') . ' (' . __('resources/status/strings.form.custom') . ')')
            ->required(fn(Get $get) => $get('custom_english_type'))
            ->visible(fn(Get $get) => $get('custom_english_type'));
    }

    public static function getName(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/status/strings.form.name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[\x{0600}-\x{06FF}\s\p{P}\d\*]+$/u')
            ->unique(
                column: 'name',
                ignoreRecord: true,
                modifyRuleUsing: fn ($rule, $get) => $rule->where('type', $get('type'))
            )
            ->placeholder(__('resources/status/strings.form.validation_name'))
            ->validationMessages([
                'regex' => __('resources/status/strings.form.validation_name'),
                'unique' => __('resources/status/strings.form.validation_name_unique'),
            ])
            ->validationAttribute(__('resources/status/strings.form.name'));
    }

    public static function getEnglishName(): TextInput
    {
        return TextInput::make('english_name')
            ->label(__('resources/status/strings.form.english_name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[A-Za-z\s\p{P}\d\*]+$/')
            ->unique(
                column: 'english_name',
                ignoreRecord: true,
                modifyRuleUsing: fn($rule, $get) => $rule->where('english_type', $get('english_type'))
            )
            ->placeholder(__('resources/status/strings.form.validation_english_name'))
            ->validationMessages([
                'regex' => __('resources/status/strings.form.validation_english_name'),
                'unique' => __('resources/status/strings.form.validation_english_name_unique'),
            ])
            ->validationAttribute(__('resources/status/strings.form.english_name'));
    }
}
