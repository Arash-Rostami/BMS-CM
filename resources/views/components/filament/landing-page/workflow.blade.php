<div class="relative mt-8 mb-16">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 lg:gap-14 relative z-10">

        <div class="card-3d workflow-step relative order-1 lg:order-1">
            <span class="workflow-node absolute top-2 {{ $isRtl ? 'right-2' : 'left-2' }} z-40 w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500 to-cyan-500 flex items-center justify-center text-white text-[11px] font-black shadow-lg shadow-indigo-500/40 border border-white/20 select-none">1</span>

            <div class="hidden lg:flex absolute top-1/2 {{ $isRtl ? 'right-[calc(100%+28px)] translate-x-1/2' : 'left-[calc(100%+28px)] -translate-x-1/2' }} -translate-y-1/2 w-24 h-12 bg-white/70 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 rounded-full shadow-[0_0_30px_rgba(59,130,246,0.15)] items-center justify-center z-50">
                <div class="absolute inset-1.5 rounded-full bg-slate-100 dark:bg-slate-900 overflow-hidden shadow-inner flex items-center">
                    <div class="h-full w-1/2 bg-gradient-to-r from-transparent via-blue-500 to-transparent opacity-60 blur-sm {{ $isRtl ? 'animate-[slide-left_2s_ease-in-out_infinite]' : 'animate-[slide-right_2s_ease-in-out_infinite]' }}"></div>
                </div>
                <div class="relative z-10 w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-green-500 flex items-center justify-center shadow-md ring-4 ring-white dark:ring-slate-800">
                    <x-heroicon-o-chevron-right class="w-4 h-4 text-white {{ $isRtl ? 'rotate-180' : '' }}" stroke-width="3"/>
                </div>
            </div>

            <div class="flex lg:hidden absolute top-[calc(100%+20px)] left-1/2 -translate-x-1/2 -translate-y-1/2 w-10 h-20 bg-white/70 dark:bg-slate-800/80 backdrop-blur-xl border border-slate-200 dark:border-slate-700 rounded-full shadow-[0_0_30px_rgba(59,130,246,0.15)] flex-col items-center justify-center z-50">
                <div class="absolute inset-1.5 rounded-full bg-slate-100 dark:bg-slate-900 overflow-hidden shadow-inner flex flex-col items-center">
                    <div class="w-full h-1/2 bg-gradient-to-b from-transparent via-blue-500 to-transparent opacity-60 blur-sm animate-[slide-down_2s_ease-in-out_infinite]"></div>
                </div>
                <div class="relative z-10 w-7 h-7 rounded-full bg-gradient-to-br from-blue-500 to-green-500 flex items-center justify-center shadow-md ring-4 ring-white dark:ring-slate-800">
                    <x-heroicon-o-chevron-down class="w-4 h-4 text-white" stroke-width="3"/>
                </div>
            </div>

            <div class="glass border border-slate-200 dark:border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl bg-white/70 dark:bg-slate-900/40">
                <div class="absolute -top-20 -right-20 w-56 h-56 bg-blue-500 rounded-full glow-orb opacity-20 dark:opacity-100"></div>
                <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

                <div class="relative z-10">
                    <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-500/10 dark:bg-blue-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full whitespace-nowrap border border-blue-500/20">
                            {{ __('dashboard/strings.status.active') }}
                        </span>
                    </div>

                    <h3 class="text-2xl sm:text-3xl font-bold mb-2" :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.steps.request_approval.title') }}
                    </h3>
                    <p :class="darkMode ? 'text-slate-400' : 'text-slate-600'" class="mb-6 sm:mb-8 text-sm sm:text-base">
                        {{ __('dashboard/strings.steps.request_approval.description') }}
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="btn-wrapper flex-1 relative">
                            <a href="{{ route('filament.dashboard.resources.purchase-requests.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full bg-gradient-to-r from-blue-600 to-blue-700 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-white text-sm sm:text-base border border-blue-700">
                                <x-heroicon-o-shopping-cart class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.view_requests') }}
                            </a>
                            <span class="badge-float bg-blue-500 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg absolute -top-3 -right-3 z-20">
                                {{ $stats['purchaseRequests'] ?? 0 }}
                            </span>
                        </div>
                        <div class="btn-wrapper flex-1 relative">
                            <a href="{{ route('filament.dashboard.resources.proforma-invoices.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full backdrop-blur-[20px] backdrop-saturate-[180%] bg-white/50 dark:bg-white/5 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-sm sm:text-base border-cyan-500/50"
                               :class="darkMode ? 'text-white' : 'text-slate-900'">
                                <x-heroicon-o-document-text class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.proforma') }}
                            </a>
                            <span class="badge-float bg-cyan-500 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg absolute -top-3 -right-3 z-20">
                                {{ $stats['proformaInvoices'] ?? 0 }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-3d workflow-step relative order-2 lg:order-2">
            <span class="workflow-node absolute top-2 {{ $isRtl ? 'right-2' : 'left-2' }} z-40 w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500 to-cyan-500 flex items-center justify-center text-white text-[11px] font-black shadow-lg shadow-indigo-500/40 border border-white/20 select-none">2</span>

            <div class="absolute top-[calc(100%+20px)] lg:top-[calc(100%+28px)] left-1/2 -translate-x-1/2 -translate-y-1/2 w-10 lg:w-12 h-20 lg:h-24 bg-white/70 dark:bg-slate-800/80 backdrop-blur-xl border border-slate-200 dark:border-slate-700 rounded-full shadow-[0_0_30px_rgba(34,197,94,0.15)] flex flex-col items-center justify-center z-50">
                <div class="absolute inset-1.5 rounded-full bg-slate-100 dark:bg-slate-900 overflow-hidden shadow-inner flex flex-col items-center">
                    <div class="w-full h-1/2 bg-gradient-to-b from-transparent via-green-500 to-transparent opacity-60 blur-sm animate-[slide-down_2s_ease-in-out_infinite]"></div>
                </div>
                <div class="relative z-10 w-7 h-7 lg:w-8 lg:h-8 rounded-full bg-gradient-to-br from-green-500 to-amber-500 flex items-center justify-center shadow-md ring-4 ring-white dark:ring-slate-800">
                    <x-heroicon-o-chevron-down class="w-4 h-4 text-white" stroke-width="3"/>
                </div>
            </div>

            <div class="glass border border-slate-200 dark:border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl bg-white/70 dark:bg-slate-900/40">
                <div class="absolute top-0 right-0 w-32 h-32 sm:w-40 sm:h-40 bg-green-500 rounded-full glow-orb opacity-20 dark:opacity-100"></div>
                <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

                <div class="relative z-10">
                    <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-green-500 to-green-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0" style="animation-delay: 1s;">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-green-600 dark:text-green-400 bg-green-500/10 dark:bg-green-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full whitespace-nowrap border border-green-500/20">
                            {{ __('dashboard/strings.status.active') }}
                        </span>
                    </div>

                    <h3 class="text-2xl sm:text-3xl font-bold mb-2" :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.steps.order_processing.title') }}
                    </h3>
                    <p :class="darkMode ? 'text-slate-400' : 'text-slate-600'" class="mb-6 sm:mb-8 text-sm sm:text-base">
                        {{ __('dashboard/strings.steps.order_processing.description') }}
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="btn-wrapper flex-1 relative">
                            <a href="{{ route('filament.dashboard.resources.registered-orders.index')}}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full bg-gradient-to-r from-green-600 to-green-700 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-white text-sm sm:text-base border border-green-700">
                                <x-heroicon-o-document-check class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.view_orders') }}
                            </a>
                            <span class="badge-float bg-green-500 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg absolute -top-3 -right-3 z-20">
                                {{ $stats['registeredOrders'] ?? 0 }}
                            </span>
                        </div>
                        <div class="btn-wrapper flex-1 relative">
                            <a href="{{ route('filament.dashboard.resources.bank-profiles.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full backdrop-blur-[20px] backdrop-saturate-[180%] bg-white/50 dark:bg-white/5 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-sm sm:text-base border-emerald-500/50"
                               :class="darkMode ? 'text-white' : 'text-slate-900'">
                                <x-heroicon-o-building-office class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.banks') }}
                            </a>
                            <span class="badge-float bg-emerald-500 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg absolute -top-3 -right-3 z-20">
                                {{ $stats['bankProfiles'] ?? 0 }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-3d workflow-step relative order-3 lg:order-4">
            <span class="workflow-node absolute top-2 {{ $isRtl ? 'right-2' : 'left-2' }} z-40 w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500 to-cyan-500 flex items-center justify-center text-white text-[11px] font-black shadow-lg shadow-indigo-500/40 border border-white/20 select-none">3</span>

            <div class="hidden lg:flex absolute top-1/2 {{ $isRtl ? 'left-[calc(100%+28px)] -translate-x-1/2' : 'right-[calc(100%+28px)] translate-x-1/2' }} -translate-y-1/2 w-24 h-12 bg-white/70 dark:bg-slate-800/80 backdrop-blur-xl border border-slate-200 dark:border-slate-700 rounded-full shadow-[0_0_30px_rgba(245,158,11,0.15)] items-center justify-center z-50">
                <div class="absolute inset-1.5 rounded-full bg-slate-100 dark:bg-slate-900 overflow-hidden shadow-inner flex items-center">
                    <div class="h-full w-1/2 bg-gradient-to-r from-transparent via-amber-500 to-transparent opacity-60 blur-sm {{ $isRtl ? 'animate-[slide-right_2s_ease-in-out_infinite]' : 'animate-[slide-left_2s_ease-in-out_infinite]' }}"></div>
                </div>
                <div class="relative z-10 w-8 h-8 rounded-full bg-gradient-to-br from-amber-500 to-purple-500 flex items-center justify-center shadow-md ring-4 ring-white dark:ring-slate-800">
                    <x-heroicon-o-chevron-left class="w-4 h-4 text-white {{ $isRtl ? 'rotate-180' : '' }}" stroke-width="3"/>
                </div>
            </div>

            <div class="flex lg:hidden absolute top-[calc(100%+20px)] left-1/2 -translate-x-1/2 -translate-y-1/2 w-10 h-20 bg-white/70 dark:bg-slate-800/80 backdrop-blur-xl border border-slate-200 dark:border-slate-700 rounded-full shadow-[0_0_30px_rgba(245,158,11,0.15)] flex-col items-center justify-center z-50">
                <div class="absolute inset-1.5 rounded-full bg-slate-100 dark:bg-slate-900 overflow-hidden shadow-inner flex flex-col items-center">
                    <div class="w-full h-1/2 bg-gradient-to-b from-transparent via-amber-500 to-transparent opacity-60 blur-sm animate-[slide-down_2s_ease-in-out_infinite]"></div>
                </div>
                <div class="relative z-10 w-7 h-7 rounded-full bg-gradient-to-br from-amber-500 to-purple-500 flex items-center justify-center shadow-md ring-4 ring-white dark:ring-slate-800">
                    <x-heroicon-o-chevron-down class="w-4 h-4 text-white" stroke-width="3"/>
                </div>
            </div>

            <div class="glass border border-slate-200 dark:border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl bg-white/70 dark:bg-slate-900/40">
                <div class="absolute top-0 right-0 w-32 h-32 sm:w-40 sm:h-40 bg-amber-500 rounded-full glow-orb opacity-20 dark:opacity-100"></div>
                <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

                <div class="relative z-10">
                    <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-amber-600 to-orange-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0" style="animation-delay: 2s;">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-amber-600 dark:text-amber-400 bg-amber-500/10 dark:bg-amber-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full whitespace-nowrap border border-amber-500/20">
                            {{ __('dashboard/strings.status.active') }}
                        </span>
                    </div>

                    <h3 class="text-2xl sm:text-3xl font-bold mb-2" :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.steps.procurement_payment.title') }}
                    </h3>
                    <p :class="darkMode ? 'text-slate-400' : 'text-slate-600'" class="mb-6 sm:mb-8 text-sm sm:text-base">
                        {{ __('dashboard/strings.steps.procurement_payment.description') }}
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="btn-wrapper flex-1 relative">
                            <a href="{{ route('filament.dashboard.resources.purchase-orders.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full bg-gradient-to-r from-amber-600 to-orange-700 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-white text-sm sm:text-base border border-orange-700">
                                <x-heroicon-o-shopping-bag class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.purchase_orders') }}
                            </a>
                            <span class="badge-float bg-amber-500 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg absolute -top-3 -right-3 z-20">
                                {{ $stats['purchaseOrders'] ?? 0 }}
                            </span>
                        </div>
                        <div class="btn-wrapper flex-1 relative">
                            <a href="{{ route('filament.dashboard.resources.payments.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full backdrop-blur-[20px] backdrop-saturate-[180%] bg-white/50 dark:bg-white/5 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-sm sm:text-base border-orange-500/50"
                               :class="darkMode ? 'text-white' : 'text-slate-900'">
                                <x-heroicon-o-banknotes class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.payments') }}
                            </a>
                            <span class="badge-float bg-orange-500 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg absolute -top-3 -right-3 z-20">
                                {{ $stats['payments'] ?? 0 }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-3d workflow-step relative order-4 lg:order-3">
            <span class="workflow-node absolute top-2 {{ $isRtl ? 'right-2' : 'left-2' }} z-40 w-7 h-7 rounded-full bg-gradient-to-br from-indigo-500 to-cyan-500 flex items-center justify-center text-white text-[11px] font-black shadow-lg shadow-indigo-500/40 border border-white/20 select-none">4</span>

            <div class="glass border border-slate-200 dark:border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl bg-white/70 dark:bg-slate-900/40">
                <div class="absolute top-0 right-0 w-32 h-32 sm:w-40 sm:h-40 bg-purple-500 rounded-full glow-orb opacity-20 dark:opacity-100"></div>
                <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

                <div class="relative z-10">
                    <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0" style="animation-delay: 3s;">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-purple-600 dark:text-purple-400 bg-purple-500/10 dark:bg-purple-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full whitespace-nowrap border border-purple-500/20">
                            {{ __('dashboard/strings.status.active') }}
                        </span>
                    </div>

                    <h3 class="text-2xl sm:text-3xl font-bold mb-2" :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.steps.logistics.title') }}
                    </h3>
                    <p :class="darkMode ? 'text-slate-400' : 'text-slate-600'" class="mb-6 sm:mb-8 text-sm sm:text-base">
                        {{ __('dashboard/strings.steps.logistics.description') }}
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <div class="btn-wrapper flex-1 relative">
                            <a href="{{ route('filament.dashboard.resources.shipments.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full bg-gradient-to-r from-purple-600 to-purple-700 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-white text-sm sm:text-base border border-purple-700">
                                <x-heroicon-o-truck class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.submodules.shipment.title') }}
                            </a>
                            <span class="badge-float bg-purple-500 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg absolute -top-3 -right-3 z-20">
                                {{ $stats['shipments'] ?? 0 }}
                            </span>
                        </div>
                        <div class="btn-wrapper flex-1 relative">
                            <a href="{{ route('filament.dashboard.resources.customs.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full backdrop-blur-[20px] backdrop-saturate-[180%] bg-white/50 dark:bg-white/5 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-sm sm:text-base border-violet-500/50"
                               :class="darkMode ? 'text-white' : 'text-slate-900'">
                                <x-heroicon-o-clipboard-document-check class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.submodules.custom_clearance.title') }}
                            </a>
                            <span class="badge-float bg-violet-500 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg absolute -top-3 -right-3 z-20">
                                {{ $stats['customs'] ?? 0 }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
