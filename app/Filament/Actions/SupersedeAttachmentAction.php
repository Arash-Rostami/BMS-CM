<?php

namespace App\Filament\Actions;

use App\Models\Attachment;
use App\Models\Status;
use App\Services\SmartCacheManager;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class SupersedeAttachmentAction
{
    public static function make(): Action
    {
        return Action::make('supersedeAttachment')
            ->label(__('resources/general/strings.attachments.mark_superseded'))
            ->tooltip(__('resources/general/strings.attachments.mark_superseded'))
            ->icon('heroicon-o-archive-box-arrow-down')
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (Attachment $record): bool => $record->isUploaded())
            ->authorize(fn (Attachment $record): bool => filled($record->attachable_type)
                && auth()->user()?->can(Str::snake(class_basename($record->attachable_type)).'.edit'))
            ->action(function (Attachment $record): void {
                $status = SmartCacheManager::remember(
                    'Status',
                    ['type' => Attachment::TYPE_ATTACHMENT, 'name' => Attachment::STATUS_SUPERSEDED],
                    1440,
                    fn () => Status::findBy(Attachment::TYPE_ATTACHMENT, Attachment::STATUS_SUPERSEDED)
                );

                if ($status) {
                    $record->update(['status_id' => $status->id]);
                }

                Notification::make()
                    ->title(__('resources/general/strings.attachments.mark_superseded_success'))
                    ->success()
                    ->send();
            });
    }
}
