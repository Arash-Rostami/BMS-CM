<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMetricLegend;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class ShipmentPunctualityWidget extends ChartWidget
{
    use HasMetricLegend;

    protected const COLORS = [
        'on_time' => '#10B981',
        'late_1_3' => '#F59E0B',
        'late_4_7' => '#F97316',
        'late_8_plus' => '#EF4444',
        'currently_overdue' => '#B91C1C',
    ];

    protected const LEGEND_KEY = 'punctuality';

    protected string $view = 'filament.widgets.chart-with-legend';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): string
    {
        return __('resources/dashboard/strings.widgets.punctuality.heading');
    }

    public function getDescription(): string
    {
        return __('resources/dashboard/strings.widgets.punctuality.description');
    }

    protected function getData(): array
    {
        $stats = AnalyticsService::shipmentPunctuality();

        return [
            'labels' => array_map(
                fn (string $key) => __("resources/dashboard/strings.widgets.punctuality.{$key}"),
                array_keys(static::COLORS)
            ),
            'datasets' => [[
                'data' => array_map(fn (string $key) => (int) ($stats[$key] ?? 0), array_keys(static::COLORS)),
                'backgroundColor' => array_values(static::COLORS),
            ]],
        ];
    }
}
