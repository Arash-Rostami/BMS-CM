<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMetricLegend;
use App\Services\AnalyticsService;
use Filament\Widgets\ChartWidget;

class ExposureAgingWidget extends ChartWidget
{
    use HasMetricLegend;

    protected const BUCKETS = [
        'bucket_0_30' => '#10B981',
        'bucket_31_60' => '#F59E0B',
        'bucket_61_90' => '#F97316',
        'bucket_90_plus' => '#EF4444',
    ];

    protected const LEGEND_KEY = 'exposure_aging';

    protected string $view = 'filament.widgets.chart-with-legend';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getHeading(): string
    {
        return __('resources/dashboard/strings.widgets.exposure_aging.heading');
    }

    public function getDescription(): string
    {
        return __('resources/dashboard/strings.widgets.exposure_aging.description');
    }

    protected function getData(): array
    {
        $rows = AnalyticsService::exposureAging();
        $isFa = app()->getLocale() === 'fa';

        return [
            'labels' => array_map(fn ($row) => $isFa ? $row->name : $row->english_name, $rows),
            'datasets' => array_map(
                fn (string $key, string $color) => [
                    'label' => __("resources/dashboard/strings.widgets.exposure_aging.{$key}"),
                    'data' => array_map(fn ($row) => (float) $row->{$key}, $rows),
                    'backgroundColor' => $color,
                ],
                array_keys(static::BUCKETS),
                array_values(static::BUCKETS)
            ),
        ];
    }
}
