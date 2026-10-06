<?php

namespace App\Filament\Traits;

use App\Observers\CodeGeneratingObserver;
use App\Observers\NotificationDispatcher;
use App\Services\CodeGenerator;
use App\Services\Imports\ImportColumnDefinition;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

trait ImportDefaults
{
    protected static array $reservedNumberQueue = [];

    public function __invoke(array $data): void
    {
        $previousLocale = app()->getLocale();
        app()->setLocale($this->options['locale'] ?? $previousLocale);

        try {
            CodeGeneratingObserver::duringImport(
                fn () => NotificationDispatcher::suspended(
                    fn () => $this->runInTransaction($data)
                )
            );
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    protected function runInTransaction(array $data): void
    {
        try {
            DB::transaction(fn () => parent::__invoke($data));
        } catch (QueryException $exception) {
            if (static::isDeadlock($exception)) {
                throw $exception;
            }

            throw new RowImportFailedException($exception->getMessage(), 0, $exception);
        }
    }

    public static function isDeadlock(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;

        return in_array($sqlState, ['40001'], true) || in_array((int) $driverCode, [1213, 1205], true);
    }

    public function resolveRecord(): ?Model
    {
        $model = static::getModel();
        $identifier = $model::SCANNABLE_IDENTIFIER;
        $value = $this->data[$identifier] ?? null;

        if (blank($value)) {
            $this->assertRequiredColumnsForNewRecordsPresent();

            return new $model;
        }

        $existing = $model::withTrashed()->where($identifier, $value)->first();

        if ($existing?->trashed()) {
            throw new RowImportFailedException(__('resources/general/strings.import.record_trashed', ['value' => $value]));
        }

        if (! $existing) {
            $this->assertRequiredColumnsForNewRecordsPresent();
        }

        return $existing ?? new $model;
    }

    /**
     * @return array<string, string> column name => translated label
     */
    protected function requiredColumnsForNewRecords(): array
    {
        return [];
    }

    protected function assertRequiredColumnsForNewRecordsPresent(): void
    {
        foreach ($this->requiredColumnsForNewRecords() as $column => $label) {
            if (blank($this->data[$column] ?? null)) {
                throw new RowImportFailedException(__('resources/general/strings.import.required_for_new_record', ['label' => $label]));
            }
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __('resources/general/strings.import.completed', [
            'successful' => number_format($import->successful_rows),
        ]);

        $failedRowsCount = $import->getFailedRowsCount();

        if ($failedRowsCount) {
            $body .= __('resources/general/strings.import.failed', [
                'failed' => number_format($failedRowsCount),
            ]);
        }

        $realFailedRowsCount = $import->failedRows()->count();

        if ($realFailedRowsCount !== $failedRowsCount) {
            $body .= __('resources/general/strings.import.count_mismatch', [
                'missing' => number_format(abs($failedRowsCount - $realFailedRowsCount)),
            ]);
        }

        return $body;
    }

    public static function hasCompletedNotificationMismatch(Import $import): bool
    {
        return $import->failedRows()->count() !== $import->getFailedRowsCount();
    }

    public static function getOptionsFormComponents(): array
    {
        return [
            Hidden::make('locale')->default(app()->getLocale()),
            Toggle::make('jalali')
                ->label(__('resources/general/strings.import.options.jalali'))
                ->live()
                ->helperText(fn (?bool $state): string => $state
                    ? __('resources/general/strings.import.options.jalali_helper_on')
                    : __('resources/general/strings.import.options.jalali_helper_off'))
                ->default(isJalaliCalendar()),
            Select::make('date_format')
                ->label(__('resources/general/strings.import.options.date_format'))
                ->native(false)
                ->options([
                    'Y-m-d' => 'YYYY-MM-DD',
                    'd/m/Y' => 'DD/MM/YYYY',
                    'm/d/Y' => 'MM/DD/YYYY',
                ])
                ->default('Y-m-d')
                ->required(),
        ];
    }

    /**
     * @return string[]
     */
    public static function autoFilledColumnNames(): array
    {
        return collect(static::importColumns())
            ->reject(fn (ImportColumnDefinition $definition) => $definition->includeInTemplate())
            ->map(fn (ImportColumnDefinition $definition) => $definition->name)
            ->values()
            ->all();
    }

    protected static function assertColumnNamesAllowed(array $columns): array
    {
        $denied = ['id', 'user_id', 'updated_by_id', 'attachable_type', 'entity_type', 'targetable_type', 'correspondable_type', 'specifiable_type', 'notifiable_type', 'statusable_type'];

        foreach ($columns as $column) {
            $name = $column->getName();

            if (in_array($name, $denied, true)) {
                throw new LogicException("Import column [{$name}] bypasses mass-assignment protection and is not allowed.");
            }
        }

        return $columns;
    }

    protected function buildRawDataMap(): array
    {
        return collect(static::importColumns())
            ->mapWithKeys(fn (ImportColumnDefinition $definition) => [
                $definition->name => $this->originalData[$this->columnMap[$definition->name] ?? $definition->name] ?? null,
            ])
            ->all();
    }

    protected function fillReservedNumberIfBlank(string $field): void
    {
        if (blank($this->record->{$field})) {
            $this->record->{$field} = static::nextReservedNumber($field);
        }
    }

    protected static function nextReservedNumber(string $field): string
    {
        if (empty(static::$reservedNumberQueue[$field])) {
            static::$reservedNumberQueue[$field] = static::reserveNumberChunk($field, 100);
        }

        return array_shift(static::$reservedNumberQueue[$field]);
    }

    protected static function reserveNumberChunk(string $field, int $count): array
    {
        $first = CodeGenerator::generate($field);
        $lastDash = strrpos($first, '-');

        if (substr_count($first, '-') >= 2) {
            $base = substr($first, 0, $lastDash);
            $start = ((int) substr($first, $lastDash + 1)) + 1;
        } else {
            $base = $first;
            $start = 1;
        }

        $values = [$first];

        for ($i = 0; $i < $count - 1; $i++) {
            $values[] = "{$base}-".($start + $i);
        }

        return $values;
    }

    public function resetReservedNumbers(): void
    {
        static::$reservedNumberQueue = [];
    }
}
