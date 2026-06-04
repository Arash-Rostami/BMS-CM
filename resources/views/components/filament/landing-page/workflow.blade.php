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
                    <span class="text-xs font-bold text-blue-400 bg-blue-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full whitespace-nowrap">
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
                           class="btn-gradient block w-full bg-gradient-to-r from-blue-600 to-blue-800 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-white text-sm sm:text-base">
                            <x-heroicon-o-document-text class="w-5 h-5 inline-block" />
                            {{ __('dashboard/strings.view_requests') }}
                        </a>
                        <span class="badge-float bg-blue-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
                            {{ $stats['purchaseRequests'] ?? 0 }}
                        </span>
                    </div>
                    <div class="btn-wrapper flex-1">
                        <a href="{{ route('filament.dashboard.resources.proforma-invoices.index') }}" target="_blank" rel="noopener noreferrer"
                           class="btn-gradient block w-full backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 border-indigo-500/50 border-2 px-4 sm:px-6 py-3 sm:py-4 rounded-xl sm:rounded-2xl font-semibold text-center text-sm sm:text-base"
                           :class="darkMode ? 'text-white' : 'text-slate-900'">
                            <x-heroicon-o-document-magnifying-glass class="w-5 h-5 inline-block" />
                            {{ __('dashboard/strings.proforma') }}
                        </a>
                        <span class="badge-float bg-indigo-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
                            {{ $stats['proformaInvoices'] ?? 0 }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 2: Order & Profiles -->
    <div class="card-3d">
        <div class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-6 sm:p-8 relative overflow-hidden group shadow-2xl">
            <div class="absolute -top-10 -right-10 w-48 h-48 bg-emerald-500 rounded-full glow-orb"></div>
            <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>

            <div class="relative z-10">
                <div class="flex items-start justify-between mb-4 sm:mb-6 gap-3">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gradient-to-br from-emerald-500 to-green-600 rounded-2xl sm:rounded-3xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-300 ease-in-out shadow-2xl floating flex-shrink-0" style="animation-delay: 1s;">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                    </div>
                    <span class="text-xs font-bold text-emerald-400 bg-emerald-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full whitespace-nowrap">
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
                        <span class="badge-float bg-green-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
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
                    <span class="text-xs font-bold text-amber-400 bg-amber-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full whitespace-nowrap">
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
                        <span class="badge-float bg-amber-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
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
                    <span class="text-xs font-bold text-purple-400 bg-purple-500/30 px-3 sm:px-4 py-1.5 sm:py-2 rounded-full whitespace-nowrap">
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
                        <span class="badge-float bg-purple-400 text-white text-xs font-bold rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
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

<div x-data="{
    shortcuts: [],
    customized: false,
    isEditing: false,
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
        } else {
            this.shortcuts = [];
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
    }
}">
    <div class="flex items-center justify-between mb-4 mt-16" x-show="shortcuts.length > 0 || isEditing">
        <h2 class="text-xl sm:text-2xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
            {{ __('dashboard/strings.workspace') ?? 'Your Workspace' }}
        </h2>
        <button @click="isEditing = true" class="text-sm font-semibold hover:underline" :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">
            {{ __('dashboard/strings.edit_workspace') ?? 'Customize Shortcuts' }}
        </button>
    </div>

    <!-- Empty State -->
    <div x-show="shortcuts.length === 0 && !isEditing" class="mt-16 text-center py-12 glass border border-white/10 rounded-3xl relative overflow-hidden group">
        <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>
        <div class="relative z-10 flex flex-col items-center gap-4">
            <div class="w-20 h-20 bg-slate-500/20 rounded-full flex items-center justify-center text-slate-400 mb-2">
                <x-heroicon-o-squares-plus class="w-10 h-10" />
            </div>
            <h3 class="text-xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
                {{ __('dashboard/strings.workspace') ?? 'Your Workspace' }}
            </h3>
            <p class="text-sm max-w-md mx-auto" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                Personalize your dashboard by pinning your most frequently used modules here for quick access.
            </p>
            <button @click="isEditing = true" class="mt-4 px-6 py-3 rounded-xl font-semibold text-white shadow-lg transition-transform hover:scale-105 bg-gradient-to-r from-cyan-500 to-blue-600">
                {{ __('dashboard/strings.add_shortcut') ?? 'Add Shortcut' }}
            </button>
        </div>
    </div>

    <div x-show="shortcuts.length > 0" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-12">
        <template x-for="item in shortcuts" :key="item.id">
            <a :href="item.route" target="_blank" rel="noopener noreferrer" class="glass border border-white/10 rounded-2xl p-5 relative overflow-hidden group shadow-lg transition-all hover:-translate-y-1 hover:shadow-2xl">
                <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>
                <div class="relative z-10 flex flex-col items-center text-center gap-3">
                    <div :class="`w-14 h-14 rounded-2xl flex items-center justify-center text-white bg-gradient-to-br from-${item.color}-500 to-${item.color}-700 shadow-md group-hover:scale-110 transition-transform`">
                        <template x-if="item.icon === 'heroicon-o-document-text'">
                            <x-heroicon-o-document-text class="w-7 h-7" />
                        </template>
                        <template x-if="item.icon === 'heroicon-o-document-magnifying-glass'">
                            <x-heroicon-o-document-magnifying-glass class="w-7 h-7" />
                        </template>
                        <template x-if="item.icon === 'heroicon-o-document-check'">
                            <x-heroicon-o-document-check class="w-7 h-7" />
                        </template>
                        <template x-if="item.icon === 'heroicon-o-building-office'">
                            <x-heroicon-o-building-office class="w-7 h-7" />
                        </template>
                        <template x-if="item.icon === 'heroicon-o-shopping-bag'">
                            <x-heroicon-o-shopping-bag class="w-7 h-7" />
                        </template>
                        <template x-if="item.icon === 'heroicon-o-banknotes'">
                            <x-heroicon-o-banknotes class="w-7 h-7" />
                        </template>
                        <template x-if="item.icon === 'heroicon-o-truck'">
                            <x-heroicon-o-truck class="w-7 h-7" />
                        </template>
                        <template x-if="item.icon === 'heroicon-o-clipboard-document-check'">
                            <x-heroicon-o-clipboard-document-check class="w-7 h-7" />
                        </template>
                    </div>
                    <span class="font-semibold text-sm" :class="darkMode ? 'text-white' : 'text-slate-800'" x-text="item.label"></span>
                </div>
                <template x-if="stats[item.id] !== undefined && stats[item.id] !== 0">
                    <span class="absolute top-3 right-3 text-white text-xs font-bold rounded-full min-w-[24px] h-6 px-1.5 flex items-center justify-center shadow-lg" :class="`bg-${item.color}-500`" x-text="stats[item.id]"></span>
                </template>
            </a>
        </template>
    </div>

    <!-- Edit Overlay -->
    <div x-show="isEditing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" x-transition.opacity>
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm" @click="isEditing = false"></div>
        <div class="glass border border-white/10 rounded-3xl p-6 sm:p-8 relative z-10 w-full max-w-4xl max-h-full overflow-y-auto shadow-2xl flex flex-col" x-transition.scale>
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-2xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
                    {{ __('dashboard/strings.edit_workspace') ?? 'Customize Shortcuts' }}
                </h3>
                <button @click="isEditing = false" class="text-slate-500 hover:text-slate-300 transition-colors">
                    <x-heroicon-o-x-mark class="w-6 h-6" />
                </button>
            </div>

            <p class="text-sm mb-6" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                Select the modules you want to pin to your personal workspace grid.
            </p>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-8 flex-1 overflow-y-auto min-h-[40vh]">
                <template x-for="module in available" :key="module.id">
                    <button @click="toggleShortcut(module)" class="relative glass border rounded-2xl p-4 flex flex-col items-center text-center gap-3 transition-all hover:scale-105" :class="selectedIds.includes(module.id) ? (darkMode ? 'border-cyan-400 bg-cyan-400/10' : 'border-indigo-600 bg-indigo-600/10') : 'border-white/10 opacity-70 hover:opacity-100'">
                        <div :class="`w-12 h-12 rounded-xl flex items-center justify-center text-white bg-gradient-to-br from-${module.color}-500 to-${module.color}-700 shadow-md`">
                            <template x-if="module.icon === 'heroicon-o-document-text'">
                                <x-heroicon-o-document-text class="w-6 h-6" />
                            </template>
                            <template x-if="module.icon === 'heroicon-o-document-magnifying-glass'">
                                <x-heroicon-o-document-magnifying-glass class="w-6 h-6" />
                            </template>
                            <template x-if="module.icon === 'heroicon-o-document-check'">
                                <x-heroicon-o-document-check class="w-6 h-6" />
                            </template>
                            <template x-if="module.icon === 'heroicon-o-building-office'">
                                <x-heroicon-o-building-office class="w-6 h-6" />
                            </template>
                            <template x-if="module.icon === 'heroicon-o-shopping-bag'">
                                <x-heroicon-o-shopping-bag class="w-6 h-6" />
                            </template>
                            <template x-if="module.icon === 'heroicon-o-banknotes'">
                                <x-heroicon-o-banknotes class="w-6 h-6" />
                            </template>
                            <template x-if="module.icon === 'heroicon-o-truck'">
                                <x-heroicon-o-truck class="w-6 h-6" />
                            </template>
                            <template x-if="module.icon === 'heroicon-o-clipboard-document-check'">
                                <x-heroicon-o-clipboard-document-check class="w-6 h-6" />
                            </template>
                        </div>
                        <span class="font-semibold text-sm" :class="darkMode ? 'text-white' : 'text-slate-800'" x-text="module.label"></span>

                        <div x-show="selectedIds.includes(module.id)" class="absolute top-2 right-2 w-6 h-6 rounded-full flex items-center justify-center shadow-md" :class="darkMode ? 'bg-cyan-400 text-slate-900' : 'bg-indigo-600 text-white'" x-transition.scale>
                            <x-heroicon-o-check class="w-4 h-4" />
                        </div>
                    </button>
                </template>
            </div>

            <div class="flex items-center justify-end pt-6 border-t border-white/10 mt-auto">
                <button @click="saveShortcuts()" class="px-8 py-3 rounded-xl font-semibold text-white shadow-lg transition-transform hover:scale-105 bg-gradient-to-r from-cyan-500 to-blue-600 w-full sm:w-auto">
                    {{ __('dashboard/strings.save') ?? 'Save Changes' }}
                </button>
            </div>
        </div>
    </div>
</div>
