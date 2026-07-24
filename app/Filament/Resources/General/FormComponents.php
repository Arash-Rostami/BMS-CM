<?php

namespace App\Filament\Resources\General;

use App\Rules\ValidAttachment;
use App\Services\FileUploadManager;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Http\UploadedFile;

class FormComponents
{
    public static function getAttachmentsField(): FileUpload
    {
        return FileUpload::make('attachments')
            ->label(__('resources/general/strings.attachments.attachments'))
            ->multiple()
            ->disk('public')
            ->visibility('public')
            ->previewable()
            ->openable()
            ->live()
            ->columnSpanFull()
            ->downloadable()
            ->hintIconTooltip(fn ($record) => $record?->attachments()->latest('id')->implode('name', "\n") ?? '')
            ->rules([new ValidAttachment])
            ->acceptedFileTypes(ValidAttachment::ALLOWED_TYPES)
            ->maxSize(ValidAttachment::MAX_SIZE_KB)
            ->validationAttribute(__('resources/general/strings.attachments.attachments'))
            ->saveUploadedFileUsing(static function (UploadedFile $file, $state) {
                return app(FileUploadManager::class)->storeTemporary($file);
            })
            ->saveRelationshipsUsing(static function ($record, array $state, Set $set) {
                if ($record) {
                    try {
                        app(FileUploadManager::class)
                            ->processTemporaryFiles($record, $state)
                            ->refreshComponent($record, $set);
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title(__('resources/general/strings.attachments.error_title'))
                            ->body(__('resources/general/strings.attachments.error_body'))
                            ->danger()
                            ->send();

                        throw $e;
                    }
                }
            })
            ->afterStateHydrated(static function (FileUpload $component, $state, $record) {
                $component->state($record?->attachments?->pluck('path')->toArray() ?? []);
            });
    }
}
