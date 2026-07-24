<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMetricLegend;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class OpenCurrencyExposureWidget extends ChartWidget
{
    use HasMetricLegend;

    protected const LEGEND_KEY = 'open_exposure';

    protected string $view = 'filament.widgets.chart-with-legend';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): string
    {
        return __('resources/dashboard/strings.widgets.open_exposure.heading');
    }

    public function getDescription(): string
    {
        return __('resources/dashboard/strings.widgets.open_exposure.description');
    }

    protected function getData(): array
    {
        $rows = AnalyticsService::openCurrencyExposure();
        $isFa = app()->getLocale() === 'fa';

        return [
            'labels' => array_map(fn ($row) => $isFa ? $row->name : $row->english_name, $rows),
            'datasets' => [[
                'data' => array_map(fn ($row) => (float) $row->open_exposure, $rows),
                'backgroundColor' => '#5C6AC4',
            ]],
        ];
    }
}
