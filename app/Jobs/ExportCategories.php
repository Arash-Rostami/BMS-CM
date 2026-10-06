<?php

namespace App\Jobs;

use App\Filament\Resources\Master\CategoryResource\Exports\CategoryExporter;
use App\Models\Category;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

class ExportCategories implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $ids,
        public int $userId,
        public string $locale,
    ) {}

    public function handle(): void
    {
        app()->setLocale($this->locale);

        $file = config('app.name').'-'.strtoupper(class_basename(Category::class)).'-'.now()->format('His').'.csv';
        $directory = "exports/{$this->userId}";

        $disk = Storage::disk('local');
        $disk->makeDirectory($directory);

        try {
            $rows = CategoryExporter::write(
                Category::whereIn('id', $this->ids),
                $disk->path("{$directory}/{$file}")
            );

            Notification::make()
                ->title(__('filament-actions::export.notifications.completed.title'))
                ->body(__('resources/general/strings.export.completed', ['successful' => $rows]))
                ->success()
                ->actions([
                    Action::make('download')
                        ->label(__('resources/general/strings.export.download'))
                        ->url(URL::signedRoute('exports.download', ['user' => $this->userId, 'file' => $file]), shouldOpenInNewTab: true)
                        ->markAsRead(),
                ])
                ->sendToDatabase(User::find($this->userId), isEventDispatched: true);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('resources/general/strings.export.error'))
                ->danger()
                ->sendToDatabase(User::find($this->userId), isEventDispatched: true);
        }
    }
}
