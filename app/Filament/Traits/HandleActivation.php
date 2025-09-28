<?php

namespace App\Filament\Traits;

use Filament\Notifications\Notification;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

trait HandleActivation
{
    protected static function getActivateBulkAction(): BulkAction
    {
        return BulkAction::make('activate')
            ->label(__('resources/general/strings.bulk.activate.label'))
            ->action(function (Collection $records) {
                static::getModel()::whereIn('id', $records->pluck('id'))->update(['is_active' => 1]);
                Notification::make()
                    ->title(__('resources/general/strings.bulk.activate.notification'))
                    ->success()
                    ->send();
            })
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->deselectRecordsAfterCompletion();
    }

    protected static function getDeactivateBulkAction(): BulkAction
    {
        return BulkAction::make('deactivate')
            ->label(__('resources/general/strings.bulk.deactivate.label'))
            ->action(function (Collection $records) {
                static::getModel()::whereIn('id', $records->pluck('id'))->update(['is_active' => 0]);
                Notification::make()
                    ->title(__('resources/general/strings.bulk.deactivate.notification'))
                    ->success()
                    ->send();
            })
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->deselectRecordsAfterCompletion();
    }
}
