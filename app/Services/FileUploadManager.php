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

            foreach ($paths as $path) {
                if (str_starts_with($path, $tempDir)) {
                    list($newPath, $newName) = $this->makeNameAndPath($tempDir, $path, $record);
                    Storage::disk('public')->move($path, $newPath);
                    $finalPaths[] = $newPath;

                    $record->attachments()->create([
                        'name' => $newName,
                        'path' => $newPath,
                        'type' => Str::limit(Storage::disk('public')->mimeType($newPath), 255, ''),
                        'status_id' => $status->id,
                        'user_id' => auth()->id(),
                    ]);
                } else {
                    $finalPaths[] = $path;
                }
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

        $newPaths = $record->attachments->pluck('path')->toArray();
        $set('attachments', $newPaths);

        return $this;
    }

    public function storeTemporary(UploadedFile $file): string
    {
        return $file->storeAs(
            'temp',
            sprintf(
                '%s-%s-%s.%s',
                Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)),
                now()->timestamp,
                uniqid(),
                $file->getClientOriginalExtension()
            ),
            'public'
        );
    }

    protected function makeNameAndPath(string $tempDir, mixed $path, Model $record): array
    {
        $folder = 'attachments/' . Str::camel(class_basename($record));
        $newPath = Str::replaceFirst($tempDir, $folder . '/', $path);

        $name = Str::of(class_basename($record))
            ->kebab()
            ->append('-', $record->getKey())
            ->append('-', Str::kebab(strtolower(auth()->user()->name) ?? 'unknown'))
            ->append('-', now()->format('Y-m-d-H:i'))
            ->toString();

        return [$newPath, $name];
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
