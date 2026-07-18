<div class="mb-6 sm:mb-8">
    <div class="lp-surface rounded-lg overflow-hidden">

        <div class="flex items-center justify-between gap-3 px-4 sm:px-6 py-2.5 border-b lp-divider">
            <a href="{{ route('filament.dashboard.pages.dashboard') }}" target="_blank" rel="noopener noreferrer"
               class="flex items-center gap-2.5 min-w-0 group">
                <div class="w-5 h-5 rounded flex items-center justify-center flex-shrink-0"
                     :class="darkMode ? 'bg-primary-400/10' : 'bg-primary-50'">
                    <svg class="w-3 h-3" :class="darkMode ? 'text-primary-400' : 'text-primary-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                </div>
                <span class="text-xs truncate font-medium text-slate-500 dark:text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-200">
                    {{ __('dashboard/strings.return_to_main_panel') }}
                </span>
            </a>
            <img src="{{ asset('img/logos/curves.png') }}" class="w-16 sm:w-20 h-auto opacity-60" alt="logo">
        </div>

        <div class="fi-tabs flex items-center gap-1 px-2 sm:px-4 overflow-x-auto">
            <button @click="activeTab = 'customize'"
                    class="lp-tab flex items-center gap-2 px-3 sm:px-4 py-2.5 text-sm whitespace-nowrap"
                    :class="activeTab === 'customize' ? 'lp-tab-active' : ''">
                <x-heroicon-o-wrench-screwdriver class="w-4 h-4 flex-shrink-0"/>
                <span>{{ __('dashboard/strings.customize') ?? 'Customize' }}</span>
            </button>
            <button @click="activeTab = 'workflow'"
                    class="lp-tab flex items-center gap-2 px-3 sm:px-4 py-2.5 text-sm whitespace-nowrap"
                    :class="activeTab === 'workflow' ? 'lp-tab-active' : ''">
                <x-heroicon-o-arrow-trending-up class="w-4 h-4 flex-shrink-0"/>
                <span>{{ __('dashboard/strings.workflow') ?? 'Workflow' }}</span>
            </button>
            <button @click="activeTab = 'search'; $nextTick(() => $dispatch('tab-search-focus'))"
                    class="lp-tab flex items-center gap-2 px-3 sm:px-4 py-2.5 text-sm whitespace-nowrap"
                    :class="activeTab === 'search' ? 'lp-tab-active' : ''">
                <x-heroicon-o-magnifying-glass class="w-4 h-4 flex-shrink-0"/>
                <span>{{ __('dashboard/strings.search') ?? 'Search' }}</span>
            </button>
        </div>
    </div>
</div>
