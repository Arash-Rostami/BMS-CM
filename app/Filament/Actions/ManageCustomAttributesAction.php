<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;

class ManageCustomAttributesAction
{
    public static function make(): Action
    {
        return Action::make('manageCustomAttributes')
            ->label(__('actions/manageCustomAttributes.label'))
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->modalHeading(__('actions/manageCustomAttributes.modal_heading'))
            ->modalSubmitActionLabel(__('actions/manageCustomAttributes.save'))
            ->form([
                KeyValue::make('attributes')
                    ->label(false)
                    ->keyLabel(__('actions/manageCustomAttributes.key_label'))
                    ->valueLabel(__('actions/manageCustomAttributes.value_label'))
                    ->addActionLabel(__('actions/manageCustomAttributes.add_row'))
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
