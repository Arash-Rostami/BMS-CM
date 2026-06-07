<div class="fixed top-8 left-1/2 -translate-x-1/2 z-50 flex items-center bg-slate-900/70 backdrop-blur-md rounded-full p-1 border border-white/10 shadow-2xl">
    <button @click="activeTab = 'customize'"
            class="px-6 py-2 rounded-full text-sm font-semibold transition-all duration-300 cursor-pointer"
            :class="activeTab === 'customize' ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/30' : 'text-slate-400 hover:text-white'">
        {{ __('dashboard/strings.customize') ?? 'Customize' }}
    </button>
    <button @click="activeTab = 'workflow'"
            class="px-6 py-2 rounded-full text-sm font-semibold transition-all duration-300 cursor-pointer"
            :class="activeTab === 'workflow' ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/30' : 'text-slate-400 hover:text-white'">
        {{ __('dashboard/strings.workflow') ?? 'Workflow' }}
    </button>
    <button @click="activeTab = 'search'"
            class="px-6 py-2 rounded-full text-sm font-semibold transition-all duration-300 cursor-pointer"
            :class="activeTab === 'search' ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/30' : 'text-slate-400 hover:text-white'">
        {{ __('dashboard/strings.search') ?? 'Search' }}
    </button>
</div>
