@push("headCSS")
    <style>
        .workflow-connector {
            position: absolute;
            top: 50%;
            left: -24px;
            width: 24px;
            height: 2px;
            background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.2) 50%, rgba(255,255,255,0.5) 100%);
            z-index: 0;
            display: none;
        }
        @media (min-width: 1024px) {
            .workflow-connector {
                display: block;
            }
            .workflow-step:nth-child(even) .workflow-connector {
                left: -24px;
            }
        }
        .dark .workflow-connector {
            background: linear-gradient(90deg, rgba(255,255,255,0) 0%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.2) 100%);
        }
        .bg-size-200 {
            background-size: 200% 100%;
        }
        .hover\:bg-pos-100:hover {
            background-position: 100% 0;
        }
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
@endpush

<div x-data="{
    shortcuts: [],
    customized: false,
    isEditing: false,
    isExpanded: false,
    available: [
        {id: 'purchaseRequests', type: 'major', route: '{{ route('filament.dashboard.resources.purchase-requests.index') }}', icon: 'heroicon-o-document-text', label: '{{ __('dashboard/strings.purchase_requests') ?? 'Purchase Requests' }}', color: 'blue'},
        {id: 'proformaInvoices', type: 'major', route: '{{ route('filament.dashboard.resources.proforma-invoices.index') }}', icon: 'heroicon-o-document-magnifying-glass', label: '{{ __('dashboard/strings.proforma') ?? 'Proforma Invoices' }}', color: 'indigo'},
        {id: 'registeredOrders', type: 'major', route: '{{ route('filament.dashboard.resources.registered-orders.index') }}', icon: 'heroicon-o-document-check', label: '{{ __('dashboard/strings.view_orders') ?? 'Registered Orders' }}', color: 'green'},
        {id: 'purchaseOrders', type: 'major', route: '{{ route('filament.dashboard.resources.purchase-orders.index') }}', icon: 'heroicon-o-shopping-bag', label: '{{ __('dashboard/strings.purchase_orders') ?? 'Purchase Orders' }}', color: 'amber'},
        {id: 'payments', type: 'major', route: '{{ route('filament.dashboard.resources.payments.index') }}', icon: 'heroicon-o-banknotes', label: '{{ __('dashboard/strings.payments') ?? 'Payments' }}', color: 'orange'},
        {id: 'shipments', type: 'major', route: '{{ route('filament.dashboard.resources.shipments.index') }}', icon: 'heroicon-o-truck', label: '{{ __('dashboard/strings.submodules.shipment.title') ?? 'Shipments' }}', color: 'purple'},
        {id: 'customs', type: 'major', route: '{{ route('filament.dashboard.resources.customs.index') }}', icon: 'heroicon-o-clipboard-document-check', label: '{{ __('dashboard/strings.submodules.custom_clearance.title') ?? 'Customs' }}', color: 'violet'},

        {id: 'bankProfiles', type: 'minor', route: '{{ route('filament.dashboard.resources.bank-profiles.index') }}', icon: 'heroicon-o-building-office', label: '{{ __('dashboard/strings.banks') ?? 'Bank Profiles' }}', color: 'emerald'},
        {id: 'categories', type: 'minor', route: '{{ route('filament.dashboard.resources.categories.index') }}', icon: 'heroicon-o-tag', label: '{{ __('dashboard/strings.resources.categories') ?? 'Categories' }}', color: 'slate'},
        {id: 'products', type: 'minor', route: '{{ route('filament.dashboard.resources.products.index') }}', icon: 'heroicon-o-cube', label: '{{ __('dashboard/strings.resources.products') ?? 'Products' }}', color: 'sky'},
        {id: 'companies', type: 'minor', route: '{{ route('filament.dashboard.resources.companies.index') }}', icon: 'heroicon-o-building-storefront', label: '{{ __('dashboard/strings.resources.companies') ?? 'Companies' }}', color: 'teal'},
        {id: 'banks', type: 'minor', route: '{{ route('filament.dashboard.resources.banks.index') }}', icon: 'heroicon-o-building-library', label: '{{ __('dashboard/strings.resources.banks') ?? 'Banks' }}', color: 'lime'},
        {id: 'currencies', type: 'minor', route: '{{ route('filament.dashboard.resources.currencies.index') }}', icon: 'heroicon-o-currency-dollar', label: '{{ __('dashboard/strings.resources.currencies') ?? 'Currencies' }}', color: 'emerald'},
        {id: 'statuses', type: 'minor', route: '{{ route('filament.dashboard.resources.statuses.index') }}', icon: 'heroicon-o-flag', label: '{{ __('dashboard/strings.resources.statuses') ?? 'Statuses' }}', color: 'rose'},
        {id: 'notifications', type: 'minor', route: '{{ route('filament.dashboard.resources.notification-settings.index') }}', icon: 'heroicon-o-bell', label: '{{ __('dashboard/strings.resources.notification_settings') ?? 'Notifications' }}', color: 'yellow'}
    ],
    stats: {{ json_encode($stats ?? []) }},
    selectedIds: [],
    init() {
        const saved = localStorage.getItem('user_shortcuts');
        if (saved) {
            this.shortcuts = JSON.parse(saved);
            this.customized = true;
            this.isExpanded = true;
        } else {
            this.shortcuts = [];
            this.isExpanded = false;
        }
        this.selectedIds = this.shortcuts.map(s => s.id);
    },
    toggleShortcut(module) {
        const index = this.selectedIds.indexOf(module.id);
        if (index > -1) {
            this.selectedIds.splice(index, 1);
        } else {
            this.selectedIds.push(module.id);
        }
    },
    saveShortcuts() {
        this.shortcuts = this.available.filter(m => this.selectedIds.includes(m.id));
        localStorage.setItem('user_shortcuts', JSON.stringify(this.shortcuts));
        this.customized = true;
        this.isEditing = false;
        this.isExpanded = this.shortcuts.length > 0;
    }
}" class="mb-12 relative z-20">
    <div class="glass border border-white/10 rounded-2xl sm:rounded-3xl overflow-hidden shadow-xl transition-all duration-300">
        <div @click="isExpanded = !isExpanded" class="px-6 py-5 flex items-center justify-between cursor-pointer hover:bg-white/5 transition-colors group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md group-hover:scale-105 transition-transform">
                    <x-heroicon-o-squares-2x2 class="w-6 h-6" />
                </div>
                <div>
                    <h2 class="text-lg sm:text-xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.workspace') ?? 'Your Workspace' }}
                    </h2>
                    <p class="text-xs sm:text-sm" :class="darkMode ? 'text-slate-400' : 'text-slate-500'" x-text="shortcuts.length === 0 ? '{{ __('dashboard/strings.setup_shortcuts') ?? 'Click to set up your personal shortcuts' }}' : shortcuts.length + ' ' + '{{ __('dashboard/strings.shortcuts_pinned') ?? 'shortcuts pinned' }}'"></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button @click.stop="isEditing = true" class="p-2.5 rounded-xl hover:bg-white/10 transition-colors shadow-sm border border-white/5" :class="darkMode ? 'text-cyan-400 bg-white/5' : 'text-indigo-600 bg-slate-100/50'" title="{{ __('dashboard/strings.edit_workspace') }}">
                    <x-heroicon-o-pencil-square class="w-5 h-5" />
                </button>
                <div class="p-1.5 rounded-full bg-white/5 border border-white/10" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                    <svg class="w-5 h-5 transition-transform duration-300" :class="isExpanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
        </div>

        <div x-show="isExpanded" x-collapse x-cloak>
            <div class="p-6 pt-0 border-t border-white/5 mt-2 bg-black/5 dark:bg-white/5">
                <div x-show="shortcuts.length === 0" class="text-center py-10">
                    <div class="w-20 h-20 bg-slate-500/10 rounded-full flex items-center justify-center text-slate-400 mx-auto mb-4 border border-white/5 shadow-inner">
                        <x-heroicon-o-squares-plus class="w-10 h-10" />
                    </div>
                    <h3 class="text-xl font-bold mb-2" :class="darkMode ? 'text-white' : 'text-slate-800'">{{ __('dashboard/strings.no_shortcuts') ?? 'No shortcuts configured' }}</h3>
                    <p class="text-sm mb-6 max-w-md mx-auto" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                        {{ __('dashboard/strings.no_shortcuts_hint') ?? 'Pin your most frequently used modules here for quick access across the application.' }}
                    </p>
                    <button @click="isEditing = true" class="px-6 py-3 rounded-xl font-bold text-white shadow-lg transition-transform hover:scale-105 bg-gradient-to-r from-indigo-500 to-purple-600">
                        {{ __('dashboard/strings.add_shortcut') ?? 'Add Shortcuts' }}
                    </button>
                </div>

                <div x-show="shortcuts.length > 0" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 pt-4">
                    <template x-for="item in shortcuts" :key="item.id">
                        <a :href="item.route" target="_blank" rel="noopener noreferrer" class="glass border border-white/10 rounded-2xl p-4 relative overflow-hidden group hover:-translate-y-1.5 transition-all duration-300 hover:shadow-xl bg-white/40 dark:bg-slate-800/40">
                            <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                            <div class="relative z-10 flex flex-col items-center text-center gap-3">
                                <div :class="`w-12 h-12 rounded-xl flex items-center justify-center text-white bg-gradient-to-br from-${item.color}-500 to-${item.color}-600 shadow-md group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300`">
                                    <template x-if="item.icon === 'heroicon-o-document-text'"><x-heroicon-o-document-text class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-document-magnifying-glass'"><x-heroicon-o-document-magnifying-glass class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-document-check'"><x-heroicon-o-document-check class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-building-office'"><x-heroicon-o-building-office class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-shopping-bag'"><x-heroicon-o-shopping-bag class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-banknotes'"><x-heroicon-o-banknotes class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-truck'"><x-heroicon-o-truck class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-clipboard-document-check'"><x-heroicon-o-clipboard-document-check class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-tag'"><x-heroicon-o-tag class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-cube'"><x-heroicon-o-cube class="w-6 h-6" /></template>

                                    <template x-if="item.icon === 'heroicon-o-building-library'"><x-heroicon-o-building-library class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-currency-dollar'"><x-heroicon-o-currency-dollar class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-flag'"><x-heroicon-o-flag class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-bell'"><x-heroicon-o-bell class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-tag'"><x-heroicon-o-tag class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-cube'"><x-heroicon-o-cube class="w-6 h-6" /></template>
                                    <template x-if="item.icon === 'heroicon-o-building-storefront'"><x-heroicon-o-building-storefront class="w-6 h-6" /></template>

                                </div>
                                <span class="font-bold text-xs sm:text-sm leading-tight" :class="darkMode ? 'text-slate-200 group-hover:text-white' : 'text-slate-700 group-hover:text-slate-900'" x-text="item.label"></span>
                            </div>
                            <template x-if="stats[item.id] !== undefined && stats[item.id] !== 0">
                                <span class="absolute top-2 right-2 text-white text-[10px] sm:text-xs font-bold rounded-full min-w-[20px] sm:min-w-[24px] h-5 sm:h-6 px-1 flex items-center justify-center shadow-md border border-white/20" :class="`bg-${item.color}-500`" x-text="stats[item.id]"></span>
                            </template>
                        </a>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Overlay -->
    <div x-show="isEditing" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" x-transition.opacity>
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-md" @click="isEditing = false"></div>
        <div class="glass border border-white/10 rounded-[2rem] p-6 sm:p-8 relative z-10 w-full max-w-5xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col bg-white/10 dark:bg-slate-900/50" x-transition.scale>
            <div class="flex items-center justify-between mb-8 flex-shrink-0">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-lg border border-white/10">
                        <x-heroicon-o-adjustments-horizontal class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="text-3xl font-extrabold tracking-tight" :class="darkMode ? 'text-white' : 'text-slate-900'">
                            {{ __('dashboard/strings.edit_workspace') ?? 'Customize Shortcuts' }}
                        </h3>
                        <p class="text-sm mt-1" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">{{ __('dashboard/strings.edit_workspace_hint') ?? 'Select the modules you want to pin to your quick access grid.' }}</p>
                    </div>
                </div>
                <button @click="isEditing = false" class="p-3 rounded-full hover:bg-white/10 text-slate-400 hover:text-slate-200 transition-colors bg-white/5 border border-white/10">
                    <x-heroicon-o-x-mark class="w-6 h-6" />
                </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-5 mb-6 flex-1 overflow-y-auto pr-2 custom-scrollbar pb-4">
                <template x-for="module in available" :key="module.id">
                    <button @click="toggleShortcut(module)" class="relative glass border rounded-[1.5rem] p-5 flex flex-col items-center text-center gap-4 transition-all duration-300 hover:scale-[1.02]" :class="selectedIds.includes(module.id) ? (darkMode ? 'border-indigo-500 bg-indigo-500/20 shadow-[0_0_25px_rgba(99,102,241,0.25)]' : 'border-indigo-600 bg-indigo-50 shadow-[0_0_20px_rgba(79,70,229,0.15)]') : 'border-white/10 opacity-70 hover:opacity-100 bg-white/5'">
                        <div class="absolute inset-0 rounded-[1.5rem] bg-gradient-to-b from-white/10 to-transparent opacity-0 transition-opacity" :class="selectedIds.includes(module.id) ? 'opacity-100' : 'group-hover:opacity-50'"></div>
                        <div :class="`relative z-10 w-14 h-14 rounded-2xl flex items-center justify-center text-white bg-gradient-to-br from-${module.color}-500 to-${module.color}-600 shadow-lg`">
                            <template x-if="module.icon === 'heroicon-o-document-text'"><x-heroicon-o-document-text class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-document-magnifying-glass'"><x-heroicon-o-document-magnifying-glass class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-document-check'"><x-heroicon-o-document-check class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-building-office'"><x-heroicon-o-building-office class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-shopping-bag'"><x-heroicon-o-shopping-bag class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-banknotes'"><x-heroicon-o-banknotes class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-truck'"><x-heroicon-o-truck class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-clipboard-document-check'"><x-heroicon-o-clipboard-document-check class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-tag'"><x-heroicon-o-tag class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-cube'"><x-heroicon-o-cube class="w-7 h-7" /></template>

                            <template x-if="module.icon === 'heroicon-o-building-library'"><x-heroicon-o-building-library class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-currency-dollar'"><x-heroicon-o-currency-dollar class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-flag'"><x-heroicon-o-flag class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-bell'"><x-heroicon-o-bell class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-tag'"><x-heroicon-o-tag class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-cube'"><x-heroicon-o-cube class="w-7 h-7" /></template>
                            <template x-if="module.icon === 'heroicon-o-building-storefront'"><x-heroicon-o-building-storefront class="w-7 h-7" /></template>

                        </div>
                        <span class="relative z-10 font-bold text-sm sm:text-base leading-tight" :class="darkMode ? 'text-slate-200' : 'text-slate-800'" x-text="module.label"></span>

                        <div x-show="selectedIds.includes(module.id)" class="absolute top-4 right-4 w-7 h-7 rounded-full flex items-center justify-center shadow-lg border border-white/20" :class="darkMode ? 'bg-indigo-500 text-white' : 'bg-indigo-600 text-white'" x-transition.scale.origin.bottom.right>
                            <x-heroicon-o-check class="w-4 h-4" />
                        </div>
                    </button>
                </template>
            </div>

            <div class="flex items-center justify-end pt-6 border-t border-white/10 flex-shrink-0 mt-auto">
                <button @click="saveShortcuts()" class="px-10 py-4 rounded-2xl font-bold text-white shadow-xl transition-all duration-300 hover:scale-105 hover:shadow-2xl bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-600 bg-size-200 hover:bg-pos-100 w-full sm:w-auto flex items-center justify-center gap-3 text-lg border border-white/20">
                    <x-heroicon-o-check-circle class="w-6 h-6" />
                    {{ __('dashboard/strings.save') ?? 'Save Changes' }}
                </button>
            </div>
        </div>
    </div>
</div>

<div class="relative mt-8 mb-16">
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none opacity-20">
        <svg class="w-full max-w-4xl h-full" viewBox="0 0 1000 400" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
            <path d="M 250,100 C 350,100 400,300 500,300 C 600,300 650,100 750,100" stroke="url(#gradient)" stroke-width="4" stroke-dasharray="8 8" stroke-linecap="round"/>
            <path d="M 250,300 C 350,300 400,100 500,100 C 600,100 650,300 750,300" stroke="url(#gradient)" stroke-width="4" stroke-dasharray="8 8" stroke-linecap="round"/>
            <defs>
                <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                    <stop offset="0%" stop-color="#6366f1" />
                    <stop offset="50%" stop-color="#ec4899" />
                    <stop offset="100%" stop-color="#8b5cf6" />
                </linearGradient>
            </defs>
        </svg>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 relative z-10">

        <!-- Step 1: Request & Approval -->
        <div class="card-3d workflow-step relative">
            <div class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl">
                <div class="absolute -top-20 -right-20 w-56 h-56 bg-blue-500 rounded-full glow-orb"></div>
                <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

                <div class="relative z-10">
                    <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-blue-400 bg-blue-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full   whitespace-nowrap">
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
                        <div class="btn-wrapper flex-1">
                            <a href="{{ route('filament.dashboard.resources.purchase-requests.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full bg-gradient-to-r from-blue-600 to-blue-700 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-white text-sm sm:text-base">
                                <x-heroicon-o-shopping-cart class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.view_requests') }}
                            </a>
                            <span class="badge-float bg-blue-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg  ">
                            {{ $stats['purchaseRequests'] ?? 0 }}
                        </span>
                        </div>
                        <div class="btn-wrapper flex-1">
                            <a href="{{ route('filament.dashboard.resources.proforma-invoices.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 border-cyan-500/50 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-sm sm:text-base"
                               :class="darkMode ? 'text-white' : 'text-slate-900'">
                                <x-heroicon-o-document-text class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.proforma') }}
                            </a>
                            <span class="badge-float bg-cyan-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
                            {{ $stats['proformaInvoices'] ?? 0 }}
                        </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 2: Order Processing -->
        <div class="card-3d workflow-step relative">
            <div class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl">
                <div class="absolute top-0 right-0 w-32 h-32 sm:w-40 sm:h-40 bg-green-500 rounded-full glow-orb"></div>
                <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

                <div class="relative z-10">
                    <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-green-500 to-green-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0" style="animation-delay: 1s;">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-green-400 bg-green-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full   whitespace-nowrap">
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
                        <div class="btn-wrapper flex-1">
                            <a href="{{ route('filament.dashboard.resources.registered-orders.index')}}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full bg-gradient-to-r from-green-600 to-green-700 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-white text-sm sm:text-base">
                                <x-heroicon-o-document-check class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.view_orders') }}
                            </a>
                            <span class="badge-float bg-green-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg  ">
                            {{ $stats['registeredOrders'] ?? 0 }}
                        </span>
                        </div>
                        <div class="btn-wrapper flex-1">
                            <a href="{{ route('filament.dashboard.resources.bank-profiles.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 border-emerald-500/50 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-sm sm:text-base"
                               :class="darkMode ? 'text-white' : 'text-slate-900'">
                                <x-heroicon-o-building-office class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.banks') }}
                            </a>
                            <span class="badge-float bg-emerald-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
                            {{ $stats['bankProfiles'] ?? 0 }}
                        </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 3: Procurement & Payment -->
        <div class="card-3d workflow-step relative">
            <div class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl">
                <div class="absolute top-0 right-0 w-32 h-32 sm:w-40 sm:h-40 bg-amber-500 rounded-full glow-orb"></div>
                <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

                <div class="relative z-10">
                    <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-amber-600 to-orange-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0" style="animation-delay: 2s;">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-amber-400 bg-amber-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full   whitespace-nowrap">
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
                        <div class="btn-wrapper flex-1">
                            <a href="{{ route('filament.dashboard.resources.purchase-orders.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full bg-gradient-to-r from-amber-600 to-orange-700 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-white text-sm sm:text-base">
                                <x-heroicon-o-shopping-bag class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.purchase_orders') }}
                            </a>
                            <span class="badge-float bg-amber-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg  ">
                            {{ $stats['purchaseOrders'] ?? 0 }}
                        </span>
                        </div>
                        <div class="btn-wrapper flex-1">
                            <a href="{{ route('filament.dashboard.resources.payments.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 border-orange-500/50 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-sm sm:text-base"
                               :class="darkMode ? 'text-white' : 'text-slate-900'">
                                <x-heroicon-o-banknotes class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.payments') }}
                            </a>
                            <span class="badge-float bg-orange-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
                            {{ $stats['payments'] ?? 0 }}
                        </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Step 4: Shipments & Customs -->
        <div class="card-3d workflow-step relative">
            <div class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl">
                <div class="absolute top-0 right-0 w-32 h-32 sm:w-40 sm:h-40 bg-purple-500 rounded-full glow-orb"></div>
                <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

                <div class="relative z-10">
                    <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0" style="animation-delay: 3s;">
                            <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                            </svg>
                        </div>
                        <span class="text-xs font-bold text-purple-400 bg-purple-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full   whitespace-nowrap">
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
                        <div class="btn-wrapper flex-1">
                            <a href="{{ route('filament.dashboard.resources.shipments.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full bg-gradient-to-r from-purple-600 to-purple-700 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-white text-sm sm:text-base">
                                <x-heroicon-o-truck class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.submodules.shipment.title') }}
                            </a>
                            <span class="badge-float bg-purple-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg  ">
                            {{ $stats['shipments'] ?? 0 }}
                        </span>
                        </div>
                        <div class="btn-wrapper flex-1">
                            <a href="{{ route('filament.dashboard.resources.customs.index') }}" target="_blank" rel="noopener noreferrer"
                               class="btn-gradient block w-full backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 border-violet-500/50 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-sm sm:text-base"
                               :class="darkMode ? 'text-white' : 'text-slate-900'">
                                <x-heroicon-o-clipboard-document-check class="w-5 h-5 inline-block" />
                                {{ __('dashboard/strings.submodules.custom_clearance.title') }}
                            </a>
                            <span class="badge-float bg-violet-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
                            {{ $stats['customs'] ?? 0 }}
                        </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
