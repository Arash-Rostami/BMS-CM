<?php

namespace App\Filament\Pages\Reports\Requester;

use App\Services\Reports\Builders\RequesterReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class RequesterLateByStageChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return RequesterReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.requester.charts.late_by_stage.heading');
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
        $chart = app(RequesterReportService::class)->chartLateByStage(ReportFilters::fromArray($this->pageFilters ?? []));

        return [
            'labels' => $chart['labels'],
            'datasets' => [
                [
                    'label' => __('resources/report/strings.requester.charts.late_by_stage.on_time'),
                    'data' => $chart['on_time'],
                    'backgroundColor' => '#10B981',
                ],
                [
                    'label' => __('resources/report/strings.requester.charts.late_by_stage.late'),
                    'data' => $chart['late'],
                    'backgroundColor' => '#EF4444',
                ],
            ],
        ];
    }
}
