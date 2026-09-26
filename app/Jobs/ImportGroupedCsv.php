<?php

namespace App\Jobs;

use App\Services\Imports\GroupRowFailedException;
use Filament\Actions\Imports\Events\ImportChunkProcessed;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Jobs\ImportCsv as BaseImportCsv;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Expression;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class ImportGroupedCsv extends BaseImportCsv
{
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("import{$this->import->getKey()}"))
                ->releaseAfter(15)
                ->expireAfter(600),
        ];
    }

    public function handle(): void
    {
        try {
            $this->handleGroups();
        } finally {
            if (method_exists($this->importer, 'resetReservedNumbers')) {
                $this->importer->resetReservedNumbers();
            }
        }
    }

    protected function handleGroups(): void
    {
        /** @var Authenticatable $user */
        $user = $this->import->user;

        auth()->setUser($user);

        $processedRows = 0;
        $successfulRows = 0;

        $groups = is_array($this->rows) ? $this->rows : unserialize(base64_decode($this->rows));

        DB::transaction(function () use (&$processedRows, $groups, &$successfulRows): void {
            foreach ($groups as $group) {
                $physicalRowCount = 1 + count($group['items']);

                try {
                    if (method_exists($this->importer, 'forGroup')) {
                        $this->importer->forGroup(array_map(
                            fn (array $item) => $this->utf8Encode($item['row']),
                            $group['items'],
                        ));
                    }

                    ($this->importer)($this->utf8Encode($group['parent']));

                    $successfulRows += $physicalRowCount;
                } catch (GroupRowFailedException $exception) {
                    $this->logGroupFailure($group, $exception->rowOffset, $exception->getMessage());
                } catch (RowImportFailedException $exception) {
                    $this->logGroupFailure($group, null, $exception->getMessage());
                } catch (ValidationException $exception) {
                    $this->logGroupFailure($group, null, collect($exception->errors())->flatten()->implode(' '));
                } catch (Throwable $exception) {
                    report($exception);
                    $this->logGroupFailure($group, null, null);
                }

                $processedRows += $physicalRowCount;
            }

            $this->import::query()
                ->whereKey($this->import)
                ->lockForUpdate()
                ->update([
                    'processed_rows' => new Expression('processed_rows + '.$processedRows),
                    'successful_rows' => new Expression('successful_rows + '.$successfulRows),
                ]);

            $this->import::query()
                ->whereKey($this->import)
                ->whereColumn('processed_rows', '>', 'total_rows')
                ->lockForUpdate()
                ->update([
                    'processed_rows' => new Expression('total_rows'),
                ]);

            $this->import::query()
                ->whereKey($this->import)
                ->whereColumn('successful_rows', '>', 'total_rows')
                ->lockForUpdate()
                ->update([
                    'successful_rows' => new Expression('total_rows'),
                ]);

            $this->import->failedRows()->createMany($this->failedRows);
        });

        $this->import->refresh();

        event(new ImportChunkProcessed(
            $this->import,
            $this->columnMap,
            $this->options,
            $processedRows,
            $successfulRows,
        ));
    }

    protected function logGroupFailure(array $group, ?int $offendingItemOffset, ?string $message): void
    {
        $offenderPhysicalRow = $offendingItemOffset === null
            ? $group['parentPhysicalRow']
            : ($group['items'][$offendingItemOffset]['physicalRow'] ?? $group['parentPhysicalRow']);

        $this->logFailedRow(
            $this->utf8Encode($group['parent']),
            $offendingItemOffset === null ? $message : __('resources/general/strings.import.sibling_row_failed', ['row' => $offenderPhysicalRow])
        );

        foreach ($group['items'] as $offset => $item) {
            $this->logFailedRow(
                $this->utf8Encode($item['row']),
                $offset === $offendingItemOffset ? $message : __('resources/general/strings.import.sibling_row_failed', ['row' => $offenderPhysicalRow])
            );
        }
    }
}
