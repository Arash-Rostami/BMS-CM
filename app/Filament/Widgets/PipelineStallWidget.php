<?php

namespace App\Filament\Widgets;

use App\Services\AnalyticsService;
use Filament\Widgets\Widget;

class PipelineStallWidget extends Widget
{
    protected const ROUTES = [
        'purchase_request' => 'filament.dashboard.resources.purchase-requests.edit',
        'registered_order' => 'filament.dashboard.resources.registered-orders.edit',
        'payment' => 'filament.dashboard.resources.payments.edit',
        'shipment' => 'filament.dashboard.resources.shipments.edit',
    ];

    protected string $view = 'filament.widgets.pipeline-stall';

    protected function getViewData(): array
    {
        $stalls = AnalyticsService::pipelineStalls();

        foreach ($stalls as $stall) {
            $stall->url = route(static::ROUTES[$stall->record_type], $stall->id);
        }

        return ['stalls' => $stalls];
    }
}
