<?php

namespace App\Filament\Pages\Reports\FxCommitments;

use App\Services\Reports\Builders\FxCommitmentsReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FxCommitmentsByBankChart extends ChartWidget
{
    use InteractsWithPageFilters;

    public static function canView(): bool
    {
        return FxCommitmentsReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.fx_commitments.charts.by_bank.heading');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y'];
    }

    protected function getData(): array
    {
        return app(FxCommitmentsReportService::class)->chartByBank(ReportFilters::fromArray($this->pageFilters ?? []));
    }
}
