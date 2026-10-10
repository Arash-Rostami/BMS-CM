<?php

namespace App\Jobs;

use App\Filament\Resources\Master\CalendarRuleResource\Exports\CalendarRuleExporter;
use App\Models\CalendarRule;
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

class ExportCalendarRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $ids,
        public int $userId,
        public string $locale,
    ) {
        $this->onQueue(config('calendar.queue'));
    }

    public function handle(): void
    {
        app()->setLocale($this->locale);

        $user = User::find($this->userId);

        if ($user === null) {
            return;
        }

        $file = config('app.name').'-'.strtoupper(class_basename(CalendarRule::class)).'-'.now()->format('His').'.csv';
        $directory = "exports/{$this->userId}";

        $disk = Storage::disk('local');
        $disk->makeDirectory($directory);

        try {
            $rows = CalendarRuleExporter::write(
                CalendarRule::query()->visibleTo($user)->whereIn('id', $this->ids),
                $disk->path("{$directory}/{$file}"),
                $user
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
                ->sendToDatabase($user, isEventDispatched: true);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->title(__('resources/general/strings.export.error'))
                ->danger()
                ->sendToDatabase($user, isEventDispatched: true);
        }
    }
}
