<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Schemas\Components\Component;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\HtmlString;

class ConcentrationRiskWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 1;

    public function getSectionContentComponent(): Component
    {
        return parent::getSectionContentComponent()->footer(new HtmlString(
            view('components.metric-legend', [
                'what' => __('resources/dashboard/strings.widgets.legend.concentration.what'),
                'data' => __('resources/dashboard/strings.widgets.legend.concentration.data'),
                'why' => __('resources/dashboard/strings.widgets.legend.concentration.why'),
                'technical' => auth()->user()?->isAdmin() ? __('resources/dashboard/strings.widgets.legend.concentration.technical') : null,
            ])->render()
        ));
    }

    protected function getStats(): array
    {
        $risk = AnalyticsService::concentrationRisk();

        return [
            $this->stat($risk['supplier_hhi'], __('resources/dashboard/strings.widgets.concentration.supplier')),
            $this->stat($risk['currency_hhi'], __('resources/dashboard/strings.widgets.concentration.currency')),
        ];
    }

    protected function stat(float $hhi, string $label): Stat
    {
        [$color, $description] = match (true) {
            $hhi >= 2500 => ['danger', __('resources/dashboard/strings.widgets.concentration.risk_high')],
            $hhi >= 1500 => ['warning', __('resources/dashboard/strings.widgets.concentration.risk_moderate')],
            default => ['success', __('resources/dashboard/strings.widgets.concentration.risk_low')],
        };

        return Stat::make($label, number_format($hhi))
            ->description($description)
            ->color($color);
    }
}
