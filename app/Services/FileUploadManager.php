<?php

namespace App\Services;

use App\Models\Status;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Str;

class FileUploadManager
{
    public function processTemporaryFiles(Model $record, array $paths)
    {
        try {
            $status = SmartCacheManager::remember(
                'Status',
                ['type' => 'Attachment Status', 'name' => 'Uploaded'],
                1440,
                fn() => Status::findBy('Attachment Status', 'Uploaded')
            ) ?? throw new Exception("Default status 'Uploaded' not found.");

            $finalPaths = [];
            $tempDir = 'temp/';
            $attachmentsData = [];
            $now = now();
            $userId = auth()->id();

            foreach ($paths as $path) {
                if (str_starts_with($path, $tempDir)) {
                    [$newPath, $newName] = $this->makeNameAndPath($tempDir, $path, $record);
                    Storage::disk('public')->move($path, $newPath);
                    $finalPaths[] = $newPath;

                    $attachmentsData[] = [
                        'name' => $newName,
                        'path' => $newPath,
                        'type' => Str::limit(Storage::disk('public')->mimeType($newPath), 255, ''),
                        'status_id' => $status->id,
                        'user_id' => $userId,
                        'attachable_type' => $record->getMorphClass(),
                        'attachable_id' => $record->getKey(),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                } else {
                    $finalPaths[] = $path;
                }
            }

            if (!empty($attachmentsData)) {
                // We chunk insert to avoid parameter limit issues if there are many files,
                // but since these are uploads, it's rarely over limit. Still good practice.
                $record->attachments()->insert($attachmentsData);
            }

            $this->syncAttachments($record, $finalPaths);

            return $this;
        } catch (Exception $e) {
            Notification::make()
                ->title('⚠️')
                ->body(__('resources/general/strings.attachments.validation.processing_failed'))
                ->danger()
                ->persistent()
                ->send();

            throw $e;
        }
    }

    public function refreshComponent($record, $set)
    {
        $record->refresh();

        $set('attachments', $record->attachments->pluck('path')->toArray());

        return $this;
    }

    public function storeTemporary(UploadedFile $file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

        return $file->storeAs('temp', sprintf(
            '%s__%s-%s.%s',
            rawurlencode($original),
            now()->timestamp,
            uniqid(),
            $file->getClientOriginalExtension()
        ), 'public');
    }

    protected function makeNameAndPath(string $tempDir, mixed $path, Model $record): array
    {
        $base = pathinfo($path, PATHINFO_FILENAME);
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        $original = rawurldecode(Str::before($base, '__')) ?: 'file';
        $unique = Str::after($base, '__');
        $folder = 'attachments/' . Str::camel(class_basename($record));

        $newPath = "{$folder}/" . (Str::slug($original) ?: 'file') . "-{$unique}.{$ext}";

        return [$newPath, "{$original}.{$ext}"];
    }

    protected function slug(string $value): string
    {
        return preg_replace('/[^\p{Latin}\p{Arabic}\p{N}]+/u', '', mb_strtolower(trim($value)));
    }

    protected function syncAttachments(Model $record, array $paths): void
    {
        $attachments = $record->attachments();

        $stale = $attachments->pluck('path')->diff($paths);

        $stale->each(fn($path) => Storage::disk('public')->delete($path));
        $attachments->whereIn('path', $stale)->forceDelete();
    }
}
