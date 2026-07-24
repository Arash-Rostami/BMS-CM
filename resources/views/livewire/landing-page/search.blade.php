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
            <x-kbd><span class="text-[10px]">⌘</span>K</x-kbd>
        </div>
    </div>

    <div x-show="selectedResult !== null && !chainLoading && chain.length > 0"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="lp-surface flex flex-wrap items-center justify-center gap-y-4 mb-5 w-full max-w-7xl py-4 px-6">

        <template x-for="stage in breadcrumbStages()" :key="stage.key">
            <div class="flex items-center">
                <div class="relative z-10 flex items-center gap-2 px-3 py-1.5 rounded-full border shadow-sm"
                     :class="@js($stageStateClasses)[stage.state]">
                    <x-heroicon-s-check-circle class="w-4 h-4" x-show="stage.state === 'completed'"/>
                    <x-heroicon-s-minus-circle class="w-4 h-4" x-show="stage.state === 'partial'"/>
                    <x-heroicon-s-x-circle class="w-4 h-4" x-show="stage.state === 'missing'"/>
                    <div class="w-2 h-2 rounded-full border border-current opacity-50" x-show="stage.state === 'upcoming'"></div>
                    <span class="text-[10px] font-bold uppercase tracking-widest leading-none whitespace-nowrap" x-text="stage.label"></span>
                </div>

                <div x-show="!stage.isLast" class="h-1 w-4 sm:w-8 -mx-1 sm:-mx-2 z-0 transition-colors duration-150"
                     :class="@js($stageLineClasses)[stage.state]"></div>
            </div>
        </template>
    </div>

    <div x-show="isSearching" class="grid grid-cols-2 gap-4 w-full max-w-3xl">
        <template x-for="i in 2" :key="i">
            <div class="lp-surface p-4 h-24 animate-pulse">
                <div class="flex flex-col justify-center h-full gap-3">
                    <x-loading-skeleton class="h-4 rounded w-1/2" />
                    <x-loading-skeleton class="h-3 rounded w-1/3" />
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
                 @click="selectResult(result)">
                <div class="flex justify-between items-center h-full gap-2">
                    <div class="flex-1 min-w-0">
                        <h4 class="text-slate-800 dark:text-white font-semibold flex items-center gap-2">
                            <span class="flex-shrink-0" :class="@js($textColors)[result.color]">
                                @foreach ($icons as $icon)
                                    <template x-if="result.icon === '{{ $icon }}'">
                                        <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4 h-4"/>
                                    </template>
                                @endforeach
                            </span>
                            <span x-text="result.title" class="truncate"></span>
                        </h4>
                        <p class="text-slate-500 dark:text-slate-400 text-xs mt-1 truncate" x-text="result.subtitle"></p>
                    </div>

                    <template x-if="result.progress > 0">
                        <div class="relative w-10 h-10 flex items-center justify-center flex-shrink-0">
                            <svg class="w-10 h-10 transform -rotate-90">
                                <circle cx="20" cy="20" r="16" stroke="currentColor" class="text-slate-200 dark:text-white/10" stroke-width="3" fill="none"/>
                                <circle cx="20" cy="20" r="16" stroke="currentColor"
                                        :class="@js($progressColors)[result.color]"
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
            <button @click="clearSelected()"
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
                        @foreach ($icons as $icon)
                            <template x-if="selectedResult?.icon === '{{ $icon }}'">
                                <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-6 h-6"/>
                            </template>
                        @endforeach
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
                                    :class="@js($progressColors)[selectedResult?.color]"
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

        <a :href="selectedResult?.url" target="_blank" rel="noopener noreferrer"
           class="lp-surface lp-surface-hover flex items-center justify-center gap-2 w-full px-4 py-3 mb-3 text-primary-600 dark:text-primary-300 hover:text-primary-800 dark:hover:text-white text-sm font-semibold group">
            <x-heroicon-o-pencil-square class="w-4 h-4"/>
            {{ __('dashboard/strings.search_edit_record') ?? 'Open & Edit Record' }}
            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5 opacity-50 group-hover:opacity-100 transition-opacity {{ $isRtl ? 'rotate-90' : '' }}"/>
        </a>

        <div class="mt-3" x-cloak>
            <div class="flex items-center gap-2 mb-2 px-1">
                <h4 class="text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">
                    {{ __('dashboard/strings.search_chain_title') }}
                </h4>
                <span class="text-[10px] text-slate-400 tabular-nums"
                      x-show="!chainLoading && !chainError && chain.length"
                      x-text="(chain.filter(e => e.attached).length) + '/' + chain.length"></span>
            </div>

            <div x-show="chainLoading" class="lp-surface p-4 animate-pulse space-y-3">
                <x-loading-skeleton class="h-3 rounded w-1/4" />
                <x-loading-skeleton class="h-8 rounded w-2/3" />
                <x-loading-skeleton class="h-8 rounded w-1/2" />
            </div>

            <div x-show="chainError" class="lp-surface p-3 flex items-center gap-2 text-sm text-red-600 dark:text-red-400">
                <x-heroicon-o-exclamation-triangle class="w-4 h-4 flex-shrink-0"/>
                <span>{{ __('dashboard/strings.search_chain_error') }}</span>
                <button type="button" @click="selectResult(selectedResult)"
                        class="ms-auto text-xs underline text-red-600 dark:text-red-400">
                    {{ __('dashboard/strings.search_chain_retry') }}
                </button>
            </div>

            <template x-if="!chainLoading && !chainError && chain.length">
                <div class="space-y-1.5">
                    <template x-for="entry in chain" :key="entry.key">
                        <div class="lp-surface px-3 py-2">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                      :class="entry.attached ? 'bg-green-500' : 'bg-red-400'"
                                      :title="entry.attached ? @js(__('dashboard/strings.search_chain_attached')) : @js(__('dashboard/strings.search_chain_none'))"></span>
                                <span class="flex-shrink-0" :class="@js($entryColors)[entry.color]">
                                    @foreach ($icons as $icon)
                                        <template x-if="entry.icon === '{{ $icon }}'">
                                            <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-4 h-4"/>
                                        </template>
                                    @endforeach
                                </span>
                                <span class="text-xs font-semibold text-slate-800 dark:text-white truncate" x-text="entry.label"></span>
                                <span class="ms-auto text-[10px] text-slate-400 tabular-nums"
                                      x-show="entry.attached"
                                      x-text="entry.records.length + ' ' + @js(__('dashboard/strings.search_chain_records'))"></span>
                                <span class="ms-auto text-[10px] text-slate-400"
                                      x-show="!entry.attached">{{ __('dashboard/strings.search_chain_none') }}</span>
                            </div>

                            <div class="flex flex-wrap gap-1.5 mt-2" x-show="entry.attached">
                                <template x-for="rec in entry.records" :key="rec.id">
                                    <a :href="rec.url" target="_blank" rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1.5 rounded-md border lp-divider lp-surface-hover px-2 py-1 text-xs">
                                        <span class="font-semibold text-slate-700 dark:text-slate-200 truncate max-w-[12rem]" x-text="rec.identifier"></span>
                                        <span class="tabular-nums text-[10px] font-bold px-1 py-0.5 rounded"
                                              :title="@js(__('dashboard/strings.search_chain_completion'))"
                                              :class="@js($badgeColors)[entry.color]"
                                              x-text="rec.progress + '%'"></span>
                                        <template x-for="ex in rec.extras" :key="ex.label">
                                            <span x-show="ex.value"
                                                  class="inline-flex items-center gap-1 text-[10px] rounded px-1.5 py-0.5 bg-slate-100 dark:bg-white/5">
                                                <span class="text-slate-400" x-text="ex.label + ':'"></span>
                                                <span class="font-semibold text-slate-700 dark:text-slate-200" x-text="ex.value"></span>
                                            </span>
                                        </template>
                                        <template x-for="s in rec.statuses" :key="s.label">
                                            <span class="inline-flex items-center gap-1 text-[10px] rounded px-1.5 py-0.5 border lp-divider">
                                                <span class="text-slate-400" x-text="s.label + ':'"></span>
                                                <span class="font-medium text-slate-700 dark:text-slate-200" x-text="s.value || '—'"></span>
                                            </span>
                                        </template>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
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
