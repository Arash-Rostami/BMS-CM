<?php

namespace App\Filament\Actions;

use App\Jobs\ImportGroupedCsv;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Imports\Events\ImportCompleted;
use Filament\Actions\Imports\Events\ImportStarted;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Bus\PendingBatch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Number;
use League\Csv\Reader as CsvReader;
use League\Csv\Statement;

class GroupedImportAction extends ImportAction
{
    protected string|Closure|null $itemDiscriminatorColumn = null;

    protected array|Closure $itemOnlyColumns = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->job(ImportGroupedCsv::class);

        $this->action(function (array $data): void {
            $csvFile = $data['file'];
            $csvStream = $this->getUploadedFileStream($csvFile);

            if (! $csvStream) {
                return;
            }

            $csvReader = CsvReader::from($csvStream);

            if (filled($csvDelimiter = $this->getCsvDelimiter($csvReader))) {
                $csvReader->setDelimiter($csvDelimiter);
            }

            $headerOffset = $this->getHeaderOffset() ?? 0;
            $csvReader->setHeaderOffset($headerOffset);
            $csvResults = (new Statement)->process($csvReader);

            $totalRows = $csvResults->count();
            $maxRows = $this->getMaxRows() ?? $totalRows;

            if ($maxRows < $totalRows) {
                $this->failureNotification(
                    Notification::make()
                        ->title(__('filament-actions::import.notifications.max_rows.title'))
                        ->body(trans_choice('filament-actions::import.notifications.max_rows.body', $maxRows, [
                            'count' => Number::format($maxRows),
                        ]))
                        ->danger(),
                );

                $this->failure();

                return;
            }

            $authGuard = $this->getAuthGuard();
            $user = auth($authGuard)->user();

            $import = app(Import::class);
            $import->user()->associate($user);
            $import->file_name = $csvFile->getClientOriginalName();
            $import->file_path = $csvFile->getRealPath();
            $import->importer = $this->getImporter();
            $import->total_rows = $totalRows;
            $import->save();

            $columnMap = $data['columnMap'];

            $physicalRows = [];

            foreach ($csvResults->getRecords() as $index => $row) {
                $physicalRows[] = ['physicalRow' => $index + $headerOffset + 2, 'data' => $row];
            }

            [$groups, $failures] = $this->groupRows($physicalRows, $columnMap);

            if (filled($failures)) {
                $import->failedRows()->createMany($failures);
                $import->increment('processed_rows', count($failures));
            }

            $chunks = $this->chunkGroups($groups);

            $job = $this->getJob();

            $options = array_merge(
                $this->getOptions(),
                Arr::except($data, ['file', 'columnMap']),
            );

            $import->unsetRelation('user');

            $importJobs = collect($chunks)
                ->map(fn (array $chunk): object => app($job, [
                    'import' => $import,
                    'rows' => base64_encode(serialize($chunk)),
                    'columnMap' => $columnMap,
                    'options' => $options,
                ]));

            $importer = $import->getImporter(
                columnMap: $columnMap,
                options: $options,
            );

            event(new ImportStarted($import, $columnMap, $options));

            Bus::batch($importJobs->all())
                ->allowFailures()
                ->when(
                    filled($jobQueue = $importer->getJobQueue()),
                    fn (PendingBatch $batch) => $batch->onQueue($jobQueue),
                )
                ->when(
                    filled($jobConnection = $importer->getJobConnection()),
                    fn (PendingBatch $batch) => $batch->onConnection($jobConnection),
                )
                ->when(
                    filled($jobBatchName = $importer->getJobBatchName()),
                    fn (PendingBatch $batch) => $batch->name($jobBatchName),
                )
                ->finally(function () use ($authGuard, $columnMap, $import, $jobConnection, $options): void {
                    $import->touch('completed_at');

                    event(new ImportCompleted($import, $columnMap, $options));

                    if (! $import->user instanceof Authenticatable) {
                        return;
                    }

                    $failedRowsCount = $import->getFailedRowsCount();

                    Notification::make()
                        ->title($import->importer::getCompletedNotificationTitle($import))
                        ->body($import->importer::getCompletedNotificationBody($import))
                        ->when(
                            ! $failedRowsCount,
                            fn (Notification $notification) => $notification->success(),
                        )
                        ->when(
                            $failedRowsCount && ($failedRowsCount < $import->total_rows),
                            fn (Notification $notification) => $notification->warning(),
                        )
                        ->when(
                            $failedRowsCount === $import->total_rows,
                            fn (Notification $notification) => $notification->danger(),
                        )
                        ->when(
                            $failedRowsCount,
                            fn (Notification $notification) => $notification->actions([
                                Action::make('downloadFailedRowsCsv')
                                    ->label(trans_choice('filament-actions::import.notifications.completed.actions.download_failed_rows_csv.label', $failedRowsCount, [
                                        'count' => Number::format($failedRowsCount),
                                    ]))
                                    ->color('danger')
                                    ->url(URL::signedRoute('filament.imports.failed-rows.download', ['authGuard' => $authGuard, 'import' => $import], absolute: false), shouldOpenInNewTab: true)
                                    ->markAsRead(),
                            ]),
                        )
                        ->when(
                            ($jobConnection === 'sync') ||
                            (blank($jobConnection) && (config('queue.default') === 'sync')),
                            fn (Notification $notification) => $notification
                                ->persistent()
                                ->send(),
                            fn (Notification $notification) => $notification->sendToDatabase($import->user, isEventDispatched: true),
                        );
                })
                ->dispatch();

            if (
                ($jobConnection === 'sync')
                || (blank($jobConnection) && (config('queue.default') === 'sync'))
            ) {
                $this->successNotification(null);
                $this->successNotificationTitle(null);

                return;
            }

            $this->successNotification(
                Notification::make()
                    ->title($this->getSuccessNotificationTitle())
                    ->body(trans_choice('filament-actions::import.notifications.started.body', $import->total_rows, [
                        'count' => Number::format($import->total_rows),
                    ]))
                    ->success(),
            );
        });
    }

    public function itemDiscriminatorColumn(string|Closure $column): static
    {
        $this->itemDiscriminatorColumn = $column;

        return $this;
    }

    public function itemOnlyColumns(array|Closure $columns): static
    {
        $this->itemOnlyColumns = $columns;

        return $this;
    }

    public function getItemDiscriminatorColumn(): string
    {
        return $this->evaluate($this->itemDiscriminatorColumn);
    }

    /**
     * @return array<string>
     */
    public function getItemOnlyColumns(): array
    {
        return $this->evaluate($this->itemOnlyColumns);
    }

    /**
     * @param  array<int, array{physicalRow: int, data: array<string, mixed>}>  $physicalRows
     * @param  array<string, string>  $columnMap
     * @return array{0: array<int, array{parent: array<string, mixed>, parentPhysicalRow: int, items: array<int, array{row: array<string, mixed>, physicalRow: int}>}>, 1: array<int, array{data: array<string, mixed>, validation_error: string}>}
     */
    protected function groupRows(array $physicalRows, array $columnMap): array
    {
        $discriminatorHeader = $columnMap[$this->getItemDiscriminatorColumn()] ?? null;

        $itemOnlyHeaders = collect($this->getItemOnlyColumns())
            ->map(fn (string $column) => $columnMap[$column] ?? null)
            ->filter()
            ->all();

        $groups = [];
        $failures = [];
        $currentGroup = null;

        foreach ($physicalRows as $entry) {
            $row = $entry['data'];
            $physicalRow = $entry['physicalRow'];

            $discriminatorValue = $discriminatorHeader ? trim((string) ($row[$discriminatorHeader] ?? '')) : '';
            $isItemRow = $discriminatorValue !== '';

            if (! $isItemRow) {
                $hasItemOnlyData = collect($itemOnlyHeaders)->contains(fn (string $header) => trim((string) ($row[$header] ?? '')) !== '');

                if ($hasItemOnlyData) {
                    $failures[] = [
                        'data' => $row,
                        'validation_error' => __('resources/general/strings.import.item_without_product', ['row' => $physicalRow]),
                    ];

                    continue;
                }

                if ($currentGroup !== null) {
                    $groups[] = $currentGroup;
                }

                $currentGroup = ['parent' => $row, 'parentPhysicalRow' => $physicalRow, 'items' => []];

                continue;
            }

            if ($currentGroup === null) {
                $failures[] = [
                    'data' => $row,
                    'validation_error' => __('resources/general/strings.import.orphan_item_row', ['row' => $physicalRow]),
                ];

                continue;
            }

            $currentGroup['items'][] = ['row' => $row, 'physicalRow' => $physicalRow];
        }

        if ($currentGroup !== null) {
            $groups[] = $currentGroup;
        }

        return [$groups, $failures];
    }

    /**
     * @param  array<int, array{parent: array<string, mixed>, parentPhysicalRow: int, items: array<int, array{row: array<string, mixed>, physicalRow: int}>}>  $groups
     * @return array<int, array<int, array{parent: array<string, mixed>, parentPhysicalRow: int, items: array<int, array{row: array<string, mixed>, physicalRow: int}>}>>
     */
    protected function chunkGroups(array $groups, int $targetSize = 100): array
    {
        $chunks = [];
        $current = [];
        $currentSize = 0;

        foreach ($groups as $group) {
            $groupSize = 1 + count($group['items']);

            if ($currentSize > 0 && ($currentSize + $groupSize) > $targetSize) {
                $chunks[] = $current;
                $current = [];
                $currentSize = 0;
            }

            $current[] = $group;
            $currentSize += $groupSize;
        }

        if (filled($current)) {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
