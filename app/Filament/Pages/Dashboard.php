<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AccountWidget;
use App\Filament\Widgets\ConcentrationRiskWidget;
use App\Filament\Widgets\ExposureAgingWidget;
use App\Filament\Widgets\OpenCurrencyExposureWidget;
use App\Filament\Widgets\PipelineStallWidget;
use App\Filament\Widgets\ShipmentPunctualityWidget;
use App\Filament\Widgets\TradeCycleTimeWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    protected const TABS = [
        'risk' => [
            'icon' => Heroicon::OutlinedShieldExclamation,
            'widgets' => [ConcentrationRiskWidget::class, PipelineStallWidget::class],
        ],
        'performance' => [
            'icon' => Heroicon::OutlinedChartBar,
            'widgets' => [TradeCycleTimeWidget::class, ShipmentPunctualityWidget::class],
        ],
        'exposure' => [
            'icon' => Heroicon::OutlinedBanknotes,
            'widgets' => [ExposureAgingWidget::class, OpenCurrencyExposureWidget::class],
        ],
    ];

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            ...$this->getWidgetsSchemaComponents([AccountWidget::class]),
            Tabs::make('Analytics')
                ->tabs(collect(static::TABS)->map(
                    fn (array $tab, string $key) => Tab::make(__("resources/dashboard/strings.widgets.tabs.{$key}"))
                        ->icon($tab['icon'])
                        ->schema([
                            Grid::make(2)->schema($this->getWidgetsSchemaComponents($tab['widgets'])),
                        ])
                )->values()->all())
                ->columnSpanFull(),
        ]);
    }
}
