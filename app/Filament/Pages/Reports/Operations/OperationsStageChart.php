<?php

namespace App\Filament\Pages\Reports\Operations;

use App\Services\Reports\Builders\OperationsReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OperationsStageChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return OperationsReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.operations.charts.stage.heading');
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
        $stages = app(OperationsReportService::class)->chartStages(ReportFilters::fromArray($this->pageFilters ?? []));

        return [
            'labels' => $stages['labels'],
            'datasets' => [[
                'label' => __('resources/report/strings.operations.charts.stage.dataset'),
                'data' => $stages['counts'],
                'backgroundColor' => ['#9CA3AF', '#06B6D4', '#F59E0B', '#3B82F6', '#10B981', '#F97316', '#22C55E'],
            ]],
        ];
    }
}
