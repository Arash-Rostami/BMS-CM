<div x-data="triWidget()"
     class="fixed top-1/5 sm:top-1/6 {{ $isRtl ? 'left-4 sm:left-6' : 'right-4 sm:right-6' }} z-50 flex flex-col gap-2 sm:gap-3"
     dir="ltr">
    <!-- Panel -->
    <div x-show="widgetOpen" @click.away="widgetOpen = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute {{ $isRtl ? 'left-0' : 'right-0' }} mt-3 backdrop-blur-[16px] backdrop-saturate-[180%] bg-white/5 border rounded-2xl shadow-2xl overflow-hidden min-w-[320px] z-50 tri-widget-panel"
         :class="darkMode ? 'border-white/10' : 'border-slate-300'">

        <!-- Header -->
        <div class="flex items-center justify-between p-3.5 border-b"
             :class="darkMode ? 'border-white/5' : 'border-slate-300'">
            <div class="flex items-center gap-3">
                <div class="rounded-md p-2 bg-white/3">
                    <svg class="w-5 h-5" :class="darkMode ? 'text-cyan-300' : 'text-indigo-600'" fill="none"
                         stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
                <div>
                    <div class="text-sm font-medium mb-2" :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">
                        {{ __('dashboard/strings.widget_title') ?? 'Widgets' }}
                    </div>
                    <div class="text-xs opacity-70 text-gray-500">{{ __('dashboard/strings.widget_subtitle') ?? 'Clock · Timer · Music' }}</div>
                </div>
            </div>

            <button @click="widgetOpen=false" class="p-2 rounded hover:bg-white/6">
                <svg class="w-4 h-4" :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'" fill="none"
                     stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Body -->
        <div class="p-3.5 space-y-2">

            <!-- Tabs -->
            <div class="flex gap-2">
                <button @click="tab='clock'" :class="tab==='clock' ? 'tab-active' : 'tab'" aria-label="Clock"
                        :title="tab==='clock' ? 'Selected' : 'Clock'">
                    <span class="text-2xl filter grayscale opacity-[70%]"
                          :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">🕙</span>
                </button>
                <button @click="tab='timer'" :class="tab==='timer' ? 'tab-active' : 'tab'" aria-label="Timer"
                        :title="tab==='timer' ? 'Selected' : 'Timer'">
                    <span class="text-3xl filter grayscale opacity-[50%]"
                          :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">⏱️</span>
                </button>
                <button @click="tab='music'" :class="tab==='music' ? 'tab-active' : 'tab'" aria-label="Music"
                        :title="tab==='music' ? 'Selected' : 'Music'">
                    <span class="text-2xl filter grayscale opacity-[60%]"
                          :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">🎵</span>
                </button>
            </div>

            <!-- Clock Tab -->
            <div x-show="tab==='clock'" x-cloak>
                <div class="text-center py-6">
                    <!-- Clock Icon -->
                    <div class="relative mx-auto mb-6 w-24 h-24 flex items-center justify-center">
                        <div class="absolute inset-0 rounded-full opacity-20 animate-ping"
                             :class="darkMode ? 'bg-cyan-400' : 'bg-indigo-600'"></div>
                        <svg class="w-20 h-20 relative z-10" :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>

                    <!-- Time Display -->
                    <div class="mb-2">
                        <div class="text-xs opacity-60 text-gray-500 mb-2">{{ __('dashboard/strings.widget_local_time') ?? 'Local time' }}</div>
                        <div class="text-4xl font-bold tracking-tight mb-3" x-text="clockString"
                             :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'"
                             style="font-variant-numeric: tabular-nums;"></div>
                    </div>

                    <!-- Date Display -->
                    <div class="space-y-1">
                        <div class="text-sm opacity-80" :class="darkMode ? 'text-cyan-400/80' : 'text-indigo-600/80'"
                             x-text="dateString"></div>
                        <div class="text-xs opacity-60 text-gray-500" x-text="shamsiDateString"></div>
                    </div>
                </div>
            </div>

            <!-- Timer Tab -->
            <div x-show="tab==='timer'" x-cloak>
                <!-- Timer Display -->
                <div class="text-center py-4 mb-4">
                    <div class="relative mx-auto mb-4 w-32 h-32 flex items-center justify-center">
                        <!-- Animated Ring -->
                        <svg class="absolute inset-0 w-full h-full -rotate-90">
                            <circle cx="64" cy="64" r="56" fill="none" stroke="currentColor"
                                    :class="darkMode ? 'text-white/5' : 'text-slate-300/30'"
                                    stroke-width="8"/>
                            <circle cx="64" cy="64" r="56" fill="none" stroke="currentColor"
                                    :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'"
                                    stroke-width="8" stroke-linecap="round"
                                    :stroke-dasharray="`${(timer.seconds / 300) * 351.86} 351.86`"
                                    style="transition: stroke-dasharray 1s linear;"/>
                        </svg>

                        <!-- Timer Value -->
                        <div class="text-3xl font-bold" x-text="formatSeconds(timer.seconds)"
                             :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'"
                             style="font-variant-numeric: tabular-nums;"></div>
                    </div>

                    <div class="text-xs opacity-60 text-gray-500">
                        <span x-text="timer.running ? 'Running...' : 'Paused'"></span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex justify-center gap-3 mb-4">
                    <button @click="toggleTimer()"
                            class="p-4 rounded-full transition-all hover:scale-110 shadow-lg cursor-pointer"
                            :class="darkMode ? 'bg-cyan-400/20 hover:bg-cyan-400/30 text-cyan-400' : 'bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-600'"
                            :title="timer.running ? 'Pause' : 'Start'">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path x-show="!timer.running" d="M8 5v14l11-7z"/>
                            <path x-show="timer.running" d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                        </svg>
                    </button>

                    <button @click="resetTimer()"
                            class="p-4 rounded-full transition-all hover:scale-110 cursor-pointer"
                            :class="darkMode ? 'bg-white/5 hover:bg-white/10 text-cyan-400' : 'bg-slate-300/20 hover:bg-slate-300/40 text-indigo-600'"
                            title="Reset">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                    </button>

                    <button x-show="alarmInterval" @click="stopAlarm()"
                            class="p-4 rounded-full transition-all hover:scale-110 animate-pulse"
                            :class="darkMode ? 'bg-red-500/20 hover:bg-red-500/30 text-red-400' : 'bg-red-600/20 hover:bg-red-600/30 text-red-600'"
                            title="Stop Alarm">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6 6h12v12H6z"/>
                        </svg>
                    </button>
                </div>

                <!-- Presets -->
                <div class="space-y-2">
                    <div class="text-xs opacity-60 text-gray-500 mb-2">{{ __('dashboard/strings.widget_presets') ?? 'Quick presets' }}</div>
                    <div class="flex gap-2 flex-wrap">
                        <button class="chip" @click="setTimerPreset(300)"
                                :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">5m
                        </button>
                        <button class="chip" @click="setTimerPreset(600)"
                                :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">10m
                        </button>
                        <button class="chip" @click="setTimerPreset(900)"
                                :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">15m
                        </button>
                        <button class="chip" @click="setTimerPreset(1800)"
                                :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">30m
                        </button>
                        <button class="chip" @click="setTimerPreset(3600)"
                                :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'">60m
                        </button>
                    </div>

                    <input type="number" min="1" step="1" class="input-inline w-full"
                           placeholder="{{ __('dashboard/strings.widget_custom_mins') ?? 'Custom minutes' }}"
                           :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'"
                           x-model.number="customMins"
                           @dblclick="setTimerPreset(customMins*60)"
                           @blur="setTimerPreset(customMins*60)"
                           @keydown.enter.prevent="setTimerPreset(customMins*60)">
                </div>
            </div>

            <!-- Music Tab -->
            <div x-show="tab==='music'" x-cloak>
                <div class="text-sm opacity-80 text-gray-500 mb-3">{{ __('dashboard/strings.widget_player') ?? 'Player' }}</div>

                <!-- Album Art -->
                <div class="relative mx-auto mb-4 overflow-hidden rounded-2xl shadow-2xl"
                     :style="music.playing ? 'width: 140px; height: 140px;' : 'width: 100px; height: 100px;'"
                     style="transition: all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);">

                    <!-- Glow Effect -->
                    <div x-show="music.playing"
                         class="absolute inset-0 blur-2xl opacity-40"
                         :class="darkMode ? 'bg-cyan-400' : 'bg-indigo-600'"
                         style="animation: pulse 2s ease-in-out infinite;"></div>

                    <!-- Image -->
                    <div class="relative w-full h-full"
                         x-show="music.playing"
                         x-transition:enter="transition ease-out duration-700"
                         x-transition:enter-start="opacity-0 scale-0 rotate-180"
                         x-transition:enter-end="opacity-100 scale-100 rotate-0"
                         x-transition:leave="transition ease-in duration-300"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-75">
                        <img :src="currentTrack.image" :alt="currentTrack.title"
                             class="w-full h-full object-cover"
                             style="animation: float 3s ease-in-out infinite;">
                    </div>

                    <!-- Placeholder -->
                    <div x-show="!music.playing"
                         class="absolute inset-0 flex items-center justify-center backdrop-blur-sm"
                         :class="darkMode ? 'bg-white/5' : 'bg-slate-300/20'">
                        <svg class="w-12 h-12 opacity-40" :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                        </svg>
                    </div>
                </div>

                <!-- Track Info -->
                <div class="text-center mb-4">
                    <div class="font-semibold text-base mb-1" x-text="currentTrack.title"
                         :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'"></div>
                    <div class="text-xs opacity-60 text-gray-500" x-text="currentTrack.time"></div>
                </div>

                <!-- Progress Bar -->
                <div class="mb-4">
                    <input type="range" min="0" max="100" step="0.5"
                           x-model.number="music.progress"
                           @input="seek($event)"
                           class="w-full h-1.5 rounded-full appearance-none cursor-pointer"
                           :class="darkMode ? 'bg-white/10' : 'bg-slate-300/40'"
                           style="background: linear-gradient(to right, currentColor var(--progress, 0%), transparent var(--progress, 0%));"
                           :style="`--progress: ${music.progress}%`">
                    <div class="flex justify-between text-xs opacity-60 mt-2 text-gray-500">
                        <span x-text="formatSeconds(Math.round(music.position||0))"></span>
                        <span x-text="formatSeconds(Math.round(music.duration||0))"></span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="flex items-center justify-center gap-3 mb-4">
                    <button @click="prev()"
                            class="p-3 rounded-full transition-all hover:scale-110"
                            :class="darkMode ? 'bg-white/5 hover:bg-white/10 text-cyan-400' : 'bg-slate-300/20 hover:bg-slate-300/40 text-indigo-600'"
                            title="Previous">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/>
                        </svg>
                    </button>

                    <button @click="playPause()"
                            class="p-4 rounded-full transition-all hover:scale-110 shadow-lg"
                            :class="darkMode ? 'bg-cyan-400/20 hover:bg-cyan-400/30 text-cyan-400' : 'bg-indigo-600/20 hover:bg-indigo-600/30 text-indigo-600'"
                            :title="music.playing ? 'Pause' : 'Play'">
                        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                            <path x-show="!music.playing" d="M8 5v14l11-7z"/>
                            <path x-show="music.playing" d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                        </svg>
                    </button>

                    <button @click="next()"
                            class="p-3 rounded-full transition-all hover:scale-110"
                            :class="darkMode ? 'bg-white/5 hover:bg-white/10 text-cyan-400' : 'bg-slate-300/20 hover:bg-slate-300/40 text-indigo-600'"
                            title="Next">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                        </svg>
                    </button>
                </div>

                <!-- Volume -->
                <div class="flex items-center gap-3 px-2">
                    <svg class="w-4 h-4 opacity-60" :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'"
                         fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/>
                    </svg>
                    <input type="range" min="0" max="1" step="0.01"
                           class="flex-1 h-1.5 rounded-full appearance-none cursor-pointer"
                           :class="darkMode ? 'bg-white/10' : 'bg-slate-300/40'"
                           x-model.number="music.volume"
                           @input="setVolume()">
                    <span class="text-xs opacity-60 w-8 text-right"
                          :class="darkMode ? 'text-cyan-400' : 'text-indigo-600'"
                          x-text="Math.round(music.volume * 100) + '%'"></span>
                </div>
            </div>

        </div>
    </div>
</div>
