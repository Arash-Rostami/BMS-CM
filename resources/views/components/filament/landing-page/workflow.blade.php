@php
    $accent = [
        'blue'  => ['border' => 'border-blue-200 dark:border-blue-500/30', 'text' => 'text-blue-700 dark:text-blue-400', 'bg' => 'bg-blue-50 dark:bg-blue-500/10'],
        'green' => ['border' => 'border-green-200 dark:border-green-500/30', 'text' => 'text-green-700 dark:text-green-400', 'bg' => 'bg-green-50 dark:bg-green-500/10'],
        'yellow'=> ['border' => 'border-yellow-200 dark:border-yellow-500/30', 'text' => 'text-yellow-800 dark:text-yellow-400', 'bg' => 'bg-yellow-50 dark:bg-yellow-500/10'],
        'red'   => ['border' => 'border-red-200 dark:border-red-500/30', 'text' => 'text-red-700 dark:text-red-400', 'bg' => 'bg-red-50 dark:bg-red-500/10'],
    ];

    $steps = [
        [
            'accent' => 'blue',
            'title' => __('dashboard/strings.steps.request_approval.title'),
            'description' => __('dashboard/strings.steps.request_approval.description'),
            'links' => [
                ['route' => route('filament.dashboard.resources.purchase-requests.index'), 'icon' => 'heroicon-o-shopping-cart', 'label' => __('dashboard/strings.view_requests'), 'count' => $stats['purchaseRequests'] ?? 0],
                ['route' => route('filament.dashboard.resources.proforma-invoices.index'), 'icon' => 'heroicon-o-document-text', 'label' => __('dashboard/strings.proforma'), 'count' => $stats['proformaInvoices'] ?? 0],
            ],
        ],
        [
            'accent' => 'green',
            'title' => __('dashboard/strings.steps.order_processing.title'),
            'description' => __('dashboard/strings.steps.order_processing.description'),
            'links' => [
                ['route' => route('filament.dashboard.resources.registered-orders.index'), 'icon' => 'heroicon-o-document-check', 'label' => __('dashboard/strings.view_orders'), 'count' => $stats['registeredOrders'] ?? 0],
                ['route' => route('filament.dashboard.resources.bank-profiles.index'), 'icon' => 'heroicon-o-building-office', 'label' => __('dashboard/strings.banks'), 'count' => $stats['bankProfiles'] ?? 0],
            ],
        ],
        [
            'accent' => 'yellow',
            'title' => __('dashboard/strings.steps.procurement_payment.title'),
            'description' => __('dashboard/strings.steps.procurement_payment.description'),
            'links' => [
                ['route' => route('filament.dashboard.resources.purchase-orders.index'), 'icon' => 'heroicon-o-shopping-bag', 'label' => __('dashboard/strings.purchase_orders'), 'count' => $stats['purchaseOrders'] ?? 0],
                ['route' => route('filament.dashboard.resources.payments.index'), 'icon' => 'heroicon-o-banknotes', 'label' => __('dashboard/strings.payments'), 'count' => $stats['payments'] ?? 0],
            ],
        ],
        [
            'accent' => 'red',
            'title' => __('dashboard/strings.steps.logistics.title'),
            'description' => __('dashboard/strings.steps.logistics.description'),
            'links' => [
                ['route' => route('filament.dashboard.resources.shipments.index'), 'icon' => 'heroicon-o-truck', 'label' => __('dashboard/strings.submodules.shipment.title'), 'count' => $stats['shipments'] ?? 0],
                ['route' => route('filament.dashboard.resources.customs.index'), 'icon' => 'heroicon-o-clipboard-document-check', 'label' => __('dashboard/strings.submodules.custom_clearance.title'), 'count' => $stats['customs'] ?? 0],
            ],
        ],
    ];
@endphp

<div class="mt-8 mb-16">
    <div class="flex flex-col lg:flex-row lg:items-stretch gap-4 lg:gap-0">
        @foreach($steps as $i => $step)
            <div class="flex-1 flex flex-col lg:flex-row lg:items-stretch">
                <div class="flex-1 lp-surface p-5">
                    <div class="flex items-center gap-2.5 mb-3">
                        <span class="w-6 h-6 rounded-full border flex items-center justify-center text-xs font-bold flex-shrink-0 {{ $accent[$step['accent']]['border'] }} {{ $accent[$step['accent']]['text'] }}">
                            {{ $i + 1 }}
                        </span>
                        <h3 class="text-sm font-semibold truncate" :class="darkMode ? 'text-white' : 'text-slate-900'">
                            {{ $step['title'] }}
                        </h3>
                    </div>
                    <p class="text-xs mb-4 leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ $step['description'] }}
                    </p>

                    <div class="space-y-1.5">
                        @foreach($step['links'] as $link)
                            <a href="{{ $link['route'] }}" target="_blank" rel="noopener noreferrer"
                               class="lp-surface-hover flex items-center justify-between gap-2 rounded-md px-2.5 py-2 border lp-divider transition-colors duration-150">
                                <span class="flex items-center gap-2 min-w-0 text-xs font-medium text-slate-700 dark:text-slate-200">
                                    {!! svg($link['icon'], 'w-4 h-4 flex-shrink-0 ' . $accent[$step['accent']]['text'])->toHtml() !!}
                                    <span class="truncate">{{ $link['label'] }}</span>
                                </span>
                                <span class="tabular-nums text-[11px] font-bold px-1.5 py-0.5 rounded flex-shrink-0 {{ $accent[$step['accent']]['bg'] }} {{ $accent[$step['accent']]['text'] }}">
                                    {{ $link['count'] }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>

                @unless($loop->last)
                    <div class="flex lg:flex-col items-center justify-center py-2 lg:py-0 lg:px-3" aria-hidden="true">
                        <div class="hidden lg:block w-4 h-px stepper-connector"></div>
                        <svg class="w-3.5 h-3.5 rotate-90 lg:rotate-0 flex-shrink-0" :class="darkMode ? 'text-slate-600' : 'text-slate-400'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="{{ $isRtl ? 'M15 19l-7-7 7-7' : 'M9 5l7 7-7 7' }}"/>
                        </svg>
                        <div class="hidden lg:block w-4 h-px stepper-connector"></div>
                    </div>
                @endunless
            </div>
        @endforeach
    </div>
</div>
