<?php

namespace App\Filament\Pages\Reports\InTransit;

use App\Services\Reports\Builders\InTransitReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class InTransitEtaChart extends ChartWidget
{
    use InteractsWithPageFilters;

    private const COLORS = ['#3B82F6', '#9CA3AF'];

    public static function canView(): bool
    {
        return InTransitReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.in_transit.charts.eta_buckets.heading');
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
        $buckets = app(InTransitReportService::class)->chartEtaBuckets(ReportFilters::fromArray($this->pageFilters ?? []));

        return [
            'labels' => $buckets['labels'],
            'datasets' => array_map(
                fn (array $dataset, int $index): array => $dataset + ['backgroundColor' => self::COLORS[$index % count(self::COLORS)]],
                $buckets['datasets'],
                array_keys($buckets['datasets']),
            ),
        ];
    }
}
