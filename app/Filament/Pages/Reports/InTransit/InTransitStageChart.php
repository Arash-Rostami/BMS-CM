<?php

namespace App\Filament\Pages\Reports\InTransit;

use App\Services\Reports\Builders\InTransitReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class InTransitStageChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return InTransitReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.in_transit.charts.stages.heading');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }

    protected function getData(): array
    {
        $stages = app(InTransitReportService::class)->chartStages(ReportFilters::fromArray($this->pageFilters ?? []));
        $palette = ['#9CA3AF', '#06B6D4', '#F59E0B', '#3B82F6', '#10B981', '#F97316', '#22C55E'];

        return [
            'labels' => $stages['labels'],
            'datasets' => [[
                'label' => __('resources/report/strings.in_transit.charts.stages.dataset'),
                'data' => $stages['counts'],
                'backgroundColor' => array_map(fn (int $index): string => $palette[$index % count($palette)], array_keys($stages['counts'])),
            ]],
        ];
    }
}
