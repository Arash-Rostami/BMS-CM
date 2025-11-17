<div class="glass border border-white/10 rounded-2xl sm:rounded-3xl p-4 sm:p-6 lg:p-8 shadow-2xl">
    <div class="flex items-center justify-between gap-2 sm:gap-3">
        <!-- Stage 1 -->
        <div class="flex items-center gap-2 sm:gap-3 h-12 sm:h-14">
            <div
                class="flex items-center justify-center w-7 h-7 sm:w-9 sm:h-9 rounded-lg backdrop-blur-md bg-blue-500/10 border border-blue-500/30 flex-shrink-0 shadow-lg shadow-blue-500/20">
                <span class="text-blue-500 font-bold text-sm sm:text-base">1</span>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
        {{ __('dashboard/strings.flow.request') }}
      </span>
        </div>

        <!-- Connector 1 -->
        <div class="flex items-center justify-center h-12 sm:h-14 opacity-40">
            <svg
                class="block w-3 h-8 sm:w-4 sm:h-10 flex-shrink-0"
                :class="darkMode ? 'text-slate-500' : 'text-slate-400'"
                viewBox="0 0 12 40"
                preserveAspectRatio="xMidYMid meet"
                fill="none"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    d="{{ $isRtl ? 'M8 2L2 20L8 38' : 'M4 2L10 20L4 38' }}"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-dasharray="3 3"
                />
            </svg>
        </div>

        <!-- Stage 2 -->
        <div class="flex items-center gap-2 sm:gap-3 h-12 sm:h-14">
            <div
                class="flex items-center justify-center w-7 h-7 sm:w-9 sm:h-9 rounded-lg backdrop-blur-md bg-green-500/10 border border-green-500/30 flex-shrink-0 shadow-lg shadow-green-500/20">
                <span class="text-green-500 font-bold text-sm sm:text-base">2</span>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
        {{ __('dashboard/strings.flow.order') }}
      </span>
        </div>

        <!-- Connector 2  -->
        <div class="flex items-center justify-center h-12 sm:h-14 opacity-40">
            <svg
                class="block w-3 h-8 sm:w-4 sm:h-10 flex-shrink-0"
                :class="darkMode ? 'text-slate-500' : 'text-slate-400'"
                viewBox="0 0 12 40"
                preserveAspectRatio="xMidYMid meet"
                fill="none"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    d="{{ $isRtl ? 'M8 2L2 20L8 38' : 'M4 2L10 20L4 38' }}"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-dasharray="3 3"
                />
            </svg>
        </div>

        <!-- Stage 3 -->
        <div class="flex items-center gap-2 sm:gap-3 h-12 sm:h-14">
            <div
                class="flex items-center justify-center w-7 h-7 sm:w-9 sm:h-9 rounded-lg backdrop-blur-md bg-amber-500/10 border border-amber-500/30 flex-shrink-0 shadow-lg shadow-amber-500/20">
                <span class="text-amber-500 font-bold text-sm sm:text-base">3</span>
            </div>
            <span :class="darkMode ? 'text-slate-300' : 'text-slate-700'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
        {{ __('dashboard/strings.flow.payment') }}
      </span>
        </div>

        <!-- Connector 3 -->
        <div class="flex items-center justify-center h-12 sm:h-14 opacity-40">
            <svg
                class="block w-3 h-8 sm:w-4 sm:h-10 flex-shrink-0"
                :class="darkMode ? 'text-slate-600' : 'text-slate-400'"
                viewBox="0 0 12 40"
                preserveAspectRatio="xMidYMid meet"
                fill="none"
                stroke="currentColor"
                aria-hidden="true"
            >
                <path
                    d="{{ $isRtl ? 'M8 2L2 20L8 38' : 'M4 2L10 20L4 38' }}"
                    stroke-width="2.5"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-dasharray="3 3"
                />
            </svg>
        </div>

        <!-- Stage 4 -->
        <div class="flex items-center gap-2 sm:gap-3 h-12 sm:h-14">
            <div
                class="flex items-center justify-center w-7 h-7 sm:w-9 sm:h-9 rounded-lg backdrop-blur-md bg-purple-500/5 border border-purple-500/20 flex-shrink-0 shadow-lg shadow-purple-500/10">
                <span class="text-purple-500/60 font-bold text-sm sm:text-base">4</span>
            </div>
            <span :class="darkMode ? 'text-slate-400' : 'text-slate-600'"
                  class="font-semibold text-xs sm:text-sm lg:text-base whitespace-nowrap">
        {{ __('dashboard/strings.flow.logistics') }}
      </span>
        </div>
    </div>
</div>
