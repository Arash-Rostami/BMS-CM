<?php

namespace App\Rules;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Validation\ValidationRule;
use League\Flysystem\UnableToRetrieveMetadata;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ValidAttachment implements ValidationRule
{
    public const MAX_SIZE_KB = 2500;

    public const ALLOWED_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml',
        'application/pdf',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel.sheet.macroEnabled.12',
        'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof TemporaryUploadedFile) return;

        try {
            $filename = $value->getClientOriginalName();

            // Filename must use Latin/Arabic chars, numbers, common punctuation only
            if (!preg_match('/^[\p{Latin}\p{Arabic}\p{N}\s.\-_()\[\],&]{1,185}$/u', $filename)) {
                $this->sendWarningNotification(__('resources/general/strings.attachments.validation.invalid_filename_chars_hint'));
                $fail(__('resources/general/strings.attachments.validation.invalid_filename_chars'));
                return;
            }

            // File must still exist (not expired)
            if (!$value->exists()) {
                $this->sendWarningNotification(__('resources/general/strings.attachments.validation.file_not_available_hint'));
                $fail(__('resources/general/strings.attachments.validation.file_not_available'));
                return;
            }

            // Enforcing size limit
            $size = $value->getSize();
            if ($size > self::MAX_SIZE_KB * 1024) {
                $this->sendWarningNotification(__('resources/general/strings.attachments.validation.attachments_size'));
                $fail(__('resources/general/strings.attachments.validation.attachments_size'));
                return;
            }

            // Enforcing type whitelist
            $mimeType = $value->getMimeType();
            if (!in_array($mimeType, self::ALLOWED_TYPES, true)) {
                $this->sendWarningNotification(__('resources/general/strings.attachments.validation.attachments_type'));
                $fail(__('resources/general/strings.attachments.validation.attachments_type'));
                return;
            }

        } catch (UnableToRetrieveMetadata $e) {
            $this->sendWarningNotification(__('resources/general/strings.attachments.validation.metadata_unreadable_hint'));
            $fail(__('resources/general/strings.attachments.validation.metadata_unreadable'));
        }
    }

    public function sendWarningNotification(string $body): void
    {
        Notification::make()
            ->title('⚠️')
            ->body($body)
            ->warning()
            ->persistent()
            ->send();
    }
}
