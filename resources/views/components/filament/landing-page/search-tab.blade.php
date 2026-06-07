<div x-show="activeTab === 'search'"
     x-transition:enter="transition-opacity duration-150 ease-in"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity duration-150 ease-out"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="flex flex-col items-center justify-center h-full w-full"
     x-data="{
         searchQuery: '',
         isSearching: false,
         results: [],
         breadcrumb: { proforma: 'upcoming', order: 'upcoming', logistics: 'upcoming' },

         async performSearch() {
             if (this.searchQuery.length < 2) {
                 this.results = [];
                 return;
             }
             this.isSearching = true;

             try {
                 // Using axios (from your package.json)
                 const response = await axios.get('/api/search/spotlight?q=' + encodeURIComponent(this.searchQuery));
                 this.results = response.data.results || [];
                 if(response.data.breadcrumb) {
                     this.breadcrumb = response.data.breadcrumb;
                 }
             } catch (error) {
                 console.error('Search error:', error);
                 this.results = [];
             } finally {
                 this.isSearching = false;
             }
         },

         getCircumference() {
             return 2 * Math.PI * 16;
         },

         getOffset(percent) {
             const c = this.getCircumference();
             return c - (percent / 100) * c;
         }
     }"
     @keydown.window="if ($event.metaKey && $event.key === 'k') { $event.preventDefault(); activeTab = 'search'; $nextTick(() => $refs.searchInput.focus()); }">

    <div class="w-full max-w-2xl relative mb-6">
        <input type="text"
               x-ref="searchInput"
               x-model.debounce.500ms="searchQuery"
               @input="performSearch"
               class="w-full bg-slate-900/70 border border-white/10 rounded-full px-6 py-4 text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 backdrop-blur-md shadow-2xl"
               placeholder="{{ __('dashboard/strings.search_spotlight') ?? 'Search records (Cmd/Ctrl+K)...' }}">
        <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
            <kbd class="hidden sm:inline-block border border-slate-600 bg-slate-800 rounded px-2 py-1 text-xs text-slate-400 font-mono">Cmd K</kbd>
        </div>
    </div>

    <!-- Dynamic Breadcrumbs -->
    <div class="flex items-center gap-2 mb-4 text-sm bg-slate-900/50 backdrop-blur-md px-4 py-1.5 rounded-full border border-white/5" x-show="searchQuery.length >= 2">
        <div class="flex items-center gap-1" :class="breadcrumb.proforma === 'completed' ? 'text-emerald-400' : (breadcrumb.proforma === 'active' ? 'text-amber-500 animate-pulse' : 'text-slate-500')">
            <x-heroicon-o-check-circle class="w-4 h-4" x-show="breadcrumb.proforma === 'completed'"/>
            <div class="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.8)]" x-show="breadcrumb.proforma === 'active'"></div>
            <div class="w-3.5 h-3.5 rounded-full border border-slate-500" x-show="breadcrumb.proforma === 'upcoming'"></div>
            <span class="font-medium">Proforma</span>
        </div>
        <div class="w-6 h-[1px] bg-slate-600"></div>
        <div class="flex items-center gap-1" :class="breadcrumb.order === 'completed' ? 'text-emerald-400' : (breadcrumb.order === 'active' ? 'text-amber-500 animate-pulse' : 'text-slate-500')">
            <x-heroicon-o-check-circle class="w-4 h-4" x-show="breadcrumb.order === 'completed'"/>
            <div class="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.8)]" x-show="breadcrumb.order === 'active'"></div>
            <div class="w-3.5 h-3.5 rounded-full border border-slate-500" x-show="breadcrumb.order === 'upcoming'"></div>
            <span class="font-medium">Order</span>
        </div>
        <div class="w-6 h-[1px] bg-slate-600"></div>
        <div class="flex items-center gap-1" :class="breadcrumb.logistics === 'completed' ? 'text-emerald-400' : (breadcrumb.logistics === 'active' ? 'text-amber-500 animate-pulse' : 'text-slate-500')">
            <x-heroicon-o-check-circle class="w-4 h-4" x-show="breadcrumb.logistics === 'completed'"/>
            <div class="w-2 h-2 rounded-full bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.8)]" x-show="breadcrumb.logistics === 'active'"></div>
            <div class="w-3.5 h-3.5 rounded-full border border-slate-500" x-show="breadcrumb.logistics === 'upcoming'"></div>
            <span>Logistics</span>
        </div>
    </div>

    <!-- Loading State -->
    <div x-show="isSearching" class="grid grid-cols-2 gap-4 w-full max-w-3xl">
        <template x-for="i in 2" :key="i">
            <div class="glass border border-white/10 rounded-2xl p-4 bg-slate-900/70 backdrop-blur-md relative overflow-hidden h-24">
                <div class="absolute inset-0 -translate-x-full animate-shimmer bg-gradient-to-r from-transparent via-white/5 to-transparent"></div>
                <div class="flex flex-col justify-center h-full gap-3">
                    <div class="h-4 bg-slate-700/50 rounded w-1/2"></div>
                    <div class="h-3 bg-slate-700/50 rounded w-1/3"></div>
                </div>
            </div>
        </template>
    </div>

    <!-- Results Grid -->
    <div x-show="!isSearching && results.length > 0" class="grid grid-cols-2 gap-4 w-full max-w-3xl">
        <template x-for="result in results" :key="result.title">
            <div class="glass border rounded-2xl p-4 bg-slate-900/70 backdrop-blur-md transition-transform hover:scale-[1.02] cursor-pointer"
                 :class="{
                     'border-indigo-500/50 shadow-[0_0_20px_rgba(79,70,229,0.3)]': result.color === 'indigo',
                     'border-amber-500/50 shadow-[0_0_15px_rgba(245,158,11,0.15)] animate-pulse': result.color === 'amber',
                     'border-white/10 hover:bg-slate-800/80': result.color === 'slate'
                 }">
                <div class="flex justify-between items-center h-full">
                    <div>
                        <h4 class="text-white font-semibold flex items-center gap-2">
                            <!-- Dynamic Icon based on type -->
                            <template x-if="result.icon === 'document-text'"><x-heroicon-o-document-text class="w-4 h-4 text-indigo-400"/></template>
                            <template x-if="result.icon === 'shopping-bag'"><x-heroicon-o-shopping-bag class="w-4 h-4 text-amber-500"/></template>
                            <template x-if="result.icon === 'paper-clip'"><x-heroicon-o-paper-clip class="w-4 h-4 text-slate-400"/></template>
                            <span x-text="result.title"></span>
                        </h4>
                        <p class="text-slate-400 text-xs mt-1" x-text="result.subtitle"></p>
                    </div>

                    <!-- Progress Donut (if measurable) -->
                    <template x-if="!result.is_binary">
                        <div class="relative w-10 h-10 flex items-center justify-center">
                            <svg class="w-10 h-10 transform -rotate-90">
                                <circle cx="20" cy="20" r="16" stroke="rgba(255,255,255,0.1)" stroke-width="3" fill="none" />
                                <circle cx="20" cy="20" r="16" stroke="currentColor" :class="result.color === 'indigo' ? 'text-indigo-500' : 'text-amber-500'" stroke-width="3" fill="none" :stroke-dasharray="getCircumference()" :stroke-dashoffset="getOffset(result.progress)" class="transition-all duration-1000" />
                            </svg>
                            <span class="absolute text-[10px] text-white font-bold" x-text="result.progress + '%'"></span>
                        </div>
                    </template>

                    <!-- Checkmark (if binary) -->
                    <template x-if="result.is_binary">
                        <div class="w-8 h-8 rounded-full bg-emerald-500/20 text-emerald-500 flex items-center justify-center">
                            <x-heroicon-o-check class="w-5 h-5"/>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    <!-- Empty State -->
    <div x-show="!isSearching && searchQuery.length >= 2 && results.length === 0" class="text-slate-400 text-sm mt-8">
        No related resources found for "<span x-text="searchQuery" class="text-white"></span>"
    </div>

    <div x-show="searchQuery.length < 2 && searchQuery.length > 0" class="text-slate-500 text-sm mt-8">
        Type at least 2 characters to search...
    </div>
</div>
