<div class="mb-6 sm:mb-8">
    <div class="glass border border-white/10 rounded-2xl sm:rounded-3xl shadow-2xl overflow-hidden card-3d">
        <a href="{{ route('filament.dashboard.pages.dashboard') }}" target="_blank" rel="noopener noreferrer" class="block group relative">
            <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>
            <div class="flex items-center justify-between relative z-10 gap-3 px-4 sm:px-6 py-3 sm:py-4">
                <div class="flex items-center gap-3 sm:gap-4 min-w-0 flex-1">
                    <div class="w-10 h-10 sm:w-12 sm:h-12 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-xl flex items-center justify-center icon-container shadow-lg flex-shrink-0">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    <p class="text-sm truncate" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                        {{ __('dashboard/strings.return_to_main_panel') }}
                    </p>
                </div>
                <img src="{{ asset('img/logos/curves.png') }}" class="w-28 h-auto opacity-90" alt="logo">
            </div>
        </a>
        <div class="px-4 py-4 flex justify-center bg-slate-50/50 dark:bg-slate-900/20 border-t border-slate-100 dark:border-white/5">
            <div class="inline-flex items-center p-1.5 rounded-2xl bg-slate-200/50 dark:bg-white/5 border border-slate-300/50 dark:border-white/10 shadow-inner backdrop-blur-md gap-1">
                <button @click="activeTab = 'customize'"
                        class="flex items-center gap-2.5 px-6 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 ease-out cursor-pointer group"
                        :class="activeTab === 'customize' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-md ring-1 ring-slate-900/5 dark:ring-white/10' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-200/30 dark:hover:bg-white/5'">
                    <x-heroicon-o-wrench-screwdriver class="w-4.5 h-4.5 flex-shrink-0 transition-transform group-hover:rotate-12"/>
                    <span>{{ __('dashboard/strings.customize') ?? 'Customize' }}</span>
                </button>
                <button @click="activeTab = 'workflow'"
                        class="flex items-center gap-2.5 px-6 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 ease-out cursor-pointer group"
                        :class="activeTab === 'workflow' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-md ring-1 ring-slate-900/5 dark:ring-white/10' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-200/30 dark:hover:bg-white/5'">
                    <x-heroicon-o-arrow-trending-up class="w-4.5 h-4.5 flex-shrink-0 transition-transform group-hover:-translate-y-0.5"/>
                    <span>{{ __('dashboard/strings.workflow') ?? 'Workflow' }}</span>
                </button>
                <button @click="activeTab = 'search'; $nextTick(() => $dispatch('tab-search-focus'))"
                        class="flex items-center gap-2.5 px-6 py-2.5 rounded-xl text-sm font-bold transition-all duration-300 ease-out cursor-pointer group"
                        :class="activeTab === 'search' ? 'bg-white dark:bg-slate-800 text-indigo-600 dark:text-indigo-400 shadow-md ring-1 ring-slate-900/5 dark:ring-white/10' : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-200/30 dark:hover:bg-white/5'">
                    <x-heroicon-o-magnifying-glass class="w-4.5 h-4.5 flex-shrink-0 transition-transform group-hover:scale-110"/>
                    <span>{{ __('dashboard/strings.search') ?? 'Search' }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
