<?php

namespace App\Filament\Pages\Reports\FxCommitments;

use App\Services\Reports\Builders\FxCommitmentsReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FxCommitmentsByMonthChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return FxCommitmentsReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.fx_commitments.charts.by_month.heading');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $chart = app(FxCommitmentsReportService::class)->chartByMonth(ReportFilters::fromArray($this->pageFilters ?? []));

        return [
            'labels' => $chart['labels'],
            'datasets' => array_map(fn (array $dataset): array => $dataset + ['borderColor' => $dataset['backgroundColor'], 'fill' => false, 'tension' => 0.2], $chart['datasets']),
        ];
    }
}
