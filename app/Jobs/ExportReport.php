<?php

namespace App\Jobs;

use App\Filament\Pages\Reports\ReportPage;
use App\Models\User;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportCsvWriter;
use App\Services\Reports\Support\ReportFilters;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

class ExportReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $report,
        public array $filters,
        public int $userId,
        public string $locale,
        public ?string $calendar = null,
    ) {}

    public function handle(): void
    {
        app()->setLocale($this->locale);
        ReportStages::flush();
        $previousCalendar = session('calendar_type');
        $previousUser = auth()->user();

        try {
            $this->run();
        } finally {
            $previousUser ? auth()->setUser($previousUser) : auth()->forgetGuards();
            $previousCalendar === null ? session()->forget('calendar_type') : session(['calendar_type' => $previousCalendar]);
        }
    }

    protected function run(): void
    {
        if ($this->calendar) {
            session(['calendar_type' => $this->calendar]);
        }

        $user = User::find($this->userId);
        $page = ReportPage::registry()[$this->report] ?? null;

        if (! $user || ! $page || ! $user->can('report.'.$page::permission())) {
            Log::warning('Report export refused.', ['report' => $this->report, 'user' => $this->userId]);

            return;
        }

        auth()->setUser($user);

        $this->export($page, $user);
    }

    /**
     * @param  class-string<ReportPage>  $page
     */
    protected function export(string $page, User $user): void
    {
        $file = config('app.name').'-REPORT-'.strtoupper($this->report).'-'.now()->format('His').'.csv';
        $directory = "exports/{$this->userId}";

        $disk = Storage::disk('local');
        $disk->makeDirectory($directory);

        try {
            $service = app($page::service());
            $filters = ReportFilters::fromArray($this->filters);
            $rows = ReportCsvWriter::write(
                $service->rows($filters),
                method_exists($service, 'exportLabelsFor') ? $service->exportLabelsFor($filters) : $service->exportLabels(),
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
