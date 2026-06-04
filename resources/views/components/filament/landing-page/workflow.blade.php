
<div x-data="{
    shortcuts: [],
    customized: false,
    isEditing: false,
    isExpanded: false,
    available: [
        {id: 'purchaseRequests', route: '{{ route('filament.dashboard.resources.purchase-requests.index') }}', icon: 'heroicon-o-document-text', label: '{{ __('dashboard/strings.purchase_requests') ?? 'Purchase Requests' }}', color: 'blue'},
        {id: 'proformaInvoices', route: '{{ route('filament.dashboard.resources.proforma-invoices.index') }}', icon: 'heroicon-o-document-magnifying-glass', label: '{{ __('dashboard/strings.proforma') ?? 'Proforma Invoices' }}', color: 'indigo'},
        {id: 'registeredOrders', route: '{{ route('filament.dashboard.resources.registered-orders.index') }}', icon: 'heroicon-o-document-check', label: '{{ __('dashboard/strings.view_orders') ?? 'Registered Orders' }}', color: 'green'},
        {id: 'bankProfiles', route: '{{ route('filament.dashboard.resources.bank-profiles.index') }}', icon: 'heroicon-o-building-office', label: '{{ __('dashboard/strings.banks') ?? 'Bank Profiles' }}', color: 'emerald'},
        {id: 'purchaseOrders', route: '{{ route('filament.dashboard.resources.purchase-orders.index') }}', icon: 'heroicon-o-shopping-bag', label: '{{ __('dashboard/strings.purchase_orders') ?? 'Purchase Orders' }}', color: 'amber'},
        {id: 'payments', route: '{{ route('filament.dashboard.resources.payments.index') }}', icon: 'heroicon-o-banknotes', label: '{{ __('dashboard/strings.payments') ?? 'Payments' }}', color: 'orange'},
        {id: 'shipments', route: '{{ route('filament.dashboard.resources.shipments.index') }}', icon: 'heroicon-o-truck', label: '{{ __('dashboard/strings.submodules.shipment.title') ?? 'Shipments' }}', color: 'purple'},
        {id: 'customs', route: '{{ route('filament.dashboard.resources.customs.index') }}', icon: 'heroicon-o-clipboard-document-check', label: '{{ __('dashboard/strings.submodules.custom_clearance.title') ?? 'Customs' }}', color: 'violet'}
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
}" class="mb-12">
    <div class="glass border border-white/10 rounded-2xl sm:rounded-3xl overflow-hidden shadow-xl transition-all duration-300">
        <div @click="isExpanded = !isExpanded" class="px-6 py-5 flex items-center justify-between cursor-pointer hover:bg-white/5 transition-colors">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md">
                    <x-heroicon-o-squares-2x2 class="w-6 h-6" />
                </div>
                <div>
                    <h2 class="text-lg sm:text-xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.workspace') ?? 'Your Workspace' }}
                    </h2>
                    <p class="text-xs sm:text-sm" :class="darkMode ? 'text-slate-400' : 'text-slate-500'" x-text="shortcuts.length === 0 ? 'Click to set up your personal shortcuts' : shortcuts.length + ' shortcuts active'"></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button @click.stop="isEditing = true" class="p-2 rounded-lg hover:bg-white/10 transition-colors" :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'" title="Edit Workspace">
                    <x-heroicon-o-pencil-square class="w-5 h-5" />
                </button>
                <div class="p-1 rounded-full bg-white/5" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                    <svg class="w-6 h-6 transition-transform duration-300" :class="isExpanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
        </div>

        <div x-show="isExpanded" x-collapse x-cloak>
            <div class="p-6 pt-0 border-t border-white/5 mt-2">
                <div x-show="shortcuts.length === 0" class="text-center py-8">
                    <div class="w-16 h-16 bg-slate-500/10 rounded-full flex items-center justify-center text-slate-400 mx-auto mb-3">
                        <x-heroicon-o-squares-plus class="w-8 h-8" />
                    </div>
                    <h3 class="text-lg font-semibold mb-2" :class="darkMode ? 'text-white' : 'text-slate-800'">No shortcuts configured</h3>
                    <p class="text-sm mb-4 max-w-md mx-auto" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                        Pin your most frequently used modules here for quick access across the application.
                    </p>
                    <button @click="isEditing = true" class="px-5 py-2 rounded-xl font-semibold text-white shadow-md transition-transform hover:scale-105 bg-gradient-to-r from-indigo-500 to-purple-600 text-sm">
                        {{ __('dashboard/strings.add_shortcut') ?? 'Add Shortcuts' }}
                    </button>
                </div>

                <div x-show="shortcuts.length > 0" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <template x-for="item in shortcuts" :key="item.id">
                        <a :href="item.route" target="_blank" rel="noopener noreferrer" class="glass border border-white/5 rounded-xl p-4 relative overflow-hidden group hover:-translate-y-1 transition-all hover:shadow-lg hover:border-white/20">
                            <div class="relative z-10 flex flex-col items-center text-center gap-2">
                                <div :class="`w-10 h-10 rounded-lg flex items-center justify-center text-white bg-gradient-to-br from-${item.color}-500 to-${item.color}-600 shadow-sm group-hover:scale-110 transition-transform`">
                                    <template x-if="item.icon === 'heroicon-o-document-text'"><x-heroicon-o-document-text class="w-5 h-5" /></template>
                                    <template x-if="item.icon === 'heroicon-o-document-magnifying-glass'"><x-heroicon-o-document-magnifying-glass class="w-5 h-5" /></template>
                                    <template x-if="item.icon === 'heroicon-o-document-check'"><x-heroicon-o-document-check class="w-5 h-5" /></template>
                                    <template x-if="item.icon === 'heroicon-o-building-office'"><x-heroicon-o-building-office class="w-5 h-5" /></template>
                                    <template x-if="item.icon === 'heroicon-o-shopping-bag'"><x-heroicon-o-shopping-bag class="w-5 h-5" /></template>
                                    <template x-if="item.icon === 'heroicon-o-banknotes'"><x-heroicon-o-banknotes class="w-5 h-5" /></template>
                                    <template x-if="item.icon === 'heroicon-o-truck'"><x-heroicon-o-truck class="w-5 h-5" /></template>
                                    <template x-if="item.icon === 'heroicon-o-clipboard-document-check'"><x-heroicon-o-clipboard-document-check class="w-5 h-5" /></template>
                                </div>
                                <span class="font-medium text-xs leading-tight" :class="darkMode ? 'text-slate-300' : 'text-slate-700'" x-text="item.label"></span>
                            </div>
                            <template x-if="stats[item.id] !== undefined && stats[item.id] !== 0">
                                <span class="absolute top-2 right-2 text-white text-[10px] font-bold rounded-full min-w-[20px] h-5 px-1 flex items-center justify-center shadow-sm" :class="`bg-${item.color}-500`" x-text="stats[item.id]"></span>
                            </template>
                        </a>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Overlay -->
    <div x-show="isEditing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" x-transition.opacity>
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm" @click="isEditing = false"></div>
        <div class="glass border border-white/10 rounded-3xl p-6 sm:p-8 relative z-10 w-full max-w-4xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col" x-transition.scale>
            <div class="flex items-center justify-between mb-6 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md">
                        <x-heroicon-o-adjustments-horizontal class="w-6 h-6" />
                    </div>
                    <div>
                        <h3 class="text-2xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
                            {{ __('dashboard/strings.edit_workspace') ?? 'Customize Shortcuts' }}
                        </h3>
                        <p class="text-sm" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">Select the modules you want to pin.</p>
                    </div>
                </div>
                <button @click="isEditing = false" class="p-2 rounded-full hover:bg-white/10 text-slate-500 hover:text-slate-300 transition-colors">
                    <x-heroicon-o-x-mark class="w-6 h-6" />
                </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-6 flex-1 overflow-y-auto pr-2 custom-scrollbar">
                <template x-for="module in available" :key="module.id">
                    <button @click="toggleShortcut(module)" class="relative glass border rounded-2xl p-4 flex flex-col items-center text-center gap-3 transition-all hover:scale-105" :class="selectedIds.includes(module.id) ? (darkMode ? 'border-indigo-500 bg-indigo-500/10 shadow-[0_0_15px_rgba(99,102,241,0.2)]' : 'border-indigo-600 bg-indigo-50/50 shadow-[0_0_15px_rgba(79,70,229,0.1)]') : 'border-white/10 opacity-70 hover:opacity-100'">
                        <div :class="`w-12 h-12 rounded-xl flex items-center justify-center text-white bg-gradient-to-br from-${module.color}-500 to-${module.color}-600 shadow-md`">
                            <template x-if="module.icon === 'heroicon-o-document-text'"><x-heroicon-o-document-text class="w-6 h-6" /></template>
                            <template x-if="module.icon === 'heroicon-o-document-magnifying-glass'"><x-heroicon-o-document-magnifying-glass class="w-6 h-6" /></template>
                            <template x-if="module.icon === 'heroicon-o-document-check'"><x-heroicon-o-document-check class="w-6 h-6" /></template>
                            <template x-if="module.icon === 'heroicon-o-building-office'"><x-heroicon-o-building-office class="w-6 h-6" /></template>
                            <template x-if="module.icon === 'heroicon-o-shopping-bag'"><x-heroicon-o-shopping-bag class="w-6 h-6" /></template>
                            <template x-if="module.icon === 'heroicon-o-banknotes'"><x-heroicon-o-banknotes class="w-6 h-6" /></template>
                            <template x-if="module.icon === 'heroicon-o-truck'"><x-heroicon-o-truck class="w-6 h-6" /></template>
                            <template x-if="module.icon === 'heroicon-o-clipboard-document-check'"><x-heroicon-o-clipboard-document-check class="w-6 h-6" /></template>
                        </div>
                        <span class="font-semibold text-sm leading-tight" :class="darkMode ? 'text-white' : 'text-slate-800'" x-text="module.label"></span>

                        <div x-show="selectedIds.includes(module.id)" class="absolute top-3 right-3 w-6 h-6 rounded-full flex items-center justify-center shadow-md" :class="darkMode ? 'bg-indigo-500 text-white' : 'bg-indigo-600 text-white'" x-transition.scale>
                            <x-heroicon-o-check class="w-4 h-4" />
                        </div>
                    </button>
                </template>
            </div>

            <div class="flex items-center justify-end pt-5 border-t border-white/10 flex-shrink-0 mt-auto">
                <button @click="saveShortcuts()" class="px-8 py-3 rounded-xl font-semibold text-white shadow-lg transition-transform hover:scale-105 bg-gradient-to-r from-indigo-500 to-purple-600 w-full sm:w-auto flex items-center justify-center gap-2">
                    <x-heroicon-o-check-circle class="w-5 h-5" />
                    {{ __('dashboard/strings.save') ?? 'Save Changes' }}
                </button>
            </div>
        </div>
    </div>
</div>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 mb-12">
    <!-- Step 1: Request & Approval -->
    <div class="card-3d">
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
    <div class="card-3d">
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
    <div class="card-3d">
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
    <div class="card-3d">
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
