@php
    $logos = [
        'light' => 'dark:hidden',
        'dark'  => 'hidden dark:block',
    ];

    $tabs = [
        [
            'id' => 'customize',
            'icon' => 'heroicon-o-wrench-screwdriver',
            'label' => __('dashboard/strings.customize') ?? 'Customize',
            'click' => "activeTab = 'customize'",
        ],
        [
            'id' => 'workflow',
            'icon' => 'heroicon-o-arrow-trending-up',
            'label' => __('dashboard/strings.workflow') ?? 'Workflow',
            'click' => "activeTab = 'workflow'",
        ],
        [
            'id' => 'search',
            'icon' => 'heroicon-o-magnifying-glass',
            'label' => __('dashboard/strings.search') ?? 'Search',
            'click' => "activeTab = 'search'; \$nextTick(() => \$dispatch('tab-search-focus'))",
        ],
    ];
@endphp

<div class="mb-4 sm:mb-5">
    <div class="lp-surface overflow-hidden">
        <div class="flex items-center justify-between gap-4 px-4 sm:px-5 py-2.5 border-b lp-divider">
            <div class="min-w-0">
                <p class="text-sm font-semibold truncate text-slate-800 dark:text-slate-100">
                    {{ $welcome ?: (__('dashboard/strings.workflow') ?? 'Workflow') }}
                </p>
            </div>

            <div class="flex items-center gap-3 flex-shrink-0">
                <a href="{{ route('filament.dashboard.pages.dashboard') }}" target="_blank" rel="noopener noreferrer"
                   class="flex items-center gap-1.5 group">
                    <svg class="w-3.5 h-3.5 flex-shrink-0 text-slate-400 dark:text-slate-500 group-hover:text-primary-600 dark:group-hover:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span class="hidden sm:inline text-xs font-medium text-slate-500 dark:text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-200">
                        {{ __('dashboard/strings.return_to_main_panel') }}
                    </span>
                </a>

                <div class="w-px h-4 bg-slate-200 dark:bg-slate-700"></div>

                @foreach ($logos as $theme => $visibility)
                    <img src="{{ asset(config("app.branding.logo.{$theme}")) }}" class="!w-10 sm:w-20 h-auto opacity-60 {{ $visibility }}" alt="logo">
                @endforeach
            </div>
        </div>

        <div class="fi-tabs flex items-center gap-1 px-2 sm:px-3 overflow-x-auto custom-scrollbar">
            @foreach ($tabs as $tab)
                <button @click="{!! $tab['click'] !!}"
                        class="lp-tab flex items-center gap-1.5 px-3 py-1.5 text-sm whitespace-nowrap"
                        :class="{ 'lp-tab-active': activeTab === '{{ $tab['id'] }}' }">
                    <x-dynamic-component :component="$tab['icon']" class="w-4 h-4 flex-shrink-0"/>
                    <span>{{ $tab['label'] }}</span>
                </button>
            @endforeach
        </div>
    </div>
</div>
