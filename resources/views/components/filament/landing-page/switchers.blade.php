<div
    class="fixed top-4 sm:top-6 {{ $isRtl ? 'left-4 sm:left-6' : 'right-4 sm:right-6' }} z-50 flex flex-col gap-2 sm:gap-3">
    <div x-data="{ open: false }" class="relative">
        {{-- Lingo Changer--}}
        <button @click="open = !open"
                class="cursor-pointer backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border rounded-xl sm:rounded-2xl p-3 sm:p-4 hover:scale-110 transition-all duration-500 ease-out shadow-2xl group"
                :class="darkMode ? 'border-white/10' : 'border-slate-300'">
            <svg class="w-5 h-5 sm:w-6 sm:h-6" :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
            </svg>
        </button>
        <div x-show="open" @click.away="open = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95"
             class="absolute {{ $isRtl ? 'left-0' : 'right-0' }} mt-3 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border border-white/10 rounded shadow-2xl overflow-hidden min-w-[72px] z-50">
            @foreach($locales as $l)
                <a href="?locale={{ $l['code'] }}"
                   class="cursor-pointer flex items-center justify-center p-3.5 hover:bg-white/10 transition-all duration-300 {{ $locale === $l['code'] ? 'bg-white/20 ring-1 ring-white/30' : '' }}"
                   title="{{ $l['alt'] }}">
                    <img src="{{ asset('img/flags/' . $l['flag']) }}" alt="{{ $l['alt'] }}"
                         class="w-7 h-7 mt-2 ml-2 rounded object-cover shadow-lg border-amber-500/50">
                </a>
            @endforeach
        </div>
    </div>
    {{-- Light/Dark Mode --}}
    <button @click="darkMode = !darkMode"
            class="backdrop-blur-[16px] cursor-pointer backdrop-saturate-[180%] bg-white/5 border rounded-xl sm:rounded-2xl p-3 sm:p-4 hover:scale-110 transition-all duration-500 ease-out shadow-2xl group"
            :class="darkMode ? 'border-white/10' : 'border-slate-300'">
        <svg x-show="darkMode" class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-400" fill="currentColor"
             viewBox="0 0 20 20">
            <path
                d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z"/>
        </svg>
        <svg x-show="!darkMode" class="w-5 h-5 sm:w-6 sm:h-6 text-indigo-600" fill="currentColor"
             viewBox="0 0 20 20">
            <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"/>
        </svg>
    </button>
    {{-- Logout Btn--}}

    <form method="POST" action="{{ filament()->getLogoutUrl() }}">
        @csrf
        <button type="submit"
                class="backdrop-blur-[16px] cursor-pointer backdrop-saturate-[180%] bg-white/5 border rounded-xl sm:rounded-2xl p-3 sm:p-4 hover:scale-110 transition-all duration-500 ease-out shadow-2xl group w-full"
                :class="darkMode ? 'border-white/10' : 'border-slate-300'">
            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
            </svg>
        </button>
    </form>
    {{--Widget--}}
    <button @click="widgetOpen = !widgetOpen"
            class="cursor-pointer backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border rounded-xl sm:rounded-2xl p-3 sm:p-4 hover:scale-110 transition-all duration-500 ease-out shadow-2xl group"
            :class="darkMode ? 'border-white/10' : 'border-slate-300'">
        <svg class="w-5 h-5 sm:w-6 sm:h-6" :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'" fill="none"
             stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
    </button>
</div>

