<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;

class ManageCustomAttributesAction
{
    public static function make(): Action
    {
        return Action::make('manageCustomAttributes')
            ->label(__('resources/general/strings.manage_custom_attributes.label'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->modalHeading(__('resources/general/strings.manage_custom_attributes.modal_heading'))
            ->modalSubmitActionLabel(__('resources/general/strings.manage_custom_attributes.save'))
            ->schema([
                KeyValue::make('attributes')
                    ->label(false)
                    ->keyLabel(__('resources/general/strings.manage_custom_attributes.key_label'))
                    ->valueLabel(__('resources/general/strings.manage_custom_attributes.value_label'))
                    ->addActionLabel(__('resources/general/strings.manage_custom_attributes.add_row'))
                    ->columnSpanFull(),
            ])
            ->fillForm(fn ($record): array => [
                'attributes' => $record->getCustomAttributesMap(),
            ])
            ->action(function ($record, array $data): void {
                $attributes = $data['attributes'] ?? [];
                $userId = auth()->id();

                $record->customAttributes()
                    ->whereNotIn('key', array_keys($attributes))
                    ->delete();

                foreach ($attributes as $key => $value) {
                    $record->customAttributes()->updateOrCreate(
                        ['key' => $key],
                        ['value' => $value, 'created_by' => $userId],
                    );
                }
            });
    }
}
