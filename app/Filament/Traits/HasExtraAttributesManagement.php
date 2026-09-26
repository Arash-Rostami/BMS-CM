<?php

namespace App\Filament\Traits;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;

trait HasExtraAttributesManagement
{
    public static function withExtraAttributesSearch(array $ownAttributes): array
    {
        return [...$ownAttributes, 'extraAttributes.key', 'extraAttributes.value'];
    }

    public static function orWhereExtraAttributesMatch(\Illuminate\Database\Eloquent\Builder $query, string $search): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orWhereHas('extraAttributes', fn ($q) => $q
            ->where('key', 'like', "%{$search}%")
            ->orWhere('value', 'like', "%{$search}%"));
    }

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
            ->badge(fn ($record) => $record?->extraAttributes->count() ?: null)
            ->badgeColor('primary')
            ->schema([
                Section::make()->schema([
                    RepeatableEntry::make('extraAttributes')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('key')
                                ->label(__('resources/general/strings.extra_attributes.key'))
                                ->badge()
                                ->color('gray')
                                ->icon('heroicon-m-key'),
                            TextEntry::make('value')
                                ->label(__('resources/general/strings.extra_attributes.value'))
                                ->formatStateUsing(fn ($state): string => match (true) {
                                    is_string($state) => $state,
                                    is_null($state) => '',
                                    default => json_encode($state, JSON_UNESCAPED_UNICODE),
                                })
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
                    ->label(__('resources/general/strings.extra_attributes.key'))
                    ->required()
                    ->maxLength(255)
                    ->validationMessages([
                        'required' => __('resources/general/strings.extra_attributes.validation_key_required'),
                        'max' => __('resources/general/strings.extra_attributes.validation_key_max'),
                    ]),
                Textarea::make('value')
                    ->label(__('resources/general/strings.extra_attributes.value'))
                    ->required()
                    ->validationMessages([
                        'required' => __('resources/general/strings.extra_attributes.validation_value_required'),
                    ])
                    ->rows(2)
                    ->formatStateUsing(fn ($state): string => match (true) {
                        is_string($state) => $state,
                        is_null($state) => '',
                        default => json_encode($state, JSON_UNESCAPED_UNICODE),
                    }),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->addActionLabel(__('resources/general/strings.extra_attributes.add_action'))
            ->reorderableWithButtons()
            ->saveRelationshipsUsing(function (Repeater $component): void {
                $record = $component->getRecord();

                $keyValueMap = [];

                foreach ($component->getItems() as $item) {
                    $itemData = $item->getState(shouldCallHooksBefore: false);

                    if (filled($itemData['key'] ?? null)) {
                        $keyValueMap[$itemData['key']] = $itemData['value'] ?? null;
                    }
                }

                $record->syncCustomAttributes($keyValueMap);
            });
    }
}
