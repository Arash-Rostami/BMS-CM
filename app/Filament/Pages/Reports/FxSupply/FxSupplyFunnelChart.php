<?php

namespace App\Filament\Pages\Reports\FxSupply;

use App\Services\Reports\Builders\FxSupplyReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FxSupplyFunnelChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return FxSupplyReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.fx_supply.charts.funnel.heading');
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
        $funnel = app(FxSupplyReportService::class)->chartFunnel(ReportFilters::fromArray($this->pageFilters ?? []));
        $palette = ['#9CA3AF', '#06B6D4', '#F59E0B', '#3B82F6', '#10B981', '#F97316', '#22C55E'];

        return [
            'labels' => $funnel['labels'],
            'datasets' => [[
                'label' => __('resources/report/strings.fx_supply.charts.funnel.dataset'),
                'data' => $funnel['counts'],
                'backgroundColor' => array_map(fn (int $index): string => $palette[$index % count($palette)], array_keys($funnel['counts'])),
            ]],
        ];
    }
}
