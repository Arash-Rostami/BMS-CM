<?php

namespace App\Filament\Pages\Reports\Widgets;

use App\Filament\Pages\Reports\ReportPage;
use App\Filament\Resources\General\TableComponents;
use App\Services\Reports\Checks\Check;
use App\Services\Reports\Checks\CheckRegistry;
use App\Services\Reports\Support\ReportFilters;
use Filament\Support\ArrayRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;

abstract class ReportAttentionWidget extends TableWidget
{
    use InteractsWithPageFilters;

    private const SEVERITY_ORDER = ['error' => 0, 'warning' => 1, 'info' => 2];

    private const SEVERITY_COLOR = ['error' => 'danger', 'warning' => 'warning', 'info' => 'info'];

    protected int|string|array $columnSpan = 'full';

    abstract protected static function report(): string;

    public static function canView(): bool
    {
        $page = ReportPage::registry()[static::report()] ?? null;

        return $page !== null && $page::canAccess();
    }

    public function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->heading(__('resources/report/strings.attention.heading'))
            ->records(fn (array $filters, int $page, int|string $recordsPerPage): LengthAwarePaginator => $this->paginate($this->rows($filters['check']['value'] ?? null, $filters['severity']['value'] ?? null), $page, $recordsPerPage))
            ->columns($this->columns())
            ->filters([$this->severityFilter(), $this->checkFilter()])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10));
    }

    /**
     * @return array<int, TextColumn>
     */
    private function columns(): array
    {
        return [
            TextColumn::make('label')->label(__('resources/report/strings.attention.columns.record'))->weight('semibold')->url(fn (array $record): ?string => $record['url']),
            TextColumn::make('problem')->label(__('resources/report/strings.attention.columns.problem'))->wrap(),
            TextColumn::make('offending')->label(__('resources/report/strings.attention.columns.offending')),
            TextColumn::make('expected')->label(__('resources/report/strings.attention.columns.expected'))->wrap(),
            TextColumn::make('severity')
                ->label(__('resources/report/strings.attention.columns.severity'))->badge()
                ->color(fn (string $state): string => self::SEVERITY_COLOR[$state])
                ->formatStateUsing(fn (string $state): string => __('resources/report/strings.attention.severity.'.$state)),
            TextColumn::make('changed_by')->label(__('resources/report/strings.attention.columns.changed_by'))->placeholder('-'),
            TextColumn::make('changed_at')->label(__('resources/report/strings.attention.columns.changed_at'))->formatStateUsing(fn (mixed $state): string => adaptiveDate($state, true))->placeholder('-'),
        ];
    }

    #[On('report-severity')]
    public function filterBySeverity(?string $severity = null): void
    {
        $this->tableFilters['severity'] = ['value' => array_key_exists((string) $severity, self::SEVERITY_ORDER) ? $severity : null];
        $this->handleTableFilterUpdates();
    }

    private function severityFilter(): SelectFilter
    {
        return SelectFilter::make('severity')
            ->label(__('resources/report/strings.attention.columns.severity'))
            ->options(collect(self::SEVERITY_ORDER)->mapWithKeys(fn (int $order, string $severity): array => [$severity => __('resources/report/strings.attention.severity.'.$severity)])->all())
            ->native(false);
    }

    private function checkFilter(): SelectFilter
    {
        return SelectFilter::make('check')
            ->label(__('resources/report/strings.attention.filters.check'))
            ->options($this->checks()->mapWithKeys(fn (Check $check): array => [$check->key => __($check->messageKey())])->all())
            ->native(false);
    }

    private function checks(): Collection
    {
        return app(CheckRegistry::class)->forReport(static::report());
    }

    private function rows(?string $check, ?string $severity = null): Collection
    {
        $registry = app(CheckRegistry::class);
        $filters = ReportFilters::fromArray($this->pageFilters ?? []);

        return $this->checks()
            ->when($check, fn (Collection $checks, string $key): Collection => $checks->where('key', $key))
            ->when($severity, fn (Collection $checks, string $level): Collection => $checks->where('severity', $level))
            ->flatMap(fn (Check $check): Collection => $registry->evaluate($check, $filters))
            ->sortBy(fn (array $row): string => self::SEVERITY_ORDER[$row['severity']].'|'.$row['label'])
            ->map(fn (array $row): array => [ArrayRecord::getKeyName() => $row['check'].':'.$row['id']] + $row)
            ->values();
    }

    private function paginate(Collection $rows, int $page, int|string $perPage): LengthAwarePaginator
    {
        $size = is_numeric($perPage) ? max((int) $perPage, 1) : max($rows->count(), 1);

        return new LengthAwarePaginator($rows->forPage($page, $size)->values(), $rows->count(), $size, $page);
    }
}
