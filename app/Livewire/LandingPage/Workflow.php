<?php

namespace App\Livewire\LandingPage;

use App\Services\DeskReferenceGroups;
use Illuminate\Support\Facades\Cache;
use Livewire\Component;

class Workflow extends Component
{
    public array $stats = [];

    public bool $isRtl = false;

    public function mount(array $counts, bool $isRtl): void
    {
        $this->stats = [
            'purchaseRequests' => (int) ($counts['purchase_requests'] ?? 0),
            'proformaInvoices' => (int) ($counts['proforma_invoices'] ?? 0),
            'registeredOrders' => (int) ($counts['registered_orders'] ?? 0),
            'bankProfiles' => (int) ($counts['bank_profiles'] ?? 0),
            'purchaseOrders' => (int) ($counts['purchase_orders'] ?? 0),
            'payments' => (int) ($counts['payments'] ?? 0),
            'shipments' => (int) ($counts['shipments'] ?? 0),
            'customs' => (int) ($counts['customs'] ?? 0),
        ];

        $this->isRtl = $isRtl;
    }

    protected function insightGroups(): array
    {
        return Cache::remember(
            'desk_reference_insight_groups:'.app()->getLocale(),
            now()->addHour(),
            fn () => $this->buildInsightGroups(),
        );
    }

    private function buildInsightGroups(): array
    {
        $accents = [
            'request_approval' => 'blue',
            'order_processing' => 'green',
            'procurement_payment' => 'yellow',
            'logistics' => 'red',
        ];

        $routes = [
            'request_approval' => 'filament.dashboard.resources.purchase-requests.index',
            'order_processing' => 'filament.dashboard.resources.registered-orders.index',
            'procurement_payment' => 'filament.dashboard.resources.purchase-orders.index',
            'logistics' => 'filament.dashboard.resources.shipments.index',
        ];

        $groups = [];

        foreach (DeskReferenceGroups::all() as $group => $content) {
            if (empty($content['tips'])) {
                continue;
            }

            $groups[] = [
                'key' => $group,
                'title' => $content['tab_label'] ?? $group,
                'scopeLabel' => $content['scope_label'] ?? null,
                'accent' => $accents[$group],
                'tips' => $content['tips'],
                'terms' => $content['terms'] ?? [],
                'process' => $content['process'] ?? [],
                'dos' => $content['dos'] ?? [],
                'donts' => $content['donts'] ?? [],
                'poster' => ! empty($content['media']['poster']) ? asset('img/desk-reference/'.$content['media']['poster']) : null,
                'audio' => ! empty($content['media']['audio']) ? asset('audio/desk-reference/'.$content['media']['audio']) : null,
                'video' => ! empty($content['media']['video']) ? asset('video/desk-reference/'.$content['media']['video']) : null,
                'route' => route($routes[$group]),
            ];
        }

        return $groups;
    }

    public function render()
    {
        return view('livewire.landing-page.workflow', [
            'insightGroups' => $this->insightGroups(),
        ]);
    }
}
