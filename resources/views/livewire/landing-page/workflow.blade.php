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
            'group' => 'request_approval',
            'title' => __('dashboard/strings.steps.request_approval.title'),
            'description' => __('dashboard/strings.steps.request_approval.description'),
            'links' => [
                ['route' => route('filament.dashboard.resources.purchase-requests.index'), 'icon' => 'heroicon-o-shopping-cart', 'label' => __('dashboard/strings.view_requests'), 'count' => $stats['purchaseRequests'] ?? 0],
                ['route' => route('filament.dashboard.resources.proforma-invoices.index'), 'icon' => 'heroicon-o-document-text', 'label' => __('dashboard/strings.proforma'), 'count' => $stats['proformaInvoices'] ?? 0],
            ],
        ],
        [
            'accent' => 'green',
            'group' => 'order_processing',
            'title' => __('dashboard/strings.steps.order_processing.title'),
            'description' => __('dashboard/strings.steps.order_processing.description'),
            'links' => [
                ['route' => route('filament.dashboard.resources.registered-orders.index'), 'icon' => 'heroicon-o-document-check', 'label' => __('dashboard/strings.view_orders'), 'count' => $stats['registeredOrders'] ?? 0],
                ['route' => route('filament.dashboard.resources.bank-profiles.index'), 'icon' => 'heroicon-o-building-office', 'label' => __('dashboard/strings.banks'), 'count' => $stats['bankProfiles'] ?? 0],
            ],
        ],
        [
            'accent' => 'yellow',
            'group' => 'procurement_payment',
            'title' => __('dashboard/strings.steps.procurement_payment.title'),
            'description' => __('dashboard/strings.steps.procurement_payment.description'),
            'links' => [
                ['route' => route('filament.dashboard.resources.purchase-orders.index'), 'icon' => 'heroicon-o-shopping-bag', 'label' => __('dashboard/strings.purchase_orders'), 'count' => $stats['purchaseOrders'] ?? 0],
                ['route' => route('filament.dashboard.resources.payments.index'), 'icon' => 'heroicon-o-banknotes', 'label' => __('dashboard/strings.payments'), 'count' => $stats['payments'] ?? 0],
            ],
        ],
        [
            'accent' => 'red',
            'group' => 'logistics',
            'title' => __('dashboard/strings.steps.logistics.title'),
            'description' => __('dashboard/strings.steps.logistics.description'),
            'links' => [
                ['route' => route('filament.dashboard.resources.shipments.index'), 'icon' => 'heroicon-o-truck', 'label' => __('dashboard/strings.submodules.shipment.title'), 'count' => $stats['shipments'] ?? 0],
                ['route' => route('filament.dashboard.resources.customs.index'), 'icon' => 'heroicon-o-clipboard-document-check', 'label' => __('dashboard/strings.submodules.custom_clearance.title'), 'count' => $stats['customs'] ?? 0],
            ],
        ],
    ];

    $insightByGroup = collect($insightGroups ?? [])->keyBy('key');
@endphp

<div x-data="workflow(@js($insightGroups ?? []))">
    <div class="mt-8 mb-8">
        <div class="flex flex-col lg:flex-row lg:items-stretch gap-4 lg:gap-0">
            @foreach($steps as $i => $step)
                @php($insight = ($insightByGroup ?? [])[$step['group']] ?? null)
                <div class="flex-1 flex flex-col lg:flex-row lg:items-stretch">
                    <div class="flex-1 lp-surface p-5 flex flex-col">
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

                        @if($insight)
                            <div class="mt-auto pt-3 border-t lp-divider">
                                <div class="lp-insight-flag">
                                    <x-heroicon-s-light-bulb class="w-3 h-3"/>
                                </div>

                                <div class="grid grid-cols-2 gap-1.5">
                                    @if(!empty($insight['audio']))
                                        <button type="button" @click="playPause(@js($insight))"
                                                class="lp-tab flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border lp-divider"
                                                :class="(playing && selected?.key === @js($insight['key'])) ? 'lp-tab-active' : ''">
                                            <x-heroicon-o-speaker-wave class="w-3.5 h-3.5"/>
                                            <span x-text="(playing && selected?.key === @js($insight['key'])) ? '{{ __('dashboard/strings.insights_pause') }}' : '{{ __('dashboard/strings.insights_listen') }}'"></span>
                                        </button>
                                    @endif
                                    @if(!empty($insight['video']))
                                        <button type="button" @click="openVideo(@js($insight))"
                                                class="lp-tab flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border lp-divider">
                                            <x-heroicon-o-film class="w-3.5 h-3.5"/>
                                            <span>{{ __('dashboard/strings.insights_watch') }}</span>
                                        </button>
                                    @endif
                                    @if(!empty($insight['poster']))
                                        <a href="{{ $insight['poster'] }}" target="_blank" rel="noopener noreferrer"
                                           class="lp-tab flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border lp-divider">
                                            <x-heroicon-o-photo class="w-3.5 h-3.5"/>
                                            <span>{{ __('dashboard/strings.insights_poster') }}</span>
                                        </a>
                                    @endif
                                    @if(!empty($insight['terms']) || !empty($insight['process']) || !empty($insight['dos']) || !empty($insight['donts']))
                                        <button type="button" @click="openText(@js($insight))"
                                                class="lp-tab flex items-center justify-center gap-1.5 px-2.5 py-1.5 text-xs rounded-md border lp-divider">
                                            <x-heroicon-o-book-open class="w-3.5 h-3.5"/>
                                            <span>{{ __('dashboard/strings.insights_guide') }}</span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endif
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

    <x-modal open="textOpen">
        <x-slot:heading>
            <h2 class="fi-modal-heading" x-text="selected?.title"></h2>
        </x-slot:heading>
        <x-slot:description>
            <p class="fi-modal-description" x-show="selected?.scopeLabel" x-text="selected?.scopeLabel"></p>
        </x-slot:description>

            <template x-if="selected">
                <div class="space-y-4">
                    <template x-if="selected.terms?.length">
                        <details open class="fi-section p-3">
                            <summary class="cursor-pointer text-sm font-semibold">
                                {{ __('resources/general/strings.desk_reference.terms_heading') }}
                            </summary>
                            <div class="grid gap-2 sm:grid-cols-2 mt-3">
                                <template x-for="term in selected.terms" :key="term.term">
                                    <div class="flex items-start gap-2">
                                        <span class="tb-badge tb-info shrink-0" x-text="term.term"></span>
                                        <span class="text-sm text-slate-600 dark:text-slate-300" x-text="term.definition"></span>
                                    </div>
                                </template>
                            </div>
                        </details>
                    </template>

                    <template x-if="selected.process?.length">
                        <details class="fi-section p-3">
                            <summary class="cursor-pointer text-sm font-semibold">
                                {{ __('resources/general/strings.desk_reference.process_heading') }}
                            </summary>
                            <ol class="space-y-2 mt-3">
                                <template x-for="(step, i) in selected.process" :key="i">
                                    <li class="flex items-start gap-3">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                              style="background: var(--custom-third-light); color: var(--filament-dark);" x-text="i + 1"></span>
                                        <span class="text-sm">
                                            <strong x-text="step.title"></strong>
                                            <span class="text-slate-600 dark:text-slate-300" x-text="' — ' + step.description"></span>
                                        </span>
                                    </li>
                                </template>
                            </ol>
                        </details>
                    </template>

                    <template x-if="selected.dos?.length || selected.donts?.length">
                        <details class="fi-section p-3">
                            <summary class="cursor-pointer text-sm font-semibold">
                                {{ __('resources/general/strings.desk_reference.dos_donts_heading') }}
                            </summary>
                            <div class="grid gap-4 sm:grid-cols-2 mt-3">
                                <ul class="space-y-2">
                                    <template x-for="item in selected.dos" :key="item">
                                        <li class="flex items-start gap-2 text-sm">
                                            <span class="tb-badge tb-success shrink-0 rounded-full !px-1.5 !py-1.5">
                                                <x-filament::icon icon="heroicon-o-check-circle" class="h-3.5 w-3.5" />
                                            </span>
                                            <span x-text="item"></span>
                                        </li>
                                    </template>
                                </ul>
                                <ul class="space-y-2">
                                    <template x-for="item in selected.donts" :key="item">
                                        <li class="flex items-start gap-2 text-sm">
                                            <span class="tb-badge tb-danger shrink-0 rounded-full !px-1.5 !py-1.5">
                                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-3.5 w-3.5" />
                                            </span>
                                            <span x-text="item"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </details>
                    </template>

                    <a :href="selected.route" target="_blank" rel="noopener noreferrer"
                       class="text-xs inline-flex items-center gap-1 text-slate-600 dark:text-slate-300">
                        <span x-text="@js(__('dashboard/strings.insights_open_module')) + ' →'"></span>
                    </a>
                </div>
            </template>
    </x-modal>

    <x-modal open="videoOpen">
        <template x-if="selected?.video">
            <video controls preload="none" :poster="selected.poster" :src="selected.video" class="w-full rounded-lg"></video>
        </template>
    </x-modal>

    @include('livewire.landing-page.workflow.footer')

    <div class="lp-surface lp-ticker flex items-center gap-3 px-4 py-2.5 mt-8 mb-2"
         x-show="tips.length >= 2" x-cloak
         @mouseenter="paused = true" @mouseleave="paused = false"
         role="region" aria-label="{{ __('dashboard/strings.insights') }}">
        <div class="relative flex-1 min-w-0 h-5">
            <template x-for="(t, i) in tips" :key="i">
                <button type="button" x-show="i === rotateIdx"
                        x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                        x-transition:leave="transition-opacity duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                        @click="openText(t.group)"
                        :aria-label="t.title + ' — ' + t.tip"
                        class="absolute inset-0 flex items-center gap-2.5 text-start">
                    <span class="shrink-0 inline-flex" :class="accentText(t.accent)">
                        <x-heroicon-o-light-bulb class="w-3.5 h-3.5"/>
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400 truncate flex-1 min-w-0" x-text="t.tip"></span>
                </button>
            </template>
        </div>
        <div class="flex items-center gap-1 shrink-0 ms-2">
            <template x-for="(t, i) in tips" :key="'d'+i">
                <button type="button" @click="setTip(i)"
                        :aria-label="@js(__('dashboard/strings.insights_next_tip')) + ' ' + (i + 1)"
                        :class="i === rotateIdx ? 'w-4 h-1.5 rounded-full ' + accentBg(t.accent) : 'w-1.5 h-1.5 rounded-full bg-slate-300 dark:bg-slate-600'"></button>
            </template>
        </div>
    </div>
</div>
