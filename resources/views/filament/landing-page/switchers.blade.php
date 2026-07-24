@php
    $side = $isRtl ? 'left' : 'right';
    $btnClass = 'cursor-pointer lp-surface lp-surface-hover rounded-lg p-2.5 sm:p-3';
    $iconClass = 'w-4.5 h-4.5 sm:w-5 sm:h-5';
@endphp

<div class="fixed top-4 sm:top-6 {{ $side }}-4 sm:{{ $side }}-6 z-50 flex flex-col gap-1.5">
    <div x-data="{ open: false }" class="relative">
        <button @click="open = !open" class="{{ $btnClass }}">
            <svg class="{{ $iconClass }}" :class="darkMode ? 'text-primary-400' : 'text-primary-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
            </svg>
        </button>
        <div x-show="open" @click.away="open = false"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute {{ $side }}-0 mt-2 lp-surface rounded-lg overflow-hidden min-w-[64px] z-50">
            @foreach($locales as $l)
                <a href="?locale={{ $l['code'] }}"
                   class="lp-surface-hover cursor-pointer flex items-center justify-center p-3 transition-colors duration-150 {{ $locale === $l['code'] ? 'bg-primary-50 dark:bg-primary-400/10' : '' }}"
                   title="{{ $l['alt'] }}">
                    <img src="{{ asset('img/flags/' . $l['flag']) }}" alt="{{ $l['alt'] }}" class="w-6 h-6 rounded object-cover">
                </a>
            @endforeach
        </div>
    </div>

    <button @click="darkMode = !darkMode" class="{{ $btnClass }}">
        <svg x-show="darkMode" class="{{ $iconClass }} text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/>
        </svg>
        <svg x-show="!darkMode" class="{{ $iconClass }} text-primary-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
        </svg>
    </button>

    <form method="POST" action="{{ filament()->getLogoutUrl() }}">
        @csrf
        <button type="submit" class="{{ $btnClass }} w-full">
            <svg class="{{ $iconClass }} text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
        </button>
    </form>

    <button @click="widgetOpen = !widgetOpen" class="{{ $btnClass }}">
        <svg class="{{ $iconClass }}" :class="darkMode ? 'text-primary-400' : 'text-primary-600'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </button>
</div>
