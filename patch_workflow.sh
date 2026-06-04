#!/bin/bash
cat << 'INNER_EOF' > /tmp/workflow_clean.blade.php
<div class="mb-10">
    <h2 class="text-xl sm:text-2xl font-bold mb-4" :class="darkMode ? 'text-white' : 'text-slate-900'">
        {{ __('dashboard/strings.corporate_pillars') ?? 'Corporate Pillars' }}
    </h2>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="#" class="glass border border-white/10 rounded-xl p-4 flex flex-col items-center justify-center gap-3 transition-all hover:-translate-y-1 hover:shadow-lg group">
            <div class="w-12 h-12 rounded-full bg-slate-500/20 flex items-center justify-center text-slate-500 group-hover:bg-slate-500 group-hover:text-white transition-colors">
                <x-heroicon-o-calendar-days class="w-6 h-6" />
            </div>
            <span class="text-sm font-semibold text-center" :class="darkMode ? 'text-slate-300' : 'text-slate-700'">{{ __('dashboard/strings.attendance') ?? 'Attendance' }}</span>
        </a>
        <a href="#" class="glass border border-white/10 rounded-xl p-4 flex flex-col items-center justify-center gap-3 transition-all hover:-translate-y-1 hover:shadow-lg group">
            <div class="w-12 h-12 rounded-full bg-indigo-500/20 flex items-center justify-center text-indigo-500 group-hover:bg-indigo-500 group-hover:text-white transition-colors">
                <x-heroicon-o-megaphone class="w-6 h-6" />
            </div>
            <span class="text-sm font-semibold text-center" :class="darkMode ? 'text-slate-300' : 'text-slate-700'">{{ __('dashboard/strings.company_news') ?? 'Company News' }}</span>
        </a>
        <a href="{{ route('filament.dashboard.pages.dashboard') }}" class="glass border border-white/10 rounded-xl p-4 flex flex-col items-center justify-center gap-3 transition-all hover:-translate-y-1 hover:shadow-lg group">
            <div class="w-12 h-12 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-500 group-hover:bg-emerald-500 group-hover:text-white transition-colors">
                <x-heroicon-o-user class="w-6 h-6" />
            </div>
            <span class="text-sm font-semibold text-center" :class="darkMode ? 'text-slate-300' : 'text-slate-700'">{{ __('dashboard/strings.profile') ?? 'Profile' }}</span>
        </a>
        <a href="#" class="glass border border-white/10 rounded-xl p-4 flex flex-col items-center justify-center gap-3 transition-all hover:-translate-y-1 hover:shadow-lg group">
            <div class="w-12 h-12 rounded-full bg-gray-500/20 flex items-center justify-center text-gray-500 group-hover:bg-gray-500 group-hover:text-white transition-colors">
                <x-heroicon-o-cog-6-tooth class="w-6 h-6" />
            </div>
            <span class="text-sm font-semibold text-center" :class="darkMode ? 'text-slate-300' : 'text-slate-700'">{{ __('dashboard/strings.settings') ?? 'Settings' }}</span>
        </a>
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
            this.shortcuts = this.available;
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
    },
    resetToDefault() {
        localStorage.removeItem('user_shortcuts');
        this.shortcuts = this.available;
        this.selectedIds = this.shortcuts.map(s => s.id);
        this.customized = false;
        this.isEditing = false;
    }
}">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl sm:text-2xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
            {{ __('dashboard/strings.workspace') ?? 'Workspace' }}
        </h2>
        <button @click="isEditing = true" class="text-sm font-semibold hover:underline" :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">
            {{ __('dashboard/strings.edit_workspace') ?? 'Edit Workspace' }}
        </button>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-12">
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

    <div x-show="isEditing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6">
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm" @click="isEditing = false"></div>
        <div class="glass border border-white/10 rounded-3xl p-6 sm:p-8 relative z-10 w-full max-w-4xl max-h-full overflow-y-auto shadow-2xl flex flex-col">
            <h3 class="text-2xl font-bold mb-6" :class="darkMode ? 'text-white' : 'text-slate-900'">
                {{ __('dashboard/strings.edit_workspace') ?? 'Edit Workspace' }}
            </h3>

            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mb-8 flex-1 overflow-y-auto min-h-[50vh]">
                <template x-for="module in available" :key="module.id">
                    <button @click="toggleShortcut(module)" class="relative glass border rounded-2xl p-4 flex flex-col items-center text-center gap-3 transition-all hover:scale-105" :class="selectedIds.includes(module.id) ? (darkMode ? 'border-cyan-400 bg-cyan-400/10' : 'border-indigo-600 bg-indigo-600/10') : 'border-white/10 opacity-70'">
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

                        <div x-show="selectedIds.includes(module.id)" class="absolute top-2 right-2 w-6 h-6 rounded-full flex items-center justify-center" :class="darkMode ? 'bg-cyan-400 text-slate-900' : 'bg-indigo-600 text-white'">
                            <x-heroicon-o-check class="w-4 h-4" />
                        </div>
                    </button>
                </template>
            </div>

            <div class="flex items-center justify-between pt-6 border-t border-white/10 mt-auto">
                <button @click="resetToDefault()" class="px-6 py-3 rounded-xl font-semibold transition-colors" :class="darkMode ? 'text-red-400 hover:bg-red-400/10' : 'text-red-600 hover:bg-red-600/10'">
                    {{ __('dashboard/strings.back_to_standard') ?? 'Reset to Default' }}
                </button>
                <button @click="saveShortcuts()" class="px-8 py-3 rounded-xl font-semibold text-white shadow-lg transition-transform hover:scale-105" :class="darkMode ? 'bg-cyan-500 hover:bg-cyan-400' : 'bg-indigo-600 hover:bg-indigo-500'">
                    {{ __('dashboard/strings.done') ?? 'Done' }}
                </button>
            </div>
        </div>
    </div>
</div>
INNER_EOF

# Replace entirely
cat /tmp/workflow_clean.blade.php > resources/views/components/filament/landing-page/workflow.blade.php
