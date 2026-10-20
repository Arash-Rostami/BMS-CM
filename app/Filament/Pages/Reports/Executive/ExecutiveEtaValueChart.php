<?php

namespace App\Filament\Pages\Reports\Executive;

use App\Services\Reports\Builders\ExecutiveReportService;
use App\Services\Reports\Support\ReportFilters;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class ExecutiveEtaValueChart extends ChartWidget
{
    use InteractsWithPageFilters;

    private const COLORS = ['#EF4444', '#F59E0B', '#3B82F6', '#10B981', '#9CA3AF'];

    public static function canView(): bool
    {
        return ExecutiveReport::canAccess();
    }

    public function getHeading(): string
    {
        return __('resources/report/strings.executive.charts.by_eta.heading');
    }

    public function getDescription(): ?string
    {
        $chart = $this->chart();
        $excluded = collect($chart['excluded'])->map(fn (float $amount, string $currency): string => $currency.' '.delimiter($amount))->implode(', ');

        return __('resources/report/strings.executive.charts.excluded', [
            'rate' => $excluded === '' ? '-' : $excluded,
            'data' => $chart['excluded_data'],
        ]);
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
        $chart = $this->chart();

        return [
            'labels' => $chart['labels'],
            'datasets' => array_map(fn (array $dataset): array => $dataset + ['backgroundColor' => self::COLORS], $chart['datasets']),
        ];
    }

    private function chart(): array
    {
        return app(ExecutiveReportService::class)->chartByEta(ReportFilters::fromArray($this->pageFilters ?? []));
    }
}
