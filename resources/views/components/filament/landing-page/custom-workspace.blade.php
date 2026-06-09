@php
    $modules = [
        ['id' => 'purchaseRequests', 'searchable' => true,  'heroicon' => 'heroicon-o-document-text',            'label' => __('dashboard/strings.resources.purchase_requests'),      'route' => route('filament.dashboard.resources.purchase-requests.index'),     'theme' => 'from-blue-500 to-blue-600',      'badge' => 'bg-blue-500'],
        ['id' => 'proformaInvoices', 'searchable' => true,  'heroicon' => 'heroicon-o-document-magnifying-glass','label' => __('dashboard/strings.proforma'),                         'route' => route('filament.dashboard.resources.proforma-invoices.index'),     'theme' => 'from-indigo-500 to-indigo-600',  'badge' => 'bg-indigo-500'],
        ['id' => 'registeredOrders', 'searchable' => true,  'heroicon' => 'heroicon-o-document-check',           'label' => __('dashboard/strings.view_orders'),                      'route' => route('filament.dashboard.resources.registered-orders.index'),     'theme' => 'from-green-500 to-green-600',    'badge' => 'bg-green-500'],
        ['id' => 'bankProfiles',     'searchable' => true,  'heroicon' => 'heroicon-o-building-office',          'label' => __('dashboard/strings.banks'),                            'route' => route('filament.dashboard.resources.bank-profiles.index'),         'theme' => 'from-emerald-500 to-emerald-600','badge' => 'bg-emerald-500'],
        ['id' => 'purchaseOrders',   'searchable' => true,  'heroicon' => 'heroicon-o-shopping-bag',             'label' => __('dashboard/strings.purchase_orders'),                  'route' => route('filament.dashboard.resources.purchase-orders.index'),       'theme' => 'from-amber-500 to-amber-600',    'badge' => 'bg-amber-500'],
        ['id' => 'payments',         'searchable' => true,  'heroicon' => 'heroicon-o-banknotes',                'label' => __('dashboard/strings.payments'),                         'route' => route('filament.dashboard.resources.payments.index'),              'theme' => 'from-orange-500 to-orange-600',  'badge' => 'bg-orange-500'],
        ['id' => 'shipments',        'searchable' => true,  'heroicon' => 'heroicon-o-truck',                    'label' => __('dashboard/strings.submodules.shipment.title'),        'route' => route('filament.dashboard.resources.shipments.index'),             'theme' => 'from-purple-500 to-purple-600',  'badge' => 'bg-purple-500'],
        ['id' => 'customs',          'searchable' => true,  'heroicon' => 'heroicon-o-clipboard-document-check', 'label' => __('dashboard/strings.submodules.custom_clearance.title'),'route' => route('filament.dashboard.resources.customs.index'),               'theme' => 'from-violet-500 to-violet-600',  'badge' => 'bg-violet-500'],
        ['id' => 'categories',       'searchable' => false, 'heroicon' => 'heroicon-o-tag',                      'label' => __('dashboard/strings.resources.categories'),             'route' => route('filament.dashboard.resources.categories.index'),            'theme' => 'from-slate-500 to-slate-600',    'badge' => 'bg-slate-500'],
        ['id' => 'products',         'searchable' => false, 'heroicon' => 'heroicon-o-cube',                     'label' => __('dashboard/strings.resources.products'),               'route' => route('filament.dashboard.resources.products.index'),              'theme' => 'from-sky-500 to-sky-600',        'badge' => 'bg-sky-500'],
        ['id' => 'companies',        'searchable' => false, 'heroicon' => 'heroicon-o-building-storefront',      'label' => __('dashboard/strings.resources.companies'),              'route' => route('filament.dashboard.resources.companies.index'),             'theme' => 'from-teal-500 to-teal-600',      'badge' => 'bg-teal-500'],
        ['id' => 'banks',            'searchable' => false, 'heroicon' => 'heroicon-o-building-library',         'label' => __('dashboard/strings.resources.banks'),                  'route' => route('filament.dashboard.resources.banks.index'),                 'theme' => 'from-lime-500 to-lime-600',      'badge' => 'bg-lime-500'],
        ['id' => 'currencies',       'searchable' => false, 'heroicon' => 'heroicon-o-currency-dollar',          'label' => __('dashboard/strings.resources.currencies'),             'route' => route('filament.dashboard.resources.currencies.index'),            'theme' => 'from-emerald-500 to-emerald-600','badge' => 'bg-emerald-500'],
        ['id' => 'statuses',         'searchable' => false, 'heroicon' => 'heroicon-o-flag',                     'label' => __('dashboard/strings.resources.statuses'),               'route' => route('filament.dashboard.resources.statuses.index'),              'theme' => 'from-rose-500 to-rose-600',      'badge' => 'bg-rose-500'],
        ['id' => 'notifications',    'searchable' => false, 'heroicon' => 'heroicon-o-bell',                     'label' => __('dashboard/strings.resources.notification_settings'),  'route' => route('filament.dashboard.resources.notification-settings.index'), 'theme' => 'from-yellow-500 to-yellow-600',  'badge' => 'bg-yellow-500'],
    ];

    foreach ($modules as $i => $m) {
        $modules[$i]['icon'] = svg($m['heroicon'], 'w-full h-full')->toHtml();
        unset($modules[$i]['heroicon']);
    }

    $counts = $counts ?? [];
    $stats  = [
        'purchaseRequests' => (int) ($counts['purchase_requests'] ?? 0),
        'proformaInvoices' => (int) ($counts['proforma_invoices'] ?? 0),
        'registeredOrders' => (int) ($counts['registered_orders'] ?? 0),
        'bankProfiles'     => (int) ($counts['bank_profiles']     ?? 0),
        'purchaseOrders'   => (int) ($counts['purchase_orders']   ?? 0),
        'payments'         => (int) ($counts['payments']          ?? 0),
        'shipments'        => (int) ($counts['shipments']         ?? 0),
        'customs'          => (int) ($counts['customs']           ?? 0),
    ];

    $workspaceConfig = [
        'modules'    => array_values($modules),
        'stats'      => $stats,
        'recordsUrl' => url('/workspace/records/__RES__'),
    ];

    /* ---- shells ---- */
    $accordionShell = 'glass border rounded-2xl overflow-hidden shadow-lg';
    $accordionTone  = "darkMode ? 'border-white/10' : 'border-slate-200'";
    $panelShell     = 'rounded-xl border overflow-hidden';
    $panelTone      = "darkMode ? 'border-white/10 bg-white/[0.03]' : 'border-slate-200 bg-white'";
    $panelHead      = 'flex items-center gap-3 px-5 py-4 border-b';
    $panelHeadTone  = "darkMode ? 'border-white/10 bg-white/[0.03]' : 'border-slate-100 bg-gradient-to-r from-indigo-50/80 to-transparent'";
    $listShell      = 'mt-1.5 rounded-lg border overflow-hidden';
    $listTone       = "darkMode ? 'border-white/10 bg-slate-900/40' : 'border-slate-200 bg-slate-50/60'";
    $tileShell      = 'flex items-center gap-3 rounded-lg border p-3 pr-10 transition-colors';
    $tileTone       = "darkMode ? 'border-white/10 bg-slate-800/40 hover:bg-slate-800/70' : 'border-slate-200 bg-white hover:bg-slate-50'";
    $chipBase       = 'group inline-flex items-center gap-2 rounded-full border py-1 pl-1 pr-3 text-xs font-semibold transition-colors !cursor-pointer';
    $chipTone       = "darkMode ? 'border-white/10 bg-white/5 text-slate-300 hover:bg-white/10' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'";
    $labelTone      = "darkMode ? 'text-slate-500' : 'text-slate-400'";
    $removeBtn      = 'absolute top-1/2 -translate-y-1/2 right-2 w-6 h-6 rounded-md flex items-center justify-center opacity-0 group-hover:opacity-100 transition-colors !cursor-pointer';
    $removeTone     = "darkMode ? 'text-slate-400 hover:bg-rose-500/20 hover:text-rose-300' : 'text-slate-400 hover:bg-rose-50 hover:text-rose-500'";
    /* ---- new helpers ---- */
    $sectionLabel   = 'text-[11px] font-bold uppercase tracking-wider mb-2 flex items-center gap-1.5';
    $ctaBtn         = 'inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-bold transition-colors !cursor-pointer';
    $ctaBtnTone     = "darkMode ? 'bg-indigo-500/15 text-indigo-300 hover:bg-indigo-500/25' : 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100'";
    $editBtn        = 'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold border transition-colors !cursor-pointer';
    $editBtnTone    = "darkMode ? 'border-white/10 text-slate-400 bg-white/5 hover:bg-white/10 hover:text-slate-200' : 'border-slate-200 text-slate-500 bg-white hover:bg-slate-50 hover:text-slate-700'";
    $closeIconTone  = "darkMode ? 'text-slate-500 hover:bg-white/10 hover:text-slate-300' : 'text-slate-400 hover:bg-slate-100 hover:text-slate-600'";
@endphp

<div x-data="{ ...workspace({{ json_encode($workspaceConfig) }}), showModulePicker: false, showRecordPicker: false }"
     class="mb-12 relative z-20 space-y-6">

    {{-- ============================== MODULES ============================== --}}
    <div class="{{ $accordionShell }}" :class="{{ $accordionTone }}">
        <button type="button" @click="modulesOpen = !modulesOpen"
                class="w-full px-6 py-5 flex items-center justify-between group !cursor-pointer">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md group-hover:scale-105 transition-transform flex-shrink-0">
                    <x-heroicon-o-squares-2x2 class="w-6 h-6"/>
                </div>
                <div class="text-left">
                    <h2 class="text-lg sm:text-xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.workspace_modules') }}
                    </h2>
                    <p class="text-xs sm:text-sm" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                        <span x-show="pinnedModuleIds.length" x-cloak>
                            <span x-text="pinnedModuleIds.length"></span> {{ __('dashboard/strings.pinned_suffix') }}
                        </span>
                    </p>
                </div>
            </div>
            <div class="p-1.5 rounded-full border"
                 :class="darkMode ? 'text-slate-400 bg-white/5 border-white/10' : 'text-slate-500 bg-slate-100 border-slate-200'">
                <svg class="w-5 h-5 transition-transform duration-300" :class="modulesOpen ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </button>

        <div x-show="modulesOpen" x-collapse x-cloak>
            <div class="px-6 pb-6 pt-2 border-t" :class="darkMode ? 'border-white/5' : 'border-slate-200'">
                <div class="{{ $panelShell }}" :class="{{ $panelTone }}">
                    <div class="p-4 sm:p-5">

                        {{-- Pinned grid (always visible when pins exist) --}}
                        <div x-show="pinnedModuleIds.length" x-cloak class="mb-4">
                            <p class="{{ $sectionLabel }}" :class="{{ $labelTone }}">{{ __('dashboard/strings.section_pinned') }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <template x-for="m in pinnedModules()" :key="m.id">
                                    <div class="relative group">
                                        <a :href="m.route" target="_blank" rel="noopener noreferrer"
                                           class="{{ $tileShell }}" :class="{{ $tileTone }}">
                                            <span class="w-9 h-9 rounded-md flex items-center justify-center text-white bg-gradient-to-br shadow-sm flex-shrink-0"
                                                  :class="m.theme">
                                                <span class="w-4 h-4" x-html="m.icon"></span>
                                            </span>
                                            <span class="font-bold text-sm truncate flex-1"
                                                  :class="darkMode ? 'text-slate-100' : 'text-slate-800'"
                                                  x-text="m.label"></span>
                                        </a>
                                        <template x-if="moduleStat(m.id)">
                                            <span class="absolute top-1/2 -translate-y-1/2 right-2 text-white text-[10px] font-bold rounded-full min-w-[20px] h-5 px-1 flex items-center justify-center shadow-sm transition-opacity group-hover:opacity-0 pointer-events-none"
                                                  :class="m.badge" x-text="moduleStat(m.id)"></span>
                                        </template>
                                        <button type="button" @click="unpinModule(m.id)"
                                                class="{{ $removeBtn }}" :class="{{ $removeTone }}"
                                                title="{{ __('dashboard/strings.record_pin.added') }}">
                                            <x-heroicon-o-x-mark class="w-4 h-4"/>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Empty state --}}
                        <div x-show="!pinnedModuleIds.length && !showModulePicker" x-cloak
                             class="flex flex-col items-center gap-3 py-8 text-center">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                                 :class="darkMode ? 'bg-white/5 text-slate-500' : 'bg-slate-100 text-slate-400'">
                                <x-heroicon-o-squares-2x2 class="w-5 h-5"/>
                            </div>
                            <p class="text-sm font-semibold" :class="darkMode ? 'text-slate-300' : 'text-slate-600'">
                                {{ __('dashboard/strings.workspace_modules_hint') }}
                            </p>
                            <button type="button" @click="showModulePicker = true"
                                    class="{{ $ctaBtn }}" :class="{{ $ctaBtnTone }}">
                                <x-heroicon-o-plus class="w-4 h-4"/>
                                {{ __('dashboard/strings.section_add') }}
                            </button>
                        </div>

                        {{-- Chip picker --}}
                        <div x-show="showModulePicker" x-cloak>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-bold uppercase tracking-wider" :class="{{ $labelTone }}">
                                    {{ __('dashboard/strings.section_add') }}
                                </span>
                                <button type="button" @click="showModulePicker = false"
                                        class="w-7 h-7 rounded-md flex items-center justify-center transition-colors !cursor-pointer"
                                        :class="{{ $closeIconTone }}">
                                    <x-heroicon-o-x-mark class="w-4 h-4"/>
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="m in unpinnedModules()" :key="m.id">
                                    <button type="button" @click="pinModule(m.id)"
                                            class="{{ $chipBase }}" :class="{{ $chipTone }}">
                                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-white bg-gradient-to-br shadow-sm transition-transform group-hover:scale-105 flex-shrink-0"
                                              :class="m.theme">
                                            <span class="w-3.5 h-3.5" x-html="m.icon"></span>
                                        </span>
                                        <span x-text="m.label"></span>
                                        <x-heroicon-o-plus class="w-3.5 h-3.5 opacity-50"/>
                                    </button>
                                </template>
                            </div>
                            <p x-show="!unpinnedModules().length" x-cloak
                               class="text-xs" :class="darkMode ? 'text-slate-500' : 'text-slate-400'">
                                {{ __('dashboard/strings.all_pinned') }}
                            </p>
                        </div>

                        {{-- Edit button --}}
                        <div x-show="pinnedModuleIds.length && !showModulePicker" x-cloak
                             class="flex justify-end mt-3">
                            <button type="button" @click="showModulePicker = true"
                                    class="{{ $editBtn }}" :class="{{ $editBtnTone }}">
                                <x-heroicon-o-pencil-square class="w-3.5 h-3.5"/>
                                {{ __('dashboard/strings.section_add') }}
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================== RECORDS ============================== --}}
    <div class="{{ $accordionShell }}" :class="{{ $accordionTone }}">
        <button type="button" @click="recordsOpen = !recordsOpen"
                class="w-full px-6 py-5 flex items-center justify-between group !cursor-pointer">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-lg bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shadow-md group-hover:scale-105 transition-transform flex-shrink-0">
                    <x-heroicon-o-bookmark class="w-6 h-6"/>
                </div>
                <div class="text-left">
                    <h2 class="text-lg sm:text-xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ __('dashboard/strings.workspace_records') }}
                    </h2>
                    <p class="text-xs sm:text-sm" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                        <span x-show="recordPins.length" x-cloak>
                            <span x-text="recordPins.length"></span> {{ __('dashboard/strings.pinned_suffix') }}
                        </span>
                    </p>
                </div>
            </div>
            <div class="p-1.5 rounded-full border"
                 :class="darkMode ? 'text-slate-400 bg-white/5 border-white/10' : 'text-slate-500 bg-slate-100 border-slate-200'">
                <svg class="w-5 h-5 transition-transform duration-300" :class="recordsOpen ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </div>
        </button>

        <div x-show="recordsOpen"  x-cloak>
            <div class="px-6 pb-6 pt-2 border-t" :class="darkMode ? 'border-white/5' : 'border-slate-200'">
                <div class="{{ $panelShell }}" :class="{{ $panelTone }}">
                    <div class="p-4 sm:p-5">

                        {{-- Pinned records grid --}}
                        <div x-show="recordPins.length" x-cloak class="mb-4">
                            <p class="{{ $sectionLabel }}" :class="{{ $labelTone }}">{{ __('dashboard/strings.section_pinned') }}</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <template x-for="p in recordPins" :key="p.key">
                                    <div class="relative group">
                                        <a :href="p.url" target="_blank" rel="noopener noreferrer"
                                           class="{{ $tileShell }}" :class="{{ $tileTone }}">
                                            <span class="w-9 h-9 rounded-md flex items-center justify-center text-white bg-gradient-to-br shadow-sm flex-shrink-0"
                                                  :class="p.theme">
                                                <span class="w-4 h-4" x-html="p.icon"></span>
                                            </span>
                                            <span class="flex-1 min-w-0">
                                                <span class="block font-bold text-sm truncate"
                                                      :class="darkMode ? 'text-slate-100' : 'text-slate-800'"
                                                      x-text="p.label"></span>
                                                <span x-show="p.subtitle" class="block text-xs truncate mt-0.5"
                                                      :class="darkMode ? 'text-slate-400' : 'text-slate-500'"
                                                      x-text="p.subtitle"></span>
                                            </span>
                                        </a>
                                        <button type="button" @click="removeRecord(p.key)"
                                                class="{{ $removeBtn }}" :class="{{ $removeTone }}"
                                                title="{{ __('dashboard/strings.record_pin.added') }}">
                                            <x-heroicon-o-x-mark class="w-4 h-4"/>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Empty state --}}
                        <div x-show="!recordPins.length && !showRecordPicker" x-cloak
                             class="flex flex-col items-center gap-3 py-8 text-center">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center"
                                 :class="darkMode ? 'bg-white/5 text-slate-500' : 'bg-slate-100 text-slate-400'">
                                <x-heroicon-o-bookmark class="w-5 h-5"/>
                            </div>
                            <p class="text-sm font-semibold" :class="darkMode ? 'text-slate-300' : 'text-slate-600'">
                                {{ __('dashboard/strings.workspace_records_hint') }}
                            </p>
                            <button type="button" @click="showRecordPicker = true"
                                    class="{{ $ctaBtn }}" :class="{{ $ctaBtnTone }}">
                                <x-heroicon-o-plus class="w-4 h-4"/>
                                {{ __('dashboard/strings.section_add') }}
                            </button>
                        </div>

                        {{-- Record picker --}}
                        <div x-show="showRecordPicker" x-cloak>
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-[11px] font-bold uppercase tracking-wider" :class="{{ $labelTone }}">
                                    {{ __('dashboard/strings.section_add') }}
                                </span>
                                <button type="button" @click="showRecordPicker = false"
                                        class="w-7 h-7 rounded-md flex items-center justify-center transition-colors !cursor-pointer"
                                        :class="{{ $closeIconTone }}">
                                    <x-heroicon-o-x-mark class="w-4 h-4"/>
                                </button>
                            </div>

                            <div class="flex flex-wrap gap-2 mb-3">
                                <template x-for="m in searchableModules()" :key="m.id">
                                    <button type="button" @click="selectResource(m.id)"
                                            class="{{ $chipBase }}"
                                            :class="pickerResource === m.id
                                                ? (darkMode ? 'border-indigo-400/50 bg-indigo-500/15 text-white' : 'border-indigo-300 bg-indigo-50 text-indigo-700')
                                                : ({{ $chipTone }})">
                                        <span class="w-6 h-6 rounded-full flex items-center justify-center text-white bg-gradient-to-br shadow-sm transition-transform group-hover:scale-105 flex-shrink-0"
                                              :class="m.theme">
                                            <span class="w-3.5 h-3.5" x-html="m.icon"></span>
                                        </span>
                                        <span x-text="m.label"></span>
                                    </button>
                                </template>
                            </div>

                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none"
                                     :class="darkMode ? 'text-slate-500' : 'text-slate-400'">
                                    <x-heroicon-o-magnifying-glass class="w-5 h-5"/>
                                </div>
                                <input type="text" x-model="recordQuery"
                                       @input.debounce.300ms="searchRecords()"
                                       @focus="recordResults.length === 0 && searchRecords()"
                                       @keydown.escape.stop="recordQuery = ''; searchRecords()"
                                       placeholder="{{ __('dashboard/strings.record_pin.placeholder') }}"
                                       class="w-full rounded-lg border pl-11 pr-10 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/40 transition-colors"
                                       :class="darkMode ? 'bg-slate-800/70 border-white/10 text-slate-200 focus:bg-slate-800 focus:border-indigo-400/60' : 'bg-slate-50 border-slate-200 text-slate-700 focus:bg-white focus:border-indigo-400'">
                                <button type="button" x-show="recordQuery" x-cloak
                                        @click="recordQuery = ''; searchRecords()"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 transition-colors !cursor-pointer"
                                        :class="darkMode ? 'text-slate-500 hover:text-slate-300' : 'text-slate-400 hover:text-slate-600'">
                                    <x-heroicon-o-x-circle class="w-5 h-5"/>
                                </button>
                            </div>

                            <div class="flex items-center justify-between px-1 mt-2 text-[11px] font-semibold"
                                 :class="darkMode ? 'text-slate-500' : 'text-slate-400'">
                                <span>{{ __('dashboard/strings.record_pin.tap') }}</span>
                                <span x-show="!recordLoading && recordResults.length" x-cloak>
                                    <span x-text="recordResults.length"></span> {{ __('dashboard/strings.record_pin.found') }}
                                </span>
                            </div>

                            <div class="{{ $listShell }}" :class="{{ $listTone }}">
                                <div class="max-h-64 overflow-y-auto custom-scrollbar">
                                    <template x-if="recordLoading">
                                        <div class="p-2 space-y-1">
                                            <template x-for="i in 3" :key="i">
                                                <div class="flex items-center gap-3 px-3 py-2.5 animate-pulse">
                                                    <div class="w-9 h-9 rounded-md flex-shrink-0"
                                                         :class="darkMode ? 'bg-white/10' : 'bg-slate-200'"></div>
                                                    <div class="flex-1 space-y-2">
                                                        <div class="h-2.5 rounded-full w-1/3" :class="darkMode ? 'bg-white/10' : 'bg-slate-200'"></div>
                                                        <div class="h-2 rounded-full w-1/2"   :class="darkMode ? 'bg-white/5'  : 'bg-slate-100'"></div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="!recordLoading && recordError">
                                        <div class="px-4 py-8 text-center">
                                            <p class="text-sm font-semibold text-rose-500">{{ __('dashboard/strings.record_pin.error') }}</p>
                                            <button type="button" @click="searchRecords()"
                                                    class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-indigo-500 hover:text-indigo-600 transition-colors !cursor-pointer">
                                                <x-heroicon-o-arrow-path class="w-3.5 h-3.5"/>
                                                {{ __('dashboard/strings.record_pin.retry') }}
                                            </button>
                                        </div>
                                    </template>

                                    <template x-if="!recordLoading && !recordError && recordResults.length === 0">
                                        <div class="px-4 py-8 text-center flex flex-col items-center gap-2">
                                            <div class="w-11 h-11 rounded-lg flex items-center justify-center"
                                                 :class="darkMode ? 'bg-white/5 text-slate-500' : 'bg-slate-100 text-slate-400'">
                                                <x-heroicon-o-magnifying-glass class="w-5 h-5"/>
                                            </div>
                                            <p class="text-sm font-semibold" :class="darkMode ? 'text-slate-300' : 'text-slate-600'">
                                                {{ __('dashboard/strings.record_pin.empty') }}
                                            </p>
                                            <p class="text-xs" :class="darkMode ? 'text-slate-500' : 'text-slate-400'">
                                                {{ __('dashboard/strings.record_pin.empty_hint') }}
                                            </p>
                                        </div>
                                    </template>

                                    <template x-if="!recordLoading && !recordError && recordResults.length > 0">
                                        <ul class="divide-y" :class="darkMode ? 'divide-white/5' : 'divide-slate-100'">
                                            <template x-for="rec in recordResults" :key="rec.key">
                                                <li class="group flex items-center gap-3 px-3 py-2.5 transition-colors"
                                                    :class="darkMode ? 'hover:bg-white/5' : 'hover:bg-white'">
                                                    <span :class="['flex items-center justify-center w-9 h-9 rounded-md text-white text-[11px] font-extrabold tracking-tight bg-gradient-to-br shadow-sm flex-shrink-0', pickerTheme]"
                                                          x-text="initials(rec.label)"></span>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="font-semibold text-sm truncate"
                                                           :class="darkMode ? 'text-slate-100' : 'text-slate-800'"
                                                           x-text="rec.label"></p>
                                                        <p x-show="rec.subtitle" class="text-xs truncate"
                                                           :class="darkMode ? 'text-slate-400' : 'text-slate-500'"
                                                           x-text="rec.subtitle"></p>
                                                    </div>
                                                    <button type="button" @click="addRecord(rec)"
                                                            :disabled="isRecordPinned(rec.key)"
                                                            class="flex-shrink-0 inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold transition-colors disabled:cursor-default !cursor-pointer"
                                                            :class="isRecordPinned(rec.key)
                                                                ? (darkMode ? 'bg-emerald-500/15 text-emerald-400' : 'bg-emerald-50 text-emerald-600')
                                                                : (darkMode ? 'bg-indigo-500/15 text-indigo-300 hover:bg-indigo-500/25' : 'bg-indigo-50 text-indigo-600 hover:bg-indigo-100')">
                                                        <span x-show="!isRecordPinned(rec.key)" class="inline-flex items-center gap-1">
                                                            <x-heroicon-o-plus class="w-3.5 h-3.5"/>
                                                            {{ __('dashboard/strings.record_pin.add') }}
                                                        </span>
                                                        <span x-show="isRecordPinned(rec.key)" class="inline-flex items-center gap-1">
                                                            <x-heroicon-o-check class="w-3.5 h-3.5"/>
                                                            {{ __('dashboard/strings.record_pin.added') }}
                                                        </span>
                                                    </button>
                                                </li>
                                            </template>
                                        </ul>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Edit button --}}
                        <div x-show="recordPins.length && !showRecordPicker" x-cloak
                             class="flex justify-end mt-3">
                            <button type="button" @click="showRecordPicker = true"
                                    class="{{ $editBtn }}" :class="{{ $editBtnTone }}">
                                <x-heroicon-o-pencil-square class="w-3.5 h-3.5"/>
                                {{ __('dashboard/strings.section_add') }}
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
