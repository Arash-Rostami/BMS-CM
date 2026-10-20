<?php

namespace App\Filament\Pages\Reports\Executive;

use App\Services\Reports\Builders\ExecutiveReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ExecutiveValueByCategoryChart extends ChartWidget
{
    use InteractsWithPageFilters;

    private const COLORS = ['#10B981', '#F59E0B', '#EF4444', '#9CA3AF'];

    public static function canView(): bool
    {
        return ExecutiveReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.executive.charts.by_category.heading');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['scales' => ['x' => ['stacked' => true], 'y' => ['stacked' => true]]];
    }

    protected function getData(): array
    {
        $chart = app(ExecutiveReportService::class)->chartByCategory(ReportFilters::fromArray($this->pageFilters ?? []));

        return [
            'labels' => $chart['labels'],
            'datasets' => array_map(
                fn (array $dataset, int $index): array => $dataset + ['backgroundColor' => self::COLORS[$index % count(self::COLORS)]],
                $chart['datasets'],
                array_keys($chart['datasets']),
            ),
        ];
    }
}
