<div class="mb-6 sm:mb-8">
    <a href="{{ route('filament.dashboard.pages.dashboard') }}" target="_blank" rel="noopener noreferrer"
       class="block group">
        <div
            class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-4 sm:p-6 transition-all duration-300 shadow-2xl card-3d relative overflow-hidden">
            <div class="absolute inset-0 shimmer-effect opacity-0 group-hover:opacity-100"></div>
            <div class="flex items-center justify-between relative z-10 gap-3">
                <div class="flex items-center gap-3 sm:gap-4 min-w-0 flex-1">
                    <div
                        class="w-12 h-12 sm:w-14 sm:h-14 bg-gradient-to-br from-cyan-500 to-blue-600 rounded-xl sm:rounded-2xl flex items-center justify-center icon-container shadow-lg flex-shrink-0">
                        <svg class="w-6 h-6 sm:w-7 sm:h-7 text-white" fill="none" stroke="currentColor"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm truncate" :class="darkMode ? 'text-slate-400' : 'text-slate-600'">
                            {{ __('dashboard/strings.return_to_main_panel') }}
                        </p>
                    </div>
                </div>
                <img src="{{ asset('img/logos/curves.png') }}" class="w-32 h-auto " alt="logo">
            </div>
        </div>
    </a>
</div>
