@props([
    'open',
    'icon',
    'title',
    'count' => null,
    'countLabel' => null,
])

<div class="lp-surface rounded-lg overflow-hidden">
    <button type="button" @click="{{ $open }} = !{{ $open }}" class="w-full px-4 sm:px-5 py-3 flex items-center justify-between group !cursor-pointer">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-md border lp-divider flex items-center justify-center flex-shrink-0 text-primary-600 dark:text-primary-400">
                <x-dynamic-component :component="$icon" class="w-4 h-4"/>
            </div>
            <div class="text-left">
                <h2 class="text-sm font-semibold" :class="darkMode ? 'text-white' : 'text-slate-900'">
                    {{ $title }}
                </h2>
                @if ($count)
                    <p class="text-xs" :class="darkMode ? 'text-slate-400' : 'text-slate-500'">
                        <span x-show="{{ $count }}" x-cloak>
                            <span x-text="{{ $count }}"></span> {{ $countLabel }}
                        </span>
                    </p>
                @endif
            </div>
        </div>
        <div class="w-6 h-6 rounded-md border lp-divider flex items-center justify-center flex-shrink-0 text-slate-500 dark:text-slate-400">
            <svg class="w-3.5 h-3.5 transition-transform duration-150" :class="{{ $open }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </div>
    </button>

    <div x-show="{{ $open }}" x-collapse x-cloak>
        <div class="px-4 sm:px-5 pb-4 pt-1 border-t lp-divider">
            <div class="lp-panel rounded-md border lp-divider overflow-hidden">
                <div class="p-3 sm:p-4">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</div>
