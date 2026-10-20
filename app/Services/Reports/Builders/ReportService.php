<?php

namespace App\Services\Reports\Builders;

use App\Services\Reports\Support\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

interface ReportService
{
    public function query(ReportFilters $filters): Builder;

    /**
     * A line may carry `quick` (a snake_case key, letters/digits/underscore): clicking the line sets `ReportFilters::$quick` and `query()` must then narrow the table to exactly the records the line counts. Cards, charts and checks never read `quick`.
     *
     * A line may also carry `url` (a drill-down link to another report page, rendered beside the label).
     *
     * @return array<int, array{key: string, label: string, lines: array<int, array{label: string, value: string, quick?: string, url?: string}>, tone: ?string}>
     */
    public function summary(ReportFilters $filters): array;

    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function rows(ReportFilters $filters): iterable;

    /**
     * @return array<string, string>
     */
    public function exportLabels(): array;
}
