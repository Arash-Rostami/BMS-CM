<div class="flex justify-center my-6">
    <div class="flex items-center bg-white/[0.04] backdrop-blur-xl rounded-xl p-1 border border-white/10 shadow-2xl gap-0.5">
        <button @click="activeTab = 'customize'"
                class="flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 cursor-pointer"
                :class="activeTab === 'customize'
                    ? 'bg-gradient-to-r from-indigo-600/25 to-cyan-600/15 text-indigo-300 border border-indigo-500/30 shadow-[0_0_18px_rgba(99,102,241,0.2)]'
                    : 'text-slate-400 hover:text-slate-200 hover:bg-white/5 border border-transparent'">
            <x-heroicon-o-wrench-screwdriver class="w-4 h-4 flex-shrink-0"/>
            <span>{{ __('dashboard/strings.customize') ?? 'Customize' }}</span>
        </button>
        <button @click="activeTab = 'workflow'"
                class="flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 cursor-pointer"
                :class="activeTab === 'workflow'
                    ? 'bg-gradient-to-r from-indigo-600/25 to-cyan-600/15 text-indigo-300 border border-indigo-500/30 shadow-[0_0_18px_rgba(99,102,241,0.2)]'
                    : 'text-slate-400 hover:text-slate-200 hover:bg-white/5 border border-transparent'">
            <x-heroicon-o-arrow-trending-up class="w-4 h-4 flex-shrink-0"/>
            <span>{{ __('dashboard/strings.workflow') ?? 'Workflow' }}</span>
        </button>
        <button @click="activeTab = 'search'; $nextTick(() => $dispatch('tab-search-focus'))"
                class="flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 cursor-pointer"
                :class="activeTab === 'search'
                    ? 'bg-gradient-to-r from-indigo-600/25 to-cyan-600/15 text-indigo-300 border border-indigo-500/30 shadow-[0_0_18px_rgba(99,102,241,0.2)]'
                    : 'text-slate-400 hover:text-slate-200 hover:bg-white/5 border border-transparent'">
            <x-heroicon-o-magnifying-glass class="w-4 h-4 flex-shrink-0"/>
            <span>{{ __('dashboard/strings.search') ?? 'Search' }}</span>
        </button>
    </div>
</div>
