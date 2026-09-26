<?php

namespace App\Filament\Actions;

use App\Models\Attachment;
use App\Models\Status;
use App\Services\SmartCacheManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class RevertAttachmentAction
{
    public static function make(): Action
    {
        return Action::make('revertAttachment')
            ->label(__('resources/general/strings.attachments.revert'))
            ->tooltip(__('resources/general/strings.attachments.revert'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Attachment $record): bool => $record->isArchived())
            ->authorize(fn (Attachment $record): bool => filled($record->attachable_type)
                && auth()->user()?->can(Str::snake(class_basename($record->attachable_type)).'.edit'))
            ->action(function (Attachment $record): void {
                $status = SmartCacheManager::remember(
                    'Status',
                    ['type' => Attachment::TYPE_ATTACHMENT, 'name' => Attachment::STATUS_UPLOADED],
                    1440,
                    fn () => Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_UPLOADED)
                );

                if ($status) {
                    $record->update(['status_id' => $status->id]);
                }

                Notification::make()
                    ->title(__('resources/general/strings.attachments.revert_success'))
                    ->success()
                    ->send();
            });
    }
}
