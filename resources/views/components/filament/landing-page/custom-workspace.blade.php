<div x-data="{
    shortcuts: [],
    customized: false,
    isEditing: false,
    isExpanded: false,
    available: [
        {id: 'purchaseRequests', type: 'major', route: '{{ route('filament.dashboard.resources.purchase-requests.index') }}', icon: 'heroicon-o-document-text', label: '{{ __('dashboard/strings.resources.purchase_requests') ?? 'Purchase Requests' }}', theme: 'from-blue-500 to-blue-600', badge: 'bg-blue-500'},
        {id: 'proformaInvoices', type: 'major', route: '{{ route('filament.dashboard.resources.proforma-invoices.index') }}', icon: 'heroicon-o-document-magnifying-glass', label: '{{ __('dashboard/strings.proforma') ?? 'Proforma Invoices' }}', theme: 'from-indigo-500 to-indigo-600', badge: 'bg-indigo-500'},
        {id: 'registeredOrders', type: 'major', route: '{{ route('filament.dashboard.resources.registered-orders.index') }}', icon: 'heroicon-o-document-check', label: '{{ __('dashboard/strings.view_orders') ?? 'Registered Orders' }}', theme: 'from-green-500 to-green-600', badge: 'bg-green-500'},
        {id: 'bankProfiles', type: 'minor', route: '{{ route('filament.dashboard.resources.bank-profiles.index') }}', icon: 'heroicon-o-building-office', label: '{{ __('dashboard/strings.banks') ?? 'Bank Profiles' }}', theme: 'from-emerald-500 to-emerald-600', badge: 'bg-emerald-500'},
        {id: 'purchaseOrders', type: 'major', route: '{{ route('filament.dashboard.resources.purchase-orders.index') }}', icon: 'heroicon-o-shopping-bag', label: '{{ __('dashboard/strings.purchase_orders') ?? 'Purchase Orders' }}', theme: 'from-amber-500 to-amber-600', badge: 'bg-amber-500'},
        {id: 'payments', type: 'major', route: '{{ route('filament.dashboard.resources.payments.index') }}', icon: 'heroicon-o-banknotes', label: '{{ __('dashboard/strings.payments') ?? 'Payments' }}', theme: 'from-orange-500 to-orange-600', badge: 'bg-orange-500'},
        {id: 'shipments', type: 'major', route: '{{ route('filament.dashboard.resources.shipments.index') }}', icon: 'heroicon-o-truck', label: '{{ __('dashboard/strings.submodules.shipment.title') ?? 'Shipments' }}', theme: 'from-purple-500 to-purple-600', badge: 'bg-purple-500'},
        {id: 'customs', type: 'major', route: '{{ route('filament.dashboard.resources.customs.index') }}', icon: 'heroicon-o-clipboard-document-check', label: '{{ __('dashboard/strings.submodules.custom_clearance.title') ?? 'Customs' }}', theme: 'from-violet-500 to-violet-600', badge: 'bg-violet-500'},
        {id: 'categories', type: 'minor', route: '{{ route('filament.dashboard.resources.categories.index') }}', icon: 'heroicon-o-tag', label: '{{ __('dashboard/strings.resources.categories') ?? 'Categories' }}', theme: 'from-slate-500 to-slate-600', badge: 'bg-slate-500'},
        {id: 'products', type: 'minor', route: '{{ route('filament.dashboard.resources.products.index') }}', icon: 'heroicon-o-cube', label: '{{ __('dashboard/strings.resources.products') ?? 'Products' }}', theme: 'from-sky-500 to-sky-600', badge: 'bg-sky-500'},
        {id: 'companies', type: 'minor', route: '{{ route('filament.dashboard.resources.companies.index') }}', icon: 'heroicon-o-building-storefront', label: '{{ __('dashboard/strings.resources.companies') ?? 'Companies' }}', theme: 'from-teal-500 to-teal-600', badge: 'bg-teal-500'},
        {id: 'banks', type: 'minor', route: '{{ route('filament.dashboard.resources.banks.index') }}', icon: 'heroicon-o-building-library', label: '{{ __('dashboard/strings.resources.banks') ?? 'Banks' }}', theme: 'from-lime-500 to-lime-600', badge: 'bg-lime-500'},
        {id: 'currencies', type: 'minor', route: '{{ route('filament.dashboard.resources.currencies.index') }}', icon: 'heroicon-o-currency-dollar', label: '{{ __('dashboard/strings.resources.currencies') ?? 'Currencies' }}', theme: 'from-emerald-500 to-emerald-600', badge: 'bg-emerald-500'},
        {id: 'statuses', type: 'minor', route: '{{ route('filament.dashboard.resources.statuses.index') }}', icon: 'heroicon-o-flag', label: '{{ __('dashboard/strings.resources.statuses') ?? 'Statuses' }}', theme: 'from-rose-500 to-rose-600', badge: 'bg-rose-500'},
        {id: 'notifications', type: 'minor', route: '{{ route('filament.dashboard.resources.notification-settings.index') }}', icon: 'heroicon-o-bell', label: '{{ __('dashboard/strings.resources.notification_settings') ?? 'Notifications' }}', theme: 'from-yellow-500 to-yellow-600', badge: 'bg-yellow-500'}
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
    <div class="glass border border-slate-200 dark:border-white/10 rounded-2xl sm:rounded-3xl overflow-hidden shadow-xl transition-all duration-300 bg-white/80 dark:bg-slate-900/80">
        <div @click="isExpanded = !isExpanded" class="px-6 py-5 flex items-center justify-between cursor-pointer hover:bg-slate-50 dark:hover:bg-white/5 transition-colors group">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md group-hover:scale-105 transition-transform flex-shrink-0">
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
                <button @click.stop="isEditing = true" class="p-2.5 rounded-xl hover:bg-slate-200 dark:hover:bg-white/10 transition-colors shadow-sm border border-slate-200 dark:border-white/5 !cursor-pointer" :class="darkMode ? 'text-cyan-400 bg-white/5' : 'text-indigo-600 bg-slate-100'" title="{{ __('dashboard/strings.edit_workspace') }}">
                    <x-heroicon-o-pencil-square class="w-5 h-5" />
                </button>
                <div class="p-1.5 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                    <svg class="w-5 h-5 transition-transform duration-300" :class="isExpanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
        </div>

        <div x-show="isExpanded" x-collapse x-cloak>
            <div class="p-6 pt-0 border-t border-slate-200 dark:border-white/5 mt-2 bg-slate-50/50 dark:bg-white/5">
                <div x-show="shortcuts.length === 0" class="text-center py-10">
                    <div class="w-20 h-20 bg-slate-500/10 rounded-full flex items-center justify-center text-slate-400 mx-auto mb-4 border border-slate-200 dark:border-white/5 shadow-inner">
                        <x-heroicon-o-squares-plus class="w-10 h-10" />
                    </div>
                    <h3 class="text-xl font-bold mb-2" :class="darkMode ? 'text-white' : 'text-slate-800'">{{ __('dashboard/strings.no_shortcuts') ?? 'No shortcuts configured' }}</h3>
                    <p class="text-sm mb-6 max-w-md mx-auto" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                        {{ __('dashboard/strings.no_shortcuts_hint') ?? 'Pin your most frequently used modules here for quick access across the application.' }}
                    </p>
                    <button @click="isEditing = true" class="px-6 py-3 rounded-xl font-bold text-white shadow-lg transition-transform hover:scale-105 bg-gradient-to-r from-indigo-500 to-purple-600 !cursor-pointer">
                        {{ __('dashboard/strings.add_shortcut') ?? 'Add Shortcuts' }}
                    </button>
                </div>

                <div x-show="shortcuts.length > 0" class="flex flex-col gap-6 pt-4">
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4">
                        <template x-for="item in shortcuts.slice(0, 8)" :key="item.id">
                            <a :href="item.route" target="_blank" rel="noopener noreferrer" class="glass border border-slate-200 dark:border-white/10 rounded-2xl p-4 relative overflow-hidden group hover:-translate-y-1.5 transition-all duration-300 hover:shadow-xl bg-slate-50/80 dark:bg-slate-800/40 flex flex-col justify-between min-h-[120px]">
                                <div class="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                                <div class="relative z-10 flex flex-col items-center text-center gap-3 h-full justify-center">

                                    <div :class="['w-12 h-12 rounded-xl flex items-center justify-center text-white bg-gradient-to-br shadow-md group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 flex-shrink-0', item.theme]">
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
                                        <template x-if="item.icon === 'heroicon-o-building-storefront'"><x-heroicon-o-building-storefront class="w-6 h-6" /></template>
                                    </div>
                                    <span class="font-bold text-xs sm:text-sm leading-tight line-clamp-2 min-h-[2rem] flex items-center justify-center" :class="darkMode ? 'text-slate-200 group-hover:text-white' : 'text-slate-700 group-hover:text-slate-900'" x-text="item.label"></span>
                                </div>

                                <template x-if="stats[item.id] !== undefined && stats[item.id] !== 0">
                                    <span :class="['absolute top-2 right-2 text-white text-[10px] sm:text-xs font-bold rounded-full min-w-[20px] sm:min-w-[24px] h-5 sm:h-6 px-1 flex items-center justify-center shadow-md border border-white/20', item.badge]" x-text="stats[item.id]"></span>
                                </template>
                            </a>
                        </template>
                    </div>

                    <div x-show="shortcuts.length > 8" class="flex items-center gap-4 my-2 opacity-90 transition-all duration-300">
                        <div class="h-[1px] flex-1 bg-gradient-to-r from-transparent via-slate-300 dark:via-white/20 to-transparent"></div>
                        <div class="text-[10px] tracking-widest font-extrabold px-3 py-1 rounded-md uppercase bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-400 dark:text-slate-400">
                            Master Configurations
                        </div>
                        <div class="h-[1px] flex-1 bg-gradient-to-r from-transparent via-slate-300 dark:via-white/20 to-transparent"></div>
                    </div>

                    <div x-show="shortcuts.length > 8" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3">
                        <template x-for="item in shortcuts.slice(8)" :key="item.id">
                            <a :href="item.route" target="_blank" rel="noopener noreferrer" class="glass border border-slate-200 dark:border-white/10 rounded-xl p-2.5 relative overflow-hidden group hover:-translate-y-0.5 transition-all duration-300 hover:shadow-md bg-slate-50/60 dark:bg-slate-800/20 flex items-center gap-3">
                                <div class="absolute inset-0 bg-gradient-to-br from-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>

                                <div :class="['w-8 h-8 rounded-lg flex items-center justify-center text-white bg-gradient-to-br shadow-sm group-hover:scale-105 transition-transform flex-shrink-0', item.theme]">
                                    <template x-if="item.icon === 'heroicon-o-document-text'"><x-heroicon-o-document-text class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-document-magnifying-glass'"><x-heroicon-o-document-magnifying-glass class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-document-check'"><x-heroicon-o-document-check class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-building-office'"><x-heroicon-o-building-office class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-shopping-bag'"><x-heroicon-o-shopping-bag class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-banknotes'"><x-heroicon-o-banknotes class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-truck'"><x-heroicon-o-truck class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-clipboard-document-check'"><x-heroicon-o-clipboard-document-check class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-tag'"><x-heroicon-o-tag class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-cube'"><x-heroicon-o-cube class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-building-library'"><x-heroicon-o-building-library class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-currency-dollar'"><x-heroicon-o-currency-dollar class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-flag'"><x-heroicon-o-flag class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-bell'"><x-heroicon-o-bell class="w-4 h-4" /></template>
                                    <template x-if="item.icon === 'heroicon-o-building-storefront'"><x-heroicon-o-building-storefront class="w-4 h-4" /></template>
                                </div>
                                <span class="font-bold text-xs leading-tight truncate flex-1" :class="darkMode ? 'text-slate-300 group-hover:text-white' : 'text-slate-600 group-hover:text-slate-900'" x-text="item.label"></span>

                                <template x-if="stats[item.id] !== undefined && stats[item.id] !== 0">
                                    <span :class="['text-white text-[9px] font-bold rounded-full min-w-[18px] h-4.5 px-1 flex items-center justify-center shadow-sm', item.badge]" x-text="stats[item.id]"></span>
                                </template>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="isEditing" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4 sm:p-6" x-transition.opacity>
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-md" @click="isEditing = false"></div>
        <div class="glass border border-slate-200 dark:border-white/10 rounded-[2rem] p-6 sm:p-8 relative z-10 w-full max-w-5xl max-h-[90vh] overflow-hidden shadow-2xl flex flex-col bg-white dark:bg-slate-900/90" x-transition.scale>
            <div class="flex items-center justify-between mb-6 flex-shrink-0">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-lg border border-white/10 flex-shrink-0">
                        <x-heroicon-o-adjustments-horizontal class="w-7 h-7" />
                    </div>
                    <div>
                        <h3 class="text-3xl font-extrabold tracking-tight" :class="darkMode ? 'text-white' : 'text-slate-900'">
                            {{ __('dashboard/strings.edit_workspace') ?? 'Customize Shortcuts' }}
                        </h3>
                        <p class="text-sm mt-1" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">{{ __('dashboard/strings.edit_workspace_hint') ?? 'Select the modules you want to pin to your quick access grid.' }}</p>
                    </div>
                </div>
                <button @click="isEditing = false" class="p-3 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-900 hover:opacity-100 dark:bg-white/5 dark:text-slate-400 dark:hover:bg-white/10 dark:hover:text-white transition-all border border-slate-200 dark:border-white/10 !cursor-pointer">
                    <x-heroicon-o-x-mark class="w-6 h-6" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto pr-2 custom-scrollbar pb-4 space-y-6">
                <div class="flex items-center gap-4 my-4 opacity-90">
                    <div class="h-[1px] flex-1 bg-gradient-to-r from-transparent via-slate-300 dark:via-white/20 to-transparent"></div>
                    <div class="p-1.5 rounded-md bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-400 dark:text-slate-400 flex items-center justify-center flex-shrink-0" title="Operational">
                        <x-heroicon-o-cpu-chip class="w-4 h-4" />
                    </div>
                    <div class="h-[1px] flex-1 bg-gradient-to-r from-transparent via-slate-300 dark:via-white/20 to-transparent"></div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-5 scale-95">
                    <template x-for="module in available.slice(0, 8)" :key="module.id">
                        <button @click="toggleShortcut(module)" class="relative glass border rounded-[1.5rem] p-5 flex flex-col items-center text-center gap-4 transition-all duration-300 hover:scale-[1.02] min-h-[140px] justify-center !cursor-pointer" :class="selectedIds.includes(module.id) ? (darkMode ? 'border-indigo-500 bg-indigo-500/20 shadow-[0_0_25px_rgba(99,102,241,0.25)]' : 'border-indigo-600 bg-indigo-50 shadow-[0_0_20px_rgba(79,70,229,0.15)]') : 'border-slate-200 dark:border-white/10 opacity-80 hover:opacity-100 bg-slate-50 dark:bg-white/5'">
                            <div class="absolute inset-0 rounded-[1.5rem] bg-gradient-to-b from-white/10 to-transparent opacity-0 transition-opacity" :class="selectedIds.includes(module.id) ? 'opacity-100' : 'group-hover:opacity-50'"></div>

                            <div :class="['relative z-10 w-14 h-14 rounded-2xl flex items-center justify-center text-white bg-gradient-to-br shadow-lg flex-shrink-0', module.theme]">
                                <template x-if="module.icon === 'heroicon-o-document-text'"><x-heroicon-o-document-text class="w-7 h-7" /></template>
                                <template x-if="module.icon === 'heroicon-o-document-magnifying-glass'"><x-heroicon-o-document-magnifying-glass class="w-7 h-7" /></template>
                                <template x-if="module.icon === 'heroicon-o-document-check'"><x-heroicon-o-document-check class="w-7 h-7" /></template>
                                <template x-if="module.icon === 'heroicon-o-building-office'"><x-heroicon-o-building-office class="w-7 h-7" /></template>
                                <template x-if="module.icon === 'heroicon-o-shopping-bag'"><x-heroicon-o-shopping-bag class="w-7 h-7" /></template>
                                <template x-if="module.icon === 'heroicon-o-banknotes'"><x-heroicon-o-banknotes class="w-7 h-7" /></template>
                                <template x-if="module.icon === 'heroicon-o-truck'"><x-heroicon-o-truck class="w-7 h-7" /></template>
                                <template x-if="module.icon === 'heroicon-o-clipboard-document-check'"><x-heroicon-o-clipboard-document-check class="w-7 h-7" /></template>
                            </div>
                            <span class="relative z-10 font-bold text-sm sm:text-base leading-tight max-w-[90%]" :class="darkMode ? 'text-slate-200' : 'text-slate-800'" x-text="module.label"></span>
                            <div x-show="selectedIds.includes(module.id)" class="absolute top-4 right-4 w-7 h-7 rounded-full flex items-center justify-center shadow-lg border border-white/20" :class="darkMode ? 'bg-indigo-500 text-white' : 'bg-indigo-600 text-white'" x-transition.scale.origin.bottom.right>
                                <x-heroicon-o-check class="w-4 h-4" />
                            </div>
                        </button>
                    </template>
                </div>

                <div class="flex items-center gap-4 my-4 opacity-90">
                    <div class="h-[1px] flex-1 bg-gradient-to-r from-transparent via-slate-300 dark:via-white/20 to-transparent"></div>
                    <div class="p-1.5 rounded-md bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-400 dark:text-slate-400 flex items-center justify-center flex-shrink-0" title="Master">
                        <x-heroicon-o-circle-stack class="w-4 h-4" />
                    </div>
                    <div class="h-[1px] flex-1 bg-gradient-to-r from-transparent via-slate-300 dark:via-white/20 to-transparent"></div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3 scale-95 cursor-pointer">
                    <template x-for="module in available.slice(8)" :key="module.id">
                        <button @click="toggleShortcut(module)" class="relative glass border rounded-xl p-3 flex lg:flex-col items-center lg:justify-center gap-3 transition-all duration-300 hover:scale-[1.01] min-h-[64px] lg:min-h-[112px] text-left lg:text-center group !cursor-pointer" :class="selectedIds.includes(module.id) ? (darkMode ? 'border-indigo-500 bg-indigo-500/10 shadow-md' : 'border-indigo-600 bg-indigo-50/50 shadow-sm') : 'border-slate-200 dark:border-white/10 opacity-80 hover:opacity-100 bg-slate-50/50 dark:bg-white/5'">

                            <div :class="['relative z-10 w-9 h-9 rounded-lg flex items-center justify-center text-white bg-gradient-to-br shadow-md flex-shrink-0', module.theme]">
                                <template x-if="module.icon === 'heroicon-o-tag'"><x-heroicon-o-tag class="w-4 h-4" /></template>
                                <template x-if="module.icon === 'heroicon-o-cube'"><x-heroicon-o-cube class="w-4 h-4" /></template>
                                <template x-if="module.icon === 'heroicon-o-building-storefront'"><x-heroicon-o-building-storefront class="w-4 h-4" /></template>
                                <template x-if="module.icon === 'heroicon-o-building-library'"><x-heroicon-o-building-library class="w-4 h-4" /></template>
                                <template x-if="module.icon === 'heroicon-o-currency-dollar'"><x-heroicon-o-currency-dollar class="w-4 h-4" /></template>
                                <template x-if="module.icon === 'heroicon-o-flag'"><x-heroicon-o-flag class="w-4 h-4" /></template>
                                <template x-if="module.icon === 'heroicon-o-bell'"><x-heroicon-o-bell class="w-4 h-4" /></template>
                            </div>
                            <span class="relative z-10 font-bold text-xs leading-tight flex-1 lg:flex-none truncate lg:whitespace-normal lg:line-clamp-2" :class="darkMode ? 'text-slate-300 group-hover:text-white' : 'text-slate-700 group-hover:text-slate-900'" x-text="module.label"></span>
                            <div x-show="selectedIds.includes(module.id)" class="w-4 h-4 rounded-full flex items-center justify-center shadow bg-indigo-600 text-white border border-white/20 flex-shrink-0 lg:absolute lg:top-2 lg:right-2">
                                <x-heroicon-o-check class="w-2.5 h-2.5" />
                            </div>
                        </button>
                    </template>
                </div>
            </div>

            <div class="flex items-center justify-end pt-4 border-t border-slate-200 dark:border-white/10 flex-shrink-0 mt-auto">
                <button @click="saveShortcuts()" class="px-10 py-4 rounded-2xl font-bold text-white shadow-xl transition-all duration-300 hover:scale-105 hover:shadow-2xl bg-gradient-to-r from-indigo-500 via-purple-500 to-indigo-600 bg-size-200 hover:bg-pos-100 w-full sm:w-auto flex items-center justify-center gap-3 text-lg border border-white/20">
                    <x-heroicon-o-check-circle class="w-6 h-6" />
                    {{ __('dashboard/strings.save') ?? 'Save Changes' }}
                </button>
            </div>
        </div>
    </div>
</div>
