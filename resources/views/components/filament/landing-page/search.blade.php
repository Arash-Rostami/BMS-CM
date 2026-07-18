<div class="flex flex-col items-center justify-center h-full w-full pt-6"
     x-data="search"
     @tab-search-focus.window="$nextTick(() => $refs.searchInput?.focus())">

    <div class="w-full max-w-3xl relative mb-6">
        <div class="absolute inset-y-0 {{ $isRtl ? 'right-5' : 'left-5' }} flex items-center pointer-events-none">
            <x-heroicon-o-magnifying-glass class="w-5 h-5 text-slate-500"/>
        </div>
        <input type="text"
               x-ref="searchInput"
               x-model="searchQuery"
               @input.debounce.500ms="performSearch"
               class="lp-surface w-full {{ $isRtl ? 'pr-14 pl-36' : 'pl-14 pr-36' }} py-4 text-slate-800 dark:text-white placeholder-slate-500 dark:placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-primary-500/40 text-sm"
               placeholder="{{ __('dashboard/strings.search_spotlight') ?? 'Search records (Cmd/Ctrl+K)…' }}">
        <div class="absolute inset-y-0 {{ $isRtl ? 'left-4' : 'right-4' }} flex items-center pointer-events-none gap-1.5">
            <kbd class="hidden sm:inline-flex items-center gap-1 border border-slate-200 dark:border-white/10 bg-slate-50 dark:bg-white/5 rounded-md px-2 py-1 text-[11px] text-slate-500 font-mono">
                <span class="text-[10px]">⌘</span>K
            </kbd>
        </div>
    </div>

    <div x-show="searchQuery.length >= 2"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="lp-surface flex flex-wrap items-center justify-center gap-y-4 mb-5 w-full max-w-7xl py-4 px-6">

        <template x-for="stage in breadcrumbStages()" :key="stage.key">
            <div class="flex items-center">
                <div class="relative z-10 flex items-center gap-2 px-3 py-1.5 rounded-full border shadow-sm"
                     :class="{
                     'bg-green-50 border-green-200 text-green-700 dark:bg-green-500/10 dark:border-green-500/20 dark:text-green-400': stage.state === 'completed',
                     'bg-yellow-50 border-yellow-200 text-yellow-800 dark:bg-yellow-500/10 dark:border-yellow-500/20 dark:text-yellow-400': stage.state === 'partial',
                     'bg-red-50 border-red-200 text-red-700 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400': stage.state === 'missing',
                     'bg-white border-slate-200 text-slate-500 dark:bg-white/5 dark:border-white/10 dark:text-slate-400': stage.state === 'upcoming',
                 }">
                    <x-heroicon-s-check-circle class="w-4 h-4" x-show="stage.state === 'completed'"/>
                    <x-heroicon-s-minus-circle class="w-4 h-4" x-show="stage.state === 'partial'"/>
                    <x-heroicon-s-x-circle class="w-4 h-4" x-show="stage.state === 'missing'"/>
                    <div class="w-2 h-2 rounded-full border border-current opacity-50" x-show="stage.state === 'upcoming'"></div>
                    <span class="text-[10px] font-bold uppercase tracking-widest leading-none whitespace-nowrap" x-text="stage.label"></span>
                </div>

                <div x-show="!stage.isLast" class="h-1 w-4 sm:w-8 -mx-1 sm:-mx-2 z-0 transition-colors duration-150"
                     :class="{
                     'bg-green-400 dark:bg-green-500/60': stage.state === 'completed',
                     'bg-yellow-400 dark:bg-yellow-500/60': stage.state === 'partial',
                     'bg-red-400 dark:bg-red-500/60': stage.state === 'missing',
                     'bg-slate-200 dark:bg-slate-700': stage.state === 'upcoming',
                 }"></div>
            </div>
        </template>
    </div>

    <div x-show="isSearching" class="grid grid-cols-2 gap-4 w-full max-w-3xl">
        <template x-for="i in 2" :key="i">
            <div class="lp-surface p-4 h-24 animate-pulse">
                <div class="flex flex-col justify-center h-full gap-3">
                    <div class="h-4 bg-slate-200 dark:bg-white/10 rounded w-1/2"></div>
                    <div class="h-3 bg-slate-200 dark:bg-white/10 rounded w-1/3"></div>
                </div>
            </div>
        </template>
    </div>

    <div x-show="byUser !== null && selectedResult === null && results.length > 0"
         x-cloak
         class="lp-surface mb-3 w-full max-w-3xl px-4 py-2 text-sm text-primary-600 dark:text-primary-300 flex items-center gap-2">
        <x-heroicon-o-user-circle class="w-4 h-4 flex-shrink-0 text-primary-400"/>
        <span>{{ __('dashboard/strings.search_by_user') ?? 'Records by' }}&#32;<strong x-text="byUser?.name"></strong></span>
    </div>

    <div x-show="!isSearching && results.length > 0 && selectedResult === null"
         class="grid grid-cols-2 gap-4 w-full max-w-3xl">
        <template x-for="(result, idx) in results" :key="idx">
            <div class="lp-surface lp-surface-hover p-4 cursor-pointer"
                 @click="selectedResult = result">
                <div class="flex justify-between items-center h-full gap-2">
                    <div class="flex-1 min-w-0">
                        <h4 class="text-slate-800 dark:text-white font-semibold flex items-center gap-2">
                            <span class="flex-shrink-0"
                                  :class="{
                                      'text-blue-600': result.color === 'blue',
                                      'text-green-600': result.color === 'green',
                                      'text-yellow-700': result.color === 'yellow',
                                      'text-red-600': result.color === 'red',
                                      'text-slate-500': result.color === 'slate'
                                  }">
                                <template x-if="result.icon === 'shopping-cart'"><x-heroicon-o-shopping-cart class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'document-text'"><x-heroicon-o-document-text class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'document-check'"><x-heroicon-o-document-check class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'building-office'"><x-heroicon-o-building-office class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'shopping-bag'"><x-heroicon-o-shopping-bag class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'banknotes'"><x-heroicon-o-banknotes class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'truck'"><x-heroicon-o-truck class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'clipboard-document-check'"><x-heroicon-o-clipboard-document-check class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'building-office-2'"><x-heroicon-o-building-office-2 class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'building-library'"><x-heroicon-o-building-library class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'currency-dollar'"><x-heroicon-o-currency-dollar class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'cube'"><x-heroicon-o-cube class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'tag'"><x-heroicon-o-tag class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'check-badge'"><x-heroicon-o-check-badge class="w-4 h-4"/></template>
                                <template x-if="result.icon === 'user-circle'"><x-heroicon-o-user-circle class="w-4 h-4"/></template>                            </span>
                            <span x-text="result.title" class="truncate"></span>
                        </h4>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1 truncate" x-text="result.subtitle"></p>
                    </div>

                    <template x-if="result.progress > 0">
                        <div class="relative w-10 h-10 flex items-center justify-center flex-shrink-0">
                            <svg class="w-10 h-10 transform -rotate-90">
                                <circle cx="20" cy="20" r="16" stroke="currentColor" class="text-slate-200 dark:text-white/10" stroke-width="3" fill="none"/>
                                <circle cx="20" cy="20" r="16" stroke="currentColor"
                                        :class="{
                                            'text-blue-500': result.color === 'blue',
                                            'text-green-500': result.color === 'green',
                                            'text-yellow-500': result.color === 'yellow',
                                            'text-red-500': result.color === 'red',
                                            'text-slate-500': result.color === 'slate'
                                        }"
                                        stroke-width="3" fill="none"
                                        :stroke-dasharray="C"
                                        :stroke-dashoffset="getOffset(result.progress)"
                                        class="transition-all duration-500"/>
                            </svg>
                            <span class="absolute text-[10px] text-slate-800 dark:text-white font-bold tabular-nums" x-text="result.progress + '%'"></span>
                        </div>
                    </template>
                    <template x-if="result.progress === 0">
                        <div class="w-8 h-8 rounded-full bg-slate-100 dark:bg-white/5 border border-slate-200 dark:border-white/10 flex items-center justify-center flex-shrink-0">
                            <x-heroicon-o-bookmark class="w-3.5 h-3.5 text-slate-500"/>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <div x-show="selectedResult !== null" x-cloak class="w-full max-w-7xl">

        <div class="flex items-center gap-3 mb-4">
            <button @click="selectedResult = null"
                    class="lp-surface lp-surface-hover px-3 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs flex items-center gap-1.5">
                <x-heroicon-o-chevron-left class="w-3.5 h-3.5 {{ $isRtl ? 'rotate-180' : '' }}"/>
                {{ __('dashboard/strings.search_back') ?? 'Back' }}
            </button>
            <span class="text-xs text-slate-600 dark:text-slate-500 font-medium uppercase tracking-wider" x-text="selectedResult?.subtitle"></span>
        </div>

        <div class="lp-surface p-5 mb-3">
            <div class="flex items-center justify-between mb-4 gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-12 h-12 rounded-lg flex items-center justify-center flex-shrink-0"
                         :class="selectedResult?.theme">
                        <template x-if="selectedResult?.icon === 'shopping-cart'"><x-heroicon-o-shopping-cart class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'document-text'"><x-heroicon-o-document-text class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'document-check'"><x-heroicon-o-document-check class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'building-office'"><x-heroicon-o-building-office class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'shopping-bag'"><x-heroicon-o-shopping-bag class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'banknotes'"><x-heroicon-o-banknotes class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'truck'"><x-heroicon-o-truck class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'clipboard-document-check'"><x-heroicon-o-clipboard-document-check class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'building-office-2'"><x-heroicon-o-building-office-2 class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'building-library'"><x-heroicon-o-building-library class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'currency-dollar'"><x-heroicon-o-currency-dollar class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'cube'"><x-heroicon-o-cube class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'tag'"><x-heroicon-o-tag class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'check-badge'"><x-heroicon-o-check-badge class="w-6 h-6"/></template>
                        <template x-if="selectedResult?.icon === 'user-circle'"><x-heroicon-o-user-circle class="w-6 h-6"/></template>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-slate-800 dark:text-white font-bold text-lg leading-tight" x-text="selectedResult?.title"></h3>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mt-0.5" x-text="selectedResult?.subtitle"></p>
                    </div>
                </div>

                <template x-if="(selectedResult?.progress || 0) > 0">
                    <div class="relative w-14 h-14 flex items-center justify-center flex-shrink-0">
                        <svg class="w-14 h-14 transform -rotate-90">
                            <circle cx="28" cy="28" r="22" stroke="currentColor" class="text-slate-200 dark:text-white/10" stroke-width="3" fill="none"/>
                            <circle cx="28" cy="28" r="22" stroke="currentColor"
                                    :class="{
                                        'text-blue-500': selectedResult?.color === 'blue',
                                        'text-green-500': selectedResult?.color === 'green',
                                        'text-yellow-500': selectedResult?.color === 'yellow',
                                        'text-red-500': selectedResult?.color === 'red',
                                        'text-slate-500': selectedResult?.color === 'slate'
                                    }"
                                    stroke-width="3" fill="none"
                                    :stroke-dasharray="Cl"
                                    :stroke-dashoffset="getOffsetL(selectedResult?.progress || 0)"
                                    class="transition-all duration-500"/>
                        </svg>
                        <span class="absolute text-[11px] text-slate-800 dark:text-white font-bold tabular-nums" x-text="(selectedResult?.progress || 0) + '%'"></span>
                    </div>
                </template>
            </div>

            <div x-show="(selectedResult?.details?.length || 0) > 0"
                 class="grid grid-cols-2 gap-2 border-t border-slate-200 dark:border-white/5 pt-4">
                <template x-for="d in (selectedResult?.details || [])" :key="d.label">
                    <div class="bg-slate-50 dark:bg-white/[0.04] rounded-lg px-3 py-2">
                        <p class="text-[10px] text-slate-500 dark:text-slate-500 uppercase tracking-wider font-semibold mb-0.5" x-text="d.label"></p>
                        <p class="text-sm text-slate-700 dark:text-slate-200 font-medium truncate" x-text="d.value"></p>
                    </div>
                </template>
            </div>
        </div>

        <a :href="selectedResult?.url" target="_blank"
           class="lp-surface lp-surface-hover flex items-center justify-center gap-2 w-full px-4 py-3 text-primary-600 dark:text-primary-300 hover:text-primary-800 dark:hover:text-white text-sm font-semibold group">
            <x-heroicon-o-pencil-square class="w-4 h-4"/>
            {{ __('dashboard/strings.search_edit_record') ?? 'Open & Edit Record' }}
            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5 opacity-50 group-hover:opacity-100 transition-opacity {{ $isRtl ? 'rotate-90' : '' }}"/>
        </a>
    </div>

    <div x-show="!isSearching && searchQuery.length >= 2 && results.length === 0"
         class="text-slate-600 dark:text-slate-400 text-sm mt-8">
        {{ __('dashboard/strings.search_no_results') ?? 'No related resources found for' }} "<span x-text="searchQuery" class="text-slate-800 dark:text-white"></span>"
    </div>

    <div x-show="searchQuery.length < 2 && searchQuery.length > 0"
         class="text-slate-600 dark:text-slate-500 text-sm mt-8">
        {{ __('dashboard/strings.search_min_chars') ?? 'Type at least 2 characters to search…' }}
    </div>
</div>
