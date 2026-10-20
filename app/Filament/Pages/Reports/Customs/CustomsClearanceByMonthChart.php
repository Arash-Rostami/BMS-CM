<?php

namespace App\Filament\Pages\Reports\Customs;

use App\Services\Reports\Builders\CustomsReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CustomsClearanceByMonthChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return CustomsReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.customs.charts.by_month.heading');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $months = app(CustomsReportService::class)->chartByMonth(ReportFilters::fromArray($this->pageFilters ?? []));

        return [
            'labels' => $months['labels'],
            'datasets' => [[
                'label' => __('resources/report/strings.customs.charts.by_month.dataset'),
                'data' => $months['averages'],
                'backgroundColor' => '#3B82F6',
                'borderColor' => '#3B82F6',
            ]],
        ];
    }
}
