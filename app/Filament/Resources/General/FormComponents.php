<?php

namespace App\Filament\Resources\General;

use App\Models\Attachment;
use App\Models\Status;
use App\Rules\ValidAttachment;
use App\Services\FileUploadManager;
use App\Services\SmartCacheManager;
use App\Services\StatusWorkflow;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

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
            ->deletable(fn (?Model $record): bool => ! ($record?->status && StatusWorkflow::isTerminal($record->status)))
            ->hintIconTooltip(fn ($record) => $record?->attachments()->latest('id')->implode('name', "\n") ?? '')
            ->rules([new ValidAttachment])
            ->preventFilePathTampering(true, fn (string $file, ?Model $record): bool => (bool) preg_match('#^temp/[^/]+$#', $file) || ($record?->attachments?->contains('path', $file) ?? false))
            ->acceptedFileTypes(ValidAttachment::ALLOWED_TYPES)
            ->maxSize(ValidAttachment::MAX_SIZE_KB)
            ->validationMessages([
                'max' => __('resources/general/strings.attachments.validation.attachments_size'),
                'mimetypes' => __('resources/general/strings.attachments.validation.attachments_type'),
            ])
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

    public static function getAttachmentStatusManager(): Repeater
    {
        return Repeater::make('attachmentStatuses')
            ->hiddenLabel()
            ->dehydrated(false)
            ->addable(false)
            ->deletable(false)
            ->reorderable(false)
            ->columnSpanFull()
            ->schema([
                Select::make('status_id')
                    ->label(__('resources/general/strings.attachments.status_label'))
                    ->options(fn (Get $get): array => static::attachmentStatusSelectOptions($get))
                    ->selectablePlaceholder(false)
                    ->disabled(fn (Get $get): bool => (bool) $get('locked'))
                    ->helperText(fn (Get $get): string => match (true) {
                        (bool) $get('locked') => __('resources/general/strings.attachments.superseded_hint'),
                        (bool) $get('archived') => __('resources/general/strings.attachments.revert_hint'),
                        default => __('resources/general/strings.attachments.status_hint'),
                    })
                    ->live()
                    ->afterStateUpdated(function ($state, Get $get): void {
                        if ($state && ($attachment = Attachment::find($get('id')))) {
                            $attachment->update(['status_id' => $state]);
                        }
                    })
                    ->columnSpanFull(),
                Placeholder::make('detail')
                    ->hiddenLabel()
                    ->content(fn (Get $get) => new HtmlString(static::attachmentStatusDetailHtml($get)))
                    ->columnSpanFull(),
            ])
            ->columns(1)
            ->visible(fn (?Model $record): bool => (bool) $record?->attachments?->isNotEmpty())
            ->afterStateHydrated(function (Repeater $component, ?Model $record): void {
                $component->state(
                    $record?->attachments->map(fn (Attachment $attachment): array => [
                        'id' => $attachment->id,
                        'status_id' => $attachment->status_id,
                        'status_name' => $attachment->status?->getLocalizedNameAttribute(),
                        'uploaded' => $attachment->isUploaded(),
                        'archived' => $attachment->isArchived(),
                        'locked' => $attachment->isSuperseded(),
                        'changed' => ! $attachment->isUploaded(),
                        'name' => $attachment->name ?: basename($attachment->path),
                        'path' => $attachment->path,
                    ])->values()->toArray() ?? []
                );
            });
    }

    protected static function attachmentStatusSelectOptions(Get $get): array
    {
        $options = $get('status_id')
            ? [$get('status_id') => $get('status_name')]
            : [];

        $nextName = match (true) {
            (bool) $get('uploaded') => Attachment::STATUS_SUPERSEDED,
            (bool) $get('archived') => Attachment::STATUS_UPLOADED,
            default => null,
        };

        if ($nextName && ($next = static::cachedAttachmentStatus($nextName))) {
            $options[$next->id] = $next->getLocalizedNameAttribute();
        }

        return $options;
    }

    protected static function cachedAttachmentStatus(string $name): ?Status
    {
        return SmartCacheManager::remember(
            'Status',
            ['type' => Attachment::TYPE_ATTACHMENT, 'name' => $name],
            1440,
            fn () => Status::findBy(Attachment::TYPE_ATTACHMENT, $name)
        );
    }

    protected static function attachmentStatusDetailHtml(Get $get): string
    {
        $name = e((string) $get('name'));

        if (! $get('changed')) {
            return '<span dir="ltr" class="text-sm text-gray-500 dark:text-gray-400">'.$name.'</span>';
        }

        $url = e(Storage::disk('public')->url((string) $get('path')));
        $icon = $get('archived') ? '🔒' : '📦';
        $badgeClass = $get('archived') ? 'tb-badge tb-info' : 'tb-badge tb-warning';
        $badgeLabel = e((string) $get('status_name'));

        return '<a href="'.$url.'" target="_blank" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-white/10 px-3 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-white/5">'
            .'<span>'.$icon.'</span>'
            .'<span dir="ltr" class="text-primary-600 dark:text-primary-400 underline">'.$name.'</span>'
            .'<span class="'.$badgeClass.'">'.$badgeLabel.'</span>'
            .'</a>';
    }
}
