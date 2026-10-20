<?php

namespace App\Filament\Pages\Reports\FxSupply;

use App\Services\Reports\Builders\FxSupplyReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class FxSupplyByBankChart extends ChartWidget
{
    use InteractsWithPageFilters;

    private const COLORS = ['#10B981', '#F59E0B', '#3B82F6', '#EF4444', '#8B5CF6', '#14B8A6'];

    public static function canView(): bool
    {
        return FxSupplyReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.fx_supply.charts.by_bank.heading');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $chart = app(FxSupplyReportService::class)->chartByBank(ReportFilters::fromArray($this->pageFilters ?? []));

        return [
            'labels' => $chart['labels'],
            'datasets' => array_map(
                fn (array $dataset, int $index): array => $dataset + ['backgroundColor' => self::COLORS[$index % count(self::COLORS)]],
                $chart['datasets'],
                array_keys($chart['datasets']),
            ),
        ];
    }
}
