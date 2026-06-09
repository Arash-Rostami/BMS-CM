<?php

namespace App\Filament\Traits;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

trait HasExtraAttributesManagement
{
    public static function getExtraAttributesFormTab(): Tab
    {
        return Tab::make(__('resources/entityAttribute/strings.general.plural_model_label'))
            ->icon('heroicon-o-puzzle-piece')
            ->schema([
                static::buildExtraAttributesRepeater(),
            ]);
    }

    public static function getExtraAttributesFormSection(): Section
    {
        return Section::make(__('resources/entityAttribute/strings.general.plural_model_label'))
            ->icon('heroicon-o-puzzle-piece')
            ->schema([
                static::buildExtraAttributesRepeater()->columnSpanFull(),
            ])
            ->columnSpanFull()
            ->collapsible()
            ->collapsed();
    }

    public static function getExtraAttributesInfolistTab(): Tab
    {
        return Tab::make(__('resources/entityAttribute/strings.general.plural_model_label'))
            ->icon('heroicon-o-puzzle-piece')
            ->badge(fn($record) => $record?->extraAttributes->count() ?: null)
            ->badgeColor('primary')
            ->schema([
                Section::make()->schema([
                    RepeatableEntry::make('extraAttributes')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('key')
                                ->label(__('resources/entityAttribute/strings.form.key'))
                                ->badge()
                                ->color('gray')
                                ->icon('heroicon-m-key'),
                            TextEntry::make('value')
                                ->label(__('resources/entityAttribute/strings.form.value'))
                                ->formatStateUsing(fn($state): string => is_string($state) ? $state : json_encode($state, JSON_UNESCAPED_UNICODE))
                                ->icon('heroicon-m-document-text')
                                ->placeholder('-'),
                        ])
                        ->columns(2),
                ]),
            ]);
    }

    protected static function buildExtraAttributesRepeater(): Repeater
    {
        return Repeater::make('extraAttributes')
            ->hiddenLabel()
            ->relationship()
            ->schema([
                TextInput::make('key')
                    ->label(__('resources/entityAttribute/strings.form.key'))
                    ->required()
                    ->maxLength(255),
                Textarea::make('value')
                    ->label(__('resources/entityAttribute/strings.form.value'))
                    ->required()
                    ->rows(2)
                    ->formatStateUsing(fn($state): string => is_string($state) ? $state : json_encode($state, JSON_UNESCAPED_UNICODE)),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->addActionLabel(__('resources/entityAttribute/strings.form.add_attribute_action'))
            ->reorderableWithButtons();
    }
}
