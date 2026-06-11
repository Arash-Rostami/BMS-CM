<div class="flex flex-col items-center justify-center h-full w-full pt-6"
     x-data="{
         searchQuery: '',
         isSearching: false,
         results: [],
         selectedResult: null,
         byUser: null,
         breadcrumb: { proforma: 'upcoming', order: 'upcoming', logistics: 'upcoming' },
         C:  2 * Math.PI * 16,
         Cl: 2 * Math.PI * 22,

         async performSearch() {
             if (this.searchQuery.length < 2) {
                 this.results = [];
                 this.selectedResult = null;
                 this.byUser = null;
                 return;
             }
             this.isSearching = true;
             this.selectedResult = null;
             try {
                 const r = await axios.get('/api/search/spotlight?q=' + encodeURIComponent(this.searchQuery));
                 this.results    = r.data.results   || [];
                 this.byUser     = r.data.by_user   || null;
                 if (r.data.breadcrumb) this.breadcrumb = r.data.breadcrumb;
             } catch {
                 this.results = [];
             } finally {
                 this.isSearching = false;
             }
         },

         getOffset(p)  { return this.C  - (p / 100) * this.C;  },
         getOffsetL(p) { return this.Cl - (p / 100) * this.Cl; }
     }"
     @tab-search-focus.window="$nextTick(() => $refs.searchInput?.focus())">

    {{-- Search input --}}
    <div class="w-full max-w-2xl relative mb-6">
        <div class="absolute inset-y-0 {{ $isRtl ? 'right-5' : 'left-5' }} flex items-center pointer-events-none">
            <x-heroicon-o-magnifying-glass class="w-5 h-5 text-slate-500"/>
        </div>
        <input type="text"
               x-ref="searchInput"
               x-model="searchQuery"
               @input.debounce.500ms="performSearch"
               class="w-full glass border border-slate-200 dark:border-white/10 rounded-2xl {{ $isRtl ? 'pr-14 pl-36' : 'pl-14 pr-36' }} py-4 text-slate-800 dark:text-white placeholder-slate-500 dark:placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/40 shadow-2xl text-sm transition-all duration-200"
               placeholder="{{ __('dashboard/strings.search_spotlight') ?? 'Search records (Cmd/Ctrl+K)…' }}">
        <div class="absolute inset-y-0 {{ $isRtl ? 'left-4' : 'right-4' }} flex items-center pointer-events-none gap-1.5">
            <kbd class="hidden sm:inline-flex items-center gap-1 border border-slate-200 dark:border-white/10 bg-slate-100 dark:bg-white/5 rounded-lg px-2 py-1 text-[11px] text-slate-500 font-mono">
                <span class="text-[10px]">⌘</span>K
            </kbd>
        </div>
    </div>

    {{-- Pipeline breadcrumb --}}
    <div class="flex items-center gap-2 mb-4 text-sm bg-white/80 dark:bg-slate-900/50 backdrop-blur-md px-4 py-1.5 rounded-full border border-slate-200 dark:border-white/5"
         x-show="searchQuery.length >= 2">
        <div class="flex items-center gap-1"
             :class="breadcrumb.proforma === 'completed' ? 'text-emerald-400' : (breadcrumb.proforma === 'active' ? 'text-amber-500 animate-pulse' : 'text-slate-500')">
            <x-heroicon-o-check-circle class="w-4 h-4" x-show="breadcrumb.proforma === 'completed'"/>
            <div class="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.8)]" x-show="breadcrumb.proforma === 'active'"></div>
            <div class="w-3.5 h-3.5 rounded-full border border-slate-500" x-show="breadcrumb.proforma === 'upcoming'"></div>
            <span class="font-medium">{{ __('dashboard/strings.search_proforma') ?? 'Proforma' }}</span>
        </div>
        <div class="w-6 h-[1px] bg-slate-600"></div>
        <div class="flex items-center gap-1"
             :class="breadcrumb.order === 'completed' ? 'text-emerald-400' : (breadcrumb.order === 'active' ? 'text-amber-500 animate-pulse' : 'text-slate-500')">
            <x-heroicon-o-check-circle class="w-4 h-4" x-show="breadcrumb.order === 'completed'"/>
            <div class="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.8)]" x-show="breadcrumb.order === 'active'"></div>
            <div class="w-3.5 h-3.5 rounded-full border border-slate-500" x-show="breadcrumb.order === 'upcoming'"></div>
            <span class="font-medium">{{ __('dashboard/strings.search_order') ?? 'Order' }}</span>
        </div>
        <div class="w-6 h-[1px] bg-slate-600"></div>
        <div class="flex items-center gap-1"
             :class="breadcrumb.logistics === 'completed' ? 'text-emerald-400' : (breadcrumb.logistics === 'active' ? 'text-amber-500 animate-pulse' : 'text-slate-500')">
            <x-heroicon-o-check-circle class="w-4 h-4" x-show="breadcrumb.logistics === 'completed'"/>
            <div class="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.8)]" x-show="breadcrumb.logistics === 'active'"></div>
            <div class="w-3.5 h-3.5 rounded-full border border-slate-500" x-show="breadcrumb.logistics === 'upcoming'"></div>
            <span>{{ __('dashboard/strings.search_logistics') ?? 'Logistics' }}</span>
        </div>
    </div>

    {{-- Skeleton loading --}}
    <div x-show="isSearching" class="grid grid-cols-2 gap-4 w-full max-w-3xl">
        <template x-for="i in 2" :key="i">
            <div class="glass border border-slate-200 dark:border-white/10 rounded-2xl p-4 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md relative overflow-hidden h-24">
                <div class="absolute inset-0 -translate-x-full animate-shimmer bg-gradient-to-r from-transparent via-slate-200/50 dark:via-white/5 to-transparent"></div>
                <div class="flex flex-col justify-center h-full gap-3">
                    <div class="h-4 bg-slate-200 dark:bg-slate-700/50 rounded w-1/2"></div>
                    <div class="h-3 bg-slate-200 dark:bg-slate-700/50 rounded w-1/3"></div>
                </div>
            </div>
        </template>
    </div>

    {{-- By-user banner --}}
    <div x-show="byUser !== null && selectedResult === null && results.length > 0"
         x-cloak
         class="mb-3 w-full max-w-3xl glass border border-indigo-500/30 dark:border-indigo-500/30 rounded-xl px-4 py-2 text-sm text-indigo-600 dark:text-indigo-300 flex items-center gap-2">
        <x-heroicon-o-user-circle class="w-4 h-4 flex-shrink-0 text-indigo-400"/>
        <span>{{ __('dashboard/strings.search_by_user') ?? 'Records by' }}&#32;<strong x-text="byUser?.name"></strong></span>
    </div>

    {{-- Results grid --}}
    <div x-show="!isSearching && results.length > 0 && selectedResult === null"
         class="grid grid-cols-2 gap-4 w-full max-w-3xl">
        <template x-for="(result, idx) in results" :key="idx">
            <div class="glass border rounded-2xl p-4 bg-slate-900/70 backdrop-blur-md transition-all hover:scale-[1.02] hover:brightness-110 cursor-pointer"
                 :class="{
                     'border-blue-500/50 shadow-[0_0_20px_rgba(59,130,246,0.25)]':    result.color === 'blue',
                     'border-indigo-500/50 shadow-[0_0_20px_rgba(79,70,229,0.3)]':   result.color === 'indigo',
                     'border-green-500/50 shadow-[0_0_15px_rgba(34,197,94,0.2)]':    result.color === 'green',
                     'border-emerald-500/50 shadow-[0_0_15px_rgba(16,185,129,0.2)]': result.color === 'emerald',
                     'border-amber-500/50 shadow-[0_0_15px_rgba(245,158,11,0.2)]':   result.color === 'amber',
                     'border-orange-500/50 shadow-[0_0_15px_rgba(249,115,22,0.2)]':  result.color === 'orange',
                     'border-purple-500/50 shadow-[0_0_15px_rgba(168,85,247,0.2)]':  result.color === 'purple',
                     'border-violet-500/50 shadow-[0_0_15px_rgba(139,92,246,0.2)]':  result.color === 'violet',
                     'border-sky-500/50 shadow-[0_0_15px_rgba(14,165,233,0.2)]':     result.color === 'sky',
                     'border-teal-500/50 shadow-[0_0_15px_rgba(20,184,166,0.2)]':    result.color === 'teal',
                     'border-lime-500/50 shadow-[0_0_15px_rgba(132,204,22,0.2)]':    result.color === 'lime',
                     'border-rose-500/50 shadow-[0_0_15px_rgba(244,63,94,0.2)]':     result.color === 'rose',
                     'border-fuchsia-500/50 shadow-[0_0_15px_rgba(217,70,239,0.2)]': result.color === 'fuchsia',
                     'border-cyan-500/50 shadow-[0_0_15px_rgba(6,182,212,0.2)]':     result.color === 'cyan',
                     'border-zinc-500/50 shadow-[0_0_15px_rgba(113,113,122,0.15)]':  result.color === 'zinc'
                 }"
                 @click="selectedResult = result">
                <div class="flex justify-between items-center h-full gap-2">
                    <div class="flex-1 min-w-0">
                        <h4 class="text-slate-800 dark:text-white font-semibold flex items-center gap-2">
                            <span class="flex-shrink-0"
                                  :class="{
                                      'text-blue-400':    result.color === 'blue',
                                      'text-indigo-400':  result.color === 'indigo',
                                      'text-green-400':   result.color === 'green',
                                      'text-emerald-400': result.color === 'emerald',
                                      'text-amber-500':   result.color === 'amber',
                                      'text-orange-400':  result.color === 'orange',
                                      'text-purple-400':  result.color === 'purple',
                                      'text-violet-400':  result.color === 'violet',
                                      'text-sky-400':     result.color === 'sky',
                                      'text-teal-400':    result.color === 'teal',
                                      'text-lime-400':    result.color === 'lime',
                                      'text-rose-400':    result.color === 'rose',
                                      'text-fuchsia-400': result.color === 'fuchsia',
                                      'text-cyan-400':    result.color === 'cyan',
                                      'text-zinc-400':    result.color === 'zinc'
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
                                <template x-if="result.icon === 'user-circle'"><x-heroicon-o-user-circle class="w-4 h-4"/></template>
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
                                        :class="{
                                            'text-blue-500':    result.color === 'blue',
                                            'text-indigo-500':  result.color === 'indigo',
                                            'text-green-500':   result.color === 'green',
                                            'text-emerald-500': result.color === 'emerald',
                                            'text-amber-500':   result.color === 'amber',
                                            'text-orange-500':  result.color === 'orange',
                                            'text-purple-500':  result.color === 'purple',
                                            'text-violet-500':  result.color === 'violet'
                                        }"
                                        stroke-width="3" fill="none"
                                        :stroke-dasharray="C"
                                        :stroke-dashoffset="getOffset(result.progress)"
                                        class="transition-all duration-1000"/>
                            </svg>
                            <span class="absolute text-[10px] text-slate-800 dark:text-white font-bold" x-text="result.progress + '%'"></span>
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

    {{-- Detail panel --}}
    <div x-show="selectedResult !== null" x-cloak class="w-full max-w-3xl">

        {{-- Back + module chip --}}
        <div class="flex items-center gap-3 mb-4">
            <button @click="selectedResult = null"
                    class="glass border border-slate-200 dark:border-white/10 rounded-xl px-3 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white text-xs flex items-center gap-1.5 transition-all hover:border-slate-300 dark:hover:border-white/20">
                <x-heroicon-o-chevron-left class="w-3.5 h-3.5 {{ $isRtl ? 'rotate-180' : '' }}"/>
                {{ __('dashboard/strings.search_back') ?? 'Back' }}
            </button>
            <span class="text-xs text-slate-600 dark:text-slate-500 font-medium uppercase tracking-wider" x-text="selectedResult?.subtitle"></span>
        </div>

        {{-- Record header card --}}
        <div class="glass border border-slate-200 dark:border-white/10 rounded-2xl p-5 mb-3 bg-white/60 dark:bg-transparent">
            <div class="flex items-center justify-between mb-4 gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-br flex items-center justify-center text-white shadow-lg flex-shrink-0"
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
                                        'text-blue-500':    selectedResult?.color === 'blue',
                                        'text-indigo-500':  selectedResult?.color === 'indigo',
                                        'text-green-500':   selectedResult?.color === 'green',
                                        'text-emerald-500': selectedResult?.color === 'emerald',
                                        'text-amber-500':   selectedResult?.color === 'amber',
                                        'text-orange-500':  selectedResult?.color === 'orange',
                                        'text-purple-500':  selectedResult?.color === 'purple',
                                        'text-violet-500':  selectedResult?.color === 'violet',
                                        'text-sky-500':     selectedResult?.color === 'sky',
                                        'text-teal-500':    selectedResult?.color === 'teal',
                                        'text-lime-500':    selectedResult?.color === 'lime',
                                        'text-rose-500':    selectedResult?.color === 'rose',
                                        'text-fuchsia-500': selectedResult?.color === 'fuchsia',
                                        'text-cyan-500':    selectedResult?.color === 'cyan',
                                        'text-zinc-500':    selectedResult?.color === 'zinc'
                                    }"
                                    stroke-width="3" fill="none"
                                    :stroke-dasharray="Cl"
                                    :stroke-dashoffset="getOffsetL(selectedResult?.progress || 0)"
                                    class="transition-all duration-1000"/>
                        </svg>
                        <span class="absolute text-[11px] text-slate-800 dark:text-white font-bold" x-text="(selectedResult?.progress || 0) + '%'"></span>
                    </div>
                </template>
            </div>

            {{-- Details grid --}}
            <div x-show="(selectedResult?.details?.length || 0) > 0"
                 class="grid grid-cols-2 gap-2 border-t border-slate-200 dark:border-white/5 pt-4">
                <template x-for="d in (selectedResult?.details || [])" :key="d.label">
                    <div class="bg-slate-100 dark:bg-white/[0.04] hover:bg-slate-200 dark:hover:bg-white/[0.07] rounded-xl px-3 py-2 transition-colors">
                        <p class="text-[10px] text-slate-500 dark:text-slate-500 uppercase tracking-wider font-semibold mb-0.5" x-text="d.label"></p>
                        <p class="text-sm text-slate-700 dark:text-slate-200 font-medium truncate" x-text="d.value"></p>
                    </div>
                </template>
            </div>
        </div>

        {{-- Edit action --}}
        <a :href="selectedResult?.url"
           class="flex items-center justify-center gap-2 w-full glass border border-indigo-500/30 dark:border-indigo-500/30 rounded-xl px-4 py-3 text-indigo-600 dark:text-indigo-300 hover:text-indigo-800 dark:hover:text-white hover:border-indigo-400 dark:hover:border-indigo-400 transition-all text-sm font-semibold group">
            <x-heroicon-o-pencil-square class="w-4 h-4 group-hover:scale-110 transition-transform"/>
            {{ __('dashboard/strings.search_edit_record') ?? 'Open &amp; Edit Record' }}
            <x-heroicon-o-arrow-top-right-on-square class="w-3.5 h-3.5 opacity-50 group-hover:opacity-100 transition-opacity {{ $isRtl ? 'rotate-90' : '' }}"/>
        </a>
    </div>

    {{-- Empty state --}}
    <div x-show="!isSearching && searchQuery.length >= 2 && results.length === 0"
         class="text-slate-600 dark:text-slate-400 text-sm mt-8">
        {{ __('dashboard/strings.search_no_results') ?? 'No related resources found for' }} "<span x-text="searchQuery" class="text-slate-800 dark:text-white"></span>"
    </div>

    <div x-show="searchQuery.length < 2 && searchQuery.length > 0"
         class="text-slate-600 dark:text-slate-500 text-sm mt-8">
        {{ __('dashboard/strings.search_min_chars') ?? 'Type at least 2 characters to search…' }}
    </div>
</div>
