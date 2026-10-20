<?php

namespace App\Filament\Pages\Reports\Operations;

use App\Filament\Resources\General\TableComponents;
use App\Services\Reports\Support\ActivityFeed;
use App\Services\Reports\Support\ReportFilters;
use Filament\Support\ArrayRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Pagination\LengthAwarePaginator;

class OperationsActivityWidget extends TableWidget
{
    use InteractsWithPageFilters;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return OperationsReport::canAccess();
    }

    public function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->heading(__('resources/report/strings.operations.feed.heading'))
            ->records(fn (int $page, int|string $recordsPerPage): LengthAwarePaginator => $this->paginate($page, $recordsPerPage))
            ->columns([
                TextColumn::make('at')->label(__('resources/report/strings.operations.feed.when'))->formatStateUsing(fn (mixed $state): string => adaptiveDate($state, true)),
                TextColumn::make('by')->label(__('resources/report/strings.operations.feed.by')),
                TextColumn::make('file')->label(__('resources/report/strings.operations.feed.file'))->badge(),
                TextColumn::make('module')->label(__('resources/report/strings.operations.feed.module')),
                TextColumn::make('change')->label(__('resources/report/strings.operations.feed.change'))->wrap(),
            ])
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10));
    }

    private function paginate(int $page, int|string $perPage): LengthAwarePaginator
    {
        $feed = app(ActivityFeed::class);
        $query = $feed->query(ReportFilters::fromArray($this->pageFilters ?? []));
        $total = (clone $query)->reorder()->count();
        $size = is_numeric($perPage) ? max((int) $perPage, 1) : max($total, 1);
        $rows = collect($feed->rows($query->forPage($page, $size)->get()))
            ->map(fn (array $row): array => [ArrayRecord::getKeyName() => $row['key']] + $row);

        return new LengthAwarePaginator($rows, $total, $size, $page);
    }
}
