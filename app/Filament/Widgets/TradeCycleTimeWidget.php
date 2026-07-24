<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMetricLegend;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class TradeCycleTimeWidget extends ChartWidget
{
    use HasMetricLegend;

    protected const LEGEND_KEY = 'cycle_time';

    protected string $view = 'filament.widgets.chart-with-legend';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): string
    {
        return __('resources/dashboard/strings.widgets.cycle_time.heading');
    }

    public function getDescription(): string
    {
        return __('resources/dashboard/strings.widgets.cycle_time.description');
    }

    protected function getData(): array
    {
        $stages = AnalyticsService::cycleTimeByStage();

        return [
            'labels' => array_map(
                fn (string $key) => __("resources/dashboard/strings.widgets.cycle_time.stages.{$key}"),
                array_keys($stages)
            ),
            'datasets' => [
                [
                    'label' => __('resources/dashboard/strings.widgets.cycle_time.p50'),
                    'data' => array_column($stages, 'p50'),
                    'backgroundColor' => '#5C6AC4',
                ],
                [
                    'label' => __('resources/dashboard/strings.widgets.cycle_time.p90'),
                    'data' => array_column($stages, 'p90'),
                    'backgroundColor' => '#BCCCDC',
                ],
            ],
        ];
    }
}
