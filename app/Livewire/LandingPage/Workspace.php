<?php

namespace App\Livewire\LandingPage;

use Livewire\Component;

class Workspace extends Component
{
    private const TONE = [
        'blue' => 'bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20',
        'green' => 'bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 border border-green-200 dark:border-green-500/20',
        'yellow' => 'bg-yellow-50 dark:bg-yellow-500/10 text-yellow-800 dark:text-yellow-400 border border-yellow-200 dark:border-yellow-500/20',
        'red' => 'bg-red-50 dark:bg-red-500/10 text-red-700 dark:text-red-400 border border-red-200 dark:border-red-500/20',
        'slate' => 'bg-slate-100 dark:bg-white/5 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-white/10',
    ];

    private const BADGE_TONE = [
        'blue' => 'bg-blue-500 text-white',
        'green' => 'bg-green-500 text-white',
        'yellow' => 'bg-yellow-500 text-white',
        'red' => 'bg-red-500 text-white',
        'slate' => 'bg-slate-500 text-white',
    ];

    public array $workspaceConfig = [];

    public function mount(array $counts): void
    {
        $this->workspaceConfig = [
            'modules' => array_values($this->modules()),
            'stats' => $this->stats($counts),
            'recordsUrl' => url('/workspace/records/__RES__'),
        ];
    }

    private function modulesList(): array
    {
        return [
            ['id' => 'purchaseRequests', 'searchable' => true,  'heroicon' => 'heroicon-o-document-text',            'label' => __('dashboard/strings.resources.purchase_requests'),      'route' => route('filament.dashboard.resources.purchase-requests.index'),     'accent' => 'blue'],
            ['id' => 'proformaInvoices', 'searchable' => true,  'heroicon' => 'heroicon-o-document-magnifying-glass', 'label' => __('dashboard/strings.proforma'),                         'route' => route('filament.dashboard.resources.proforma-invoices.index'),     'accent' => 'blue'],
            ['id' => 'registeredOrders', 'searchable' => true,  'heroicon' => 'heroicon-o-document-check',           'label' => __('dashboard/strings.view_orders'),                      'route' => route('filament.dashboard.resources.registered-orders.index'),     'accent' => 'green'],
            ['id' => 'bankProfiles',     'searchable' => true,  'heroicon' => 'heroicon-o-building-office',          'label' => __('dashboard/strings.banks'),                            'route' => route('filament.dashboard.resources.bank-profiles.index'),         'accent' => 'green'],
            ['id' => 'purchaseOrders',   'searchable' => true,  'heroicon' => 'heroicon-o-shopping-bag',             'label' => __('dashboard/strings.purchase_orders'),                  'route' => route('filament.dashboard.resources.purchase-orders.index'),       'accent' => 'yellow'],
            ['id' => 'payments',         'searchable' => true,  'heroicon' => 'heroicon-o-banknotes',                'label' => __('dashboard/strings.payments'),                         'route' => route('filament.dashboard.resources.payments.index'),              'accent' => 'yellow'],
            ['id' => 'shipments',        'searchable' => true,  'heroicon' => 'heroicon-o-truck',                    'label' => __('dashboard/strings.submodules.shipment.title'),        'route' => route('filament.dashboard.resources.shipments.index'),             'accent' => 'red'],
            ['id' => 'customs',          'searchable' => true,  'heroicon' => 'heroicon-o-clipboard-document-check', 'label' => __('dashboard/strings.submodules.custom_clearance.title'), 'route' => route('filament.dashboard.resources.customs.index'),               'accent' => 'red'],
            ['id' => 'categories',       'searchable' => false, 'heroicon' => 'heroicon-o-tag',                      'label' => __('dashboard/strings.resources.categories'),             'route' => route('filament.dashboard.resources.categories.index'),            'accent' => 'slate'],
            ['id' => 'products',         'searchable' => false, 'heroicon' => 'heroicon-o-cube',                     'label' => __('dashboard/strings.resources.products'),               'route' => route('filament.dashboard.resources.products.index'),              'accent' => 'slate'],
            ['id' => 'companies',        'searchable' => false, 'heroicon' => 'heroicon-o-building-storefront',      'label' => __('dashboard/strings.resources.companies'),              'route' => route('filament.dashboard.resources.companies.index'),             'accent' => 'slate'],
            ['id' => 'banks',            'searchable' => false, 'heroicon' => 'heroicon-o-building-library',         'label' => __('dashboard/strings.resources.banks'),                  'route' => route('filament.dashboard.resources.banks.index'),                 'accent' => 'slate'],
            ['id' => 'currencies',       'searchable' => false, 'heroicon' => 'heroicon-o-currency-dollar',          'label' => __('dashboard/strings.resources.currencies'),             'route' => route('filament.dashboard.resources.currencies.index'),            'accent' => 'slate'],
            ['id' => 'statuses',         'searchable' => false, 'heroicon' => 'heroicon-o-flag',                     'label' => __('dashboard/strings.resources.statuses'),               'route' => route('filament.dashboard.resources.statuses.index'),              'accent' => 'slate'],
            ['id' => 'notifications',    'searchable' => false, 'heroicon' => 'heroicon-o-bell',                     'label' => __('dashboard/strings.resources.notification_settings'),  'route' => route('filament.dashboard.resources.notification-settings.index'), 'accent' => 'slate'],
        ];
    }

    private function modules(): array
    {
        return array_map(function ($m) {
            return [
                'id' => $m['id'],
                'searchable' => $m['searchable'],
                'label' => $m['label'],
                'route' => $m['route'],
                'icon' => svg($m['heroicon'], 'w-full h-full')->toHtml(),
                'theme' => self::TONE[$m['accent']],
                'badge' => self::BADGE_TONE[$m['accent']],
            ];
        }, $this->modulesList());
    }

    private function stats(array $counts): array
    {
        return [
            'purchaseRequests' => (int) ($counts['purchase_requests'] ?? 0),
            'proformaInvoices' => (int) ($counts['proforma_invoices'] ?? 0),
            'registeredOrders' => (int) ($counts['registered_orders'] ?? 0),
            'bankProfiles' => (int) ($counts['bank_profiles'] ?? 0),
            'purchaseOrders' => (int) ($counts['purchase_orders'] ?? 0),
            'payments' => (int) ($counts['payments'] ?? 0),
            'shipments' => (int) ($counts['shipments'] ?? 0),
            'customs' => (int) ($counts['customs'] ?? 0),
        ];
    }

    public function render()
    {
        return view('livewire.landing-page.workspace');
    }
}
