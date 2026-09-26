@php
    $surface = $surface ?? 'panel';
    $palettes = config('palettes');
    $isPanel = $surface === 'panel';
@endphp

<div x-data="{ open: false, active: document.documentElement.dataset.theme || 'slate' }"
     class="relative {{ $isPanel ? 'hidden shrink-0 items-center lg:flex' : ($surface === 'login' ? 'fixed top-4 end-4 z-50 hidden sm:block' : '') }}"
     style="{{ $isPanel ? 'order: 6' : '' }}">
    @if ($isPanel)
        <x-icon-button x-on:click="open = !open" tooltip="{{ __('resources/general/strings.theme_palette.button') }}" aria-haspopup="true" x-bind:aria-expanded="open">
            <x-heroicon-o-swatch class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
        </x-icon-button>
    @else
        <button type="button" @click="open = !open" title="{{ __('resources/general/strings.theme_palette.button') }}" aria-haspopup="true" :aria-expanded="open"
                class="cursor-pointer lp-surface lp-surface-hover rounded-lg p-2.5 sm:p-3">
            <x-heroicon-o-swatch class="w-4.5 h-4.5 sm:w-5 sm:h-5 text-primary-600 dark:text-primary-400" />
        </button>
    @endif

    <div x-cloak x-show="open" @click.away="open = false" @keydown.escape.window="open = false"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="absolute top-full end-0 mt-2 z-50 flex items-center gap-2 {{ $isPanel ? 'rounded-xl border border-gray-200 dark:border-white/10 bg-white dark:bg-gray-900 shadow-lg p-2' : 'lp-surface rounded-lg p-2' }}">
        @foreach ($palettes as $key => $palette)
            <button type="button"
                    @click="setTheme('{{ $key }}'); active = '{{ $key }}'; open = false"
                    :aria-current="active === '{{ $key }}' ? 'true' : null"
                    title="{{ __("resources/general/strings.theme_palette.palettes.{$key}") }}"
                    :class="active === '{{ $key }}' ? 'ring-2 ring-primary-500 scale-110' : 'hover:scale-110'"
                    class="rounded-full p-0.5 transition-transform duration-150 {{ $isPanel ?: 'cursor-pointer' }}">
                <span class="dark:hidden block h-4 w-4 rounded-full border border-black/10" style="background: {{ $palette['dot'] }}"></span>
                <span class="hidden dark:block h-4 w-4 rounded-full" style="background: {{ $palette['dot_dark'] }}"></span>
            </button>
        @endforeach
    </div>
</div>