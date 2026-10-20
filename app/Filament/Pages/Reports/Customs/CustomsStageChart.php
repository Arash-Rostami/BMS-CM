<?php

namespace App\Filament\Pages\Reports\Customs;

use App\Services\Reports\Builders\CustomsReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class CustomsStageChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return CustomsReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.customs.charts.stage.heading');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $stage = app(CustomsReportService::class)->chartStage(ReportFilters::fromArray($this->pageFilters ?? []));

        return [
            'labels' => $stage['labels'],
            'datasets' => [[
                'label' => __('resources/report/strings.customs.charts.stage.dataset'),
                'data' => $stage['counts'],
                'backgroundColor' => '#3B82F6',
            ]],
        ];
    }
}
