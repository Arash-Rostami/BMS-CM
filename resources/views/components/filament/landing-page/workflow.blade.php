<div x-data="shortcutManager(@js($stats))" x-init="init()" class="mb-12">
    <!-- Header & Action -->
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-2xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
            {{ __('dashboard/strings.workspace') ?? 'Workspace' }}
        </h2>
        <button @click="isEditing = true"
                class="p-2 rounded-full hover:bg-slate-200/50 dark:hover:bg-white/10 transition-colors"
                :class="darkMode ? 'text-slate-300' : 'text-slate-600'"
                title="Edit Shortcuts">
            <x-heroicon-o-pencil-square class="w-6 h-6" />
        </button>
    </div>

    <!-- Grid Tracks -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <template x-for="(shortcut, index) in activeShortcuts" :key="shortcut.id">
            <div class="card-3d h-full">
                <div class="glass border border-white/10 rounded-2xl p-5 relative overflow-hidden group shadow-xl h-full flex flex-col justify-between">
                    <div class="absolute -top-10 -right-10 w-32 h-32 rounded-full glow-orb opacity-50"
                         :class="shortcut.colorClass"></div>
                    <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>

                    <div class="relative z-10">
                        <div class="flex items-start justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center group-hover:scale-110 group-hover:-rotate-6 transition-all duration-300 shadow-lg text-white"
                                 :class="shortcut.bgGradient">
                                <div x-html="shortcut.icon" class="w-6 h-6"></div>
                            </div>
                            <span x-show="shortcut.count !== undefined"
                                  class="text-xs font-bold px-2 py-1 rounded-full shadow-sm"
                                  :class="shortcut.badgeClass"
                                  x-text="shortcut.count"></span>
                        </div>

                        <h3 class="text-lg font-semibold mb-1" :class="darkMode ? 'text-white' : 'text-slate-900'" x-text="shortcut.title"></h3>
                        <p class="text-sm opacity-80 mb-4 line-clamp-2" :class="darkMode ? 'text-slate-400' : 'text-slate-600'" x-text="shortcut.description"></p>
                    </div>

                    <div class="relative z-10 mt-auto">
                        <a :href="shortcut.url" target="_blank" rel="noopener noreferrer"
                           class="btn-gradient block w-full px-4 py-2.5 rounded-xl font-semibold text-center text-white text-sm transition-transform active:scale-95"
                           :class="shortcut.btnGradient">
                            <span x-text="shortcut.actionText"></span>
                        </a>
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- Edit Overlay / Modal -->
    <div x-show="isEditing" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">

        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl max-w-2xl w-full mx-4 max-h-[85vh] flex flex-col"
             @click.away="saveShortcuts">

            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold" :class="darkMode ? 'text-white' : 'text-slate-900'">
                    {{ __('dashboard/strings.customize_workspace') ?? 'Customize Workspace' }}
                </h3>
                <button @click="saveShortcuts" class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-white/10 transition-colors">
                    <x-heroicon-o-x-mark class="w-6 h-6" :class="darkMode ? 'text-slate-300' : 'text-slate-500'" />
                </button>
            </div>

            <div class="overflow-y-auto pr-2 custom-scrollbar flex-1 space-y-3">
                <template x-for="module in moduleLibrary" :key="module.id">
                    <div class="flex items-center justify-between p-4 rounded-2xl border transition-colors cursor-pointer"
                         :class="selectedIds.includes(module.id)
                            ? (darkMode ? 'border-indigo-500/50 bg-indigo-500/10' : 'border-indigo-500 bg-indigo-50')
                            : (darkMode ? 'border-white/10 hover:border-white/20' : 'border-slate-200 hover:border-slate-300')"
                         @click="toggleSelection(module.id)">

                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center text-white" :class="module.bgGradient">
                                <div x-html="module.icon" class="w-5 h-5"></div>
                            </div>
                            <div>
                                <div class="font-semibold text-sm sm:text-base" :class="darkMode ? 'text-white' : 'text-slate-900'" x-text="module.title"></div>
                                <div class="text-xs sm:text-sm" :class="darkMode ? 'text-slate-400' : 'text-slate-500'" x-text="module.description"></div>
                            </div>
                        </div>

                        <div class="w-6 h-6 rounded-full border-2 flex items-center justify-center transition-colors flex-shrink-0"
                             :class="selectedIds.includes(module.id)
                                ? 'bg-indigo-500 border-indigo-500'
                                : (darkMode ? 'border-slate-600' : 'border-slate-300')">
                            <svg x-show="selectedIds.includes(module.id)" class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-6 flex justify-end gap-3 pt-4 border-t" :class="darkMode ? 'border-white/10' : 'border-slate-200'">
                <button @click="resetToDefault"
                        class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-colors"
                        :class="darkMode ? 'text-slate-300 hover:bg-white/5' : 'text-slate-600 hover:bg-slate-100'">
                    {{ __('dashboard/strings.reset_to_default') ?? 'Reset to Default' }}
                </button>
                <button @click="saveShortcuts"
                        class="px-5 py-2.5 rounded-xl text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white transition-colors shadow-lg shadow-indigo-500/30">
                    {{ __('dashboard/strings.save_changes') ?? 'Save Changes' }}
                </button>
            </div>
        </div>
    </div>
</div>
