<?php

namespace App\Filament\Pages\Reports\Operations;

use App\Services\Reports\Builders\OperationsReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class OperationsIdleByStageChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return OperationsReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.operations.charts.idle_by_stage.heading');
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
        $filters = ReportFilters::fromArray($this->pageFilters ?? []);
        $chart = app(OperationsReportService::class)->chartIdleByStage($filters);

        return [
            'labels' => $chart['labels'],
            'datasets' => [
                [
                    'label' => __('resources/report/strings.operations.charts.idle_by_stage.active', ['days' => $filters->idleDays]),
                    'data' => $chart['active'],
                    'backgroundColor' => '#10B981',
                ],
                [
                    'label' => __('resources/report/strings.operations.charts.idle_by_stage.idle', ['days' => $filters->idleDays]),
                    'data' => $chart['idle'],
                    'backgroundColor' => '#F59E0B',
                ],
            ],
        ];
    }
}
