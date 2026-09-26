<?php

namespace App\Filament\Actions;

use App\Services\StatusWorkflow;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class ReturnForRevisionAction
{
    public static function make(string $typeConstant): Action
    {
        return Action::make('returnForRevision')
            ->label(__('resources/general/strings.status_workflow.return_for_revision'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('warning')
            ->authorize(fn (?Model $record): bool => filled($record?->status?->approval_permission)
                && auth()->user()?->can($record->status->approval_permission))
            ->schema([
                Textarea::make('reason')
                    ->label(__('resources/general/strings.status_workflow.return_for_revision_reason_label'))
                    ->required()
                    ->rows(3),
            ])
            ->action(function (Model $record, array $data) use ($typeConstant): void {
                $target = StatusWorkflow::initialFor($typeConstant);

                if (! $target) {
                    return;
                }

                $record::withStatusHistoryReason($data['reason']);
                $record->update(['status_id' => $target->id]);

                Notification::make()
                    ->title(__('resources/general/strings.status_workflow.return_for_revision_success'))
                    ->success()
                    ->send();
            });
    }
}
