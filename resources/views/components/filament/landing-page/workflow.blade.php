<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 mb-12">
    <!-- Step 1: Request & Approval -->
    <div class="card-3d">
        <div
            class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl"
            @mouseenter="hoveredStep = 1" @mouseleave="hoveredStep = null">
            <div class="absolute -top-20 -right-20 w-56 h-56 bg-blue-500 rounded-full glow-orb"></div>
            <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

            <div class="relative z-10">
                <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                    <div
                        class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <span
                        class="text-xs font-bold text-blue-400 bg-blue-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full pulse-ring whitespace-nowrap">
                        {{ __('dashboard/strings.status.active') }}
                    </span>
                </div>

                <h3 class="text-2xl sm:text-3xl font-bold mb-2"
                    :class="darkMode ? 'text-white' : 'text-slate-900'">
                    {{ __('dashboard/strings.steps.request_approval.title') }}
                </h3>
                <p :class="darkMode ? 'text-slate-400' : 'text-slate-600'"
                   class="mb-4 sm:mb-6 text-sm sm:text-base">
                    {{ __('dashboard/strings.steps.request_approval.description') }}
                </p>

                <div x-show="hoveredStep === 1"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     class="grid grid-cols-2 gap-3 mb-4 sm:mb-6">
                    <div
                        class="p-4 sm:p-5 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 rounded-xl sm:rounded-2xl border-blue-500/50 border-2 shadow-lg">
                        <div class="text-xs sm:text-sm mb-2"
                             :class="darkMode ? 'text-slate-400' : 'text-slate-600'">
                            {{ __('dashboard/strings.steps.request_approval.pending_requests') }}
                        </div>
                        <div class="text-2xl sm:text-3xl font-bold text-blue-400">
                            {{ $stats['purchaseRequests'] ?? 0 }}
                        </div>
                    </div>
                    <div
                        class="p-4 sm:p-5 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 rounded-xl sm:rounded-2xl border-cyan-500/50 border-2 shadow-lg">
                        <div class="text-xs sm:text-sm mb-2"
                             :class="darkMode ? 'text-slate-400' : 'text-slate-600'">
                            {{ __('dashboard/strings.proforma') }}
                        </div>
                        <div class="text-2xl sm:text-3xl font-bold text-cyan-400">
                            {{ $stats['proformaInvoices'] ?? 0 }}
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('filament.dashboard.resources.purchase-requests.index') }}"
                       target="_blank" rel="noopener noreferrer"
                       class="flex-1 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-500 hover:to-blue-600 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold transition-all duration-500 ease-out hover:scale-105 hover:shadow-2xl text-center text-white text-sm sm:text-base">
                        {{ __('dashboard/strings.view_requests') }}
                    </a>
                    <a href="{{ route('filament.dashboard.resources.proforma-invoices.index') }}"
                       target="_blank" rel="noopener noreferrer"
                       class="flex-1 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 border-cyan-500/50 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold transition-all duration-500 ease-out hover:scale-105 text-center text-sm sm:text-base"
                       :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.proforma') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: Order Processing -->
    <div class="card-3d">
        <div
            class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl"
            @mouseenter="hoveredStep = 2" @mouseleave="hoveredStep = null">
            <div
                class="absolute top-0 right-0 w-32 h-32 sm:w-40 sm:h-40 bg-green-500 rounded-full glow-orb"></div>
            <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

            <div class="relative z-10">
                <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                    <div
                        class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-green-500 to-green-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0"
                        style="animation-delay: 1s;">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <span
                        class="text-xs font-bold text-green-400 bg-green-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full pulse-ring whitespace-nowrap">
                        {{ __('dashboard/strings.status.active') }}
                    </span>
                </div>

                <h3 class="text-2xl sm:text-3xl font-bold mb-2"
                    :class="darkMode ? 'text-white' : 'text-slate-900'">
                    {{ __('dashboard/strings.steps.order_processing.title') }}
                </h3>
                <p :class="darkMode ? 'text-slate-400' : 'text-slate-600'"
                   class="mb-4 sm:mb-6 text-sm sm:text-base">
                    {{ __('dashboard/strings.steps.order_processing.description') }}
                </p>

                <div x-show="hoveredStep === 2"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     class="grid grid-cols-2 gap-3 mb-4 sm:mb-6">
                    <div
                        class="p-4 sm:p-5 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 rounded-xl sm:rounded-2xl border-green-500/50 border-2 shadow-lg">
                        <div class="text-xs sm:text-sm mb-2"
                             :class="darkMode ? 'text-slate-400' : 'text-slate-600'">
                            {{ __('dashboard/strings.steps.order_processing.active_orders') }}
                        </div>
                        <div class="text-2xl sm:text-3xl font-bold text-green-400">
                            {{ $stats['registeredOrders'] ?? 0 }}
                        </div>
                    </div>
                    <div
                        class="p-4 sm:p-5 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 rounded-xl sm:rounded-2xl border-emerald-500/50 border-2 shadow-lg">
                        <div class="text-xs sm:text-sm mb-2"
                             :class="darkMode ? 'text-slate-400' : 'text-slate-600'">
                            {{ __('dashboard/strings.banks') }}
                        </div>
                        <div class="text-2xl sm:text-3xl font-bold text-emerald-400">
                            {{ $stats['bankProfiles'] ?? 0 }}
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('filament.dashboard.resources.registered-orders.index')}}" target="_blank"
                       rel="noopener noreferrer"
                       class="flex-1 bg-gradient-to-r from-green-600 to-green-700 hover:from-green-500 hover:to-green-600 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold transition-all duration-500 ease-out hover:scale-105 hover:shadow-2xl text-center text-white text-sm sm:text-base">
                        {{ __('dashboard/strings.view_orders') }}
                    </a>
                    <a href="{{ route('filament.dashboard.resources.bank-profiles.index') }}" target="_blank"
                       rel="noopener noreferrer"
                       class="flex-1 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 border-emerald-500/50 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold transition-all duration-500 ease-out hover:scale-105 text-center text-sm sm:text-base"
                       :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.banks') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 3: Procurement & Payment -->
    <div class="card-3d">
        <div
            class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl"
            @mouseenter="hoveredStep = 3" @mouseleave="hoveredStep = null">
            <div
                class="absolute top-0 right-0 w-32 h-32 sm:w-40 sm:h-40 bg-amber-500 rounded-full glow-orb"></div>
            <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

            <div class="relative z-10">
                <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                    <div
                        class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-amber-600 to-orange-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0"
                        style="animation-delay: 2s;">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <span
                        class="text-xs font-bold text-amber-400 bg-amber-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full pulse-ring whitespace-nowrap">
                        {{ __('dashboard/strings.status.active') }}
                    </span>
                </div>

                <h3 class="text-2xl sm:text-3xl font-bold mb-2"
                    :class="darkMode ? 'text-white' : 'text-slate-900'">
                    {{ __('dashboard/strings.steps.procurement_payment.title') }}
                </h3>
                <p :class="darkMode ? 'text-slate-400' : 'text-slate-600'"
                   class="mb-4 sm:mb-6 text-sm sm:text-base">
                    {{ __('dashboard/strings.steps.procurement_payment.description') }}
                </p>

                <div x-show="hoveredStep === 3"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     class="grid grid-cols-2 gap-3 mb-4 sm:mb-6">
                    <div
                        class="p-4 sm:p-5 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 rounded-xl sm:rounded-2xl border-amber-500/50 border-2 shadow-lg">
                        <div class="text-xs sm:text-sm mb-2"
                             :class="darkMode ? 'text-slate-400' : 'text-slate-600'">
                            {{ __('dashboard/strings.view_orders') }}
                        </div>
                        <div class="text-2xl sm:text-3xl font-bold text-amber-400">
                            {{ $stats['purchaseOrders'] ?? 0 }}
                        </div>
                    </div>
                    <div
                        class="p-4 sm:p-5 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 rounded-xl sm:rounded-2xl border-orange-500/50 border-2 shadow-lg">
                        <div class="text-xs sm:text-sm mb-2"
                             :class="darkMode ? 'text-slate-400' : 'text-slate-600'">
                            {{ __('dashboard/strings.payments') }}
                        </div>
                        <div class="text-2xl sm:text-3xl font-bold text-orange-400">
                            {{ $stats['payments'] ?? 0 }}
                        </div>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('filament.dashboard.resources.purchase-orders.index') }}" target="_blank"
                       rel="noopener noreferrer"
                       class="flex-1 bg-gradient-to-r from-amber-600 to-orange-700 hover:from-amber-500 hover:to-orange-600 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold transition-all duration-500 ease-out hover:scale-105 hover:shadow-2xl text-center text-white text-sm sm:text-base">
                        {{ __('dashboard/strings.view_orders') }}
                    </a>
                    <a href="{{ route('filament.dashboard.resources.payments.index') }}" target="_blank"
                       rel="noopener noreferrer"
                       class="flex-1 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 border-orange-500/50 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold transition-all duration-500 ease-out hover:scale-105 text-center text-sm sm:text-base"
                       :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.payments') }}
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 4: Logistics & Clearance -->
    <div class="card-3d">
        <div
            class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl hover:opacity-100 transition-opacity duration-500"
            @click="showSubmodules = !showSubmodules">
            <div
                class="absolute top-0 right-0 w-32 h-32 sm:w-40 sm:h-40 bg-purple-500/50 rounded-full glow-orb"></div>

            <div class="relative z-10">
                <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                    <div
                        class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-purple-500/70 to-purple-700/70 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0"
                        style="animation-delay: 3s;">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                        </svg>
                    </div>
                    <span
                        class="text-xs font-bold text-purple-400 bg-purple-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full animate-pulse whitespace-nowrap">
                        {{ __('dashboard/strings.coming_soon') }}
                    </span>
                </div>

                <h3 class="text-2xl sm:text-3xl font-bold mb-2"
                    :class="darkMode ? 'text-white' : 'text-slate-900'">
                    {{ __('dashboard/strings.steps.logistics.title') }}
                </h3>
                <p :class="darkMode ? 'text-slate-400' : 'text-slate-600'"
                   class="mb-4 sm:mb-6 text-sm sm:text-base">
                    {{ __('dashboard/strings.steps.logistics.description') }}
                </p>

                <div x-show="showSubmodules"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform scale-95"
                     x-transition:enter-end="opacity-100 transform scale-100"
                     class="space-y-3 mb-4 sm:mb-6">
                    <div
                        class="p-3 sm:p-4 border border-white/10 rounded-xl sm:rounded-2xl border-purple-500/30 border hover:border-purple-500/60 transition-all cursor-pointer hover:scale-105">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-500/30 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-400" fill="none"
                                     stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-semibold text-sm sm:text-base"
                                    :class="darkMode ? 'text-white' : 'text-slate-900'">
                                    {{ __('dashboard/strings.submodules.shipment.title') }}
                                </h4>
                                <p class="text-xs truncate"
                                   :class="darkMode ? 'text-slate-400' : 'text-slate-600'">
                                    {{ __('dashboard/strings.submodules.shipment.description') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="p-3 sm:p-4 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 rounded-xl sm:rounded-2xl border-purple-500/30 border hover:border-purple-500/60 transition-all cursor-pointer hover:scale-105">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-500/30 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-400" fill="none"
                                     stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-semibold text-sm sm:text-base"
                                    :class="darkMode ? 'text-white' : 'text-slate-900'">
                                    {{ __('dashboard/strings.submodules.logistics.title') }}
                                </h4>
                                <p class="text-xs truncate"
                                   :class="darkMode ? 'text-slate-400' : 'text-slate-600'">
                                    {{ __('dashboard/strings.submodules.logistics.description') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="p-3 sm:p-4 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 rounded-xl sm:rounded-2xl border-purple-500/30 border hover:border-purple-500/60 transition-all cursor-pointer hover:scale-105">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 sm:w-12 sm:h-12 bg-purple-500/30 rounded-lg sm:rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-400" fill="none"
                                     stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-semibold text-sm sm:text-base"
                                    :class="darkMode ? 'text-white' : 'text-slate-900'">
                                    {{ __('dashboard/strings.submodules.custom_clearance.title') }}
                                </h4>
                                <p class="text-xs truncate"
                                   :class="darkMode ? 'text-slate-400' : 'text-slate-600'">
                                    {{ __('dashboard/strings.submodules.custom_clearance.description') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <button
                    class="w-full backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 border-purple-500/50 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold transition-all duration-500 ease-out hover:scale-105 text-sm sm:text-base"
                    :class="darkMode ? 'text-white' : 'text-slate-900'">
                    <span x-show="!showSubmodules">{{ __('dashboard/strings.show_submodules') }}</span>
                    <span x-show="showSubmodules">{{ __('dashboard/strings.hide_submodules') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
