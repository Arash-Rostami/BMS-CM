<?php

namespace App\Filament\Actions;

use App\Services\StatusWorkflow;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class ResubmitAction
{
    public static function make(string $typeConstant): Action
    {
        return Action::make('resubmit')
            ->label(__('resources/general/strings.status_workflow.resubmit'))
            ->icon('heroicon-o-arrow-path')
            ->color('info')
            ->requiresConfirmation()
            ->authorize(fn (?Model $record): bool => $record
                && auth()->id() === $record->user_id
                && in_array($record->status?->english_name, ['Conditional', 'Declined'], true))
            ->action(function (Model $record) use ($typeConstant): void {
                $target = StatusWorkflow::initialFor($typeConstant);

                if (! $target) {
                    return;
                }

                $record->update(['status_id' => $target->id]);

                Notification::make()
                    ->title(__('resources/general/strings.status_workflow.resubmit_success'))
                    ->success()
                    ->send();
            });
    }
}
