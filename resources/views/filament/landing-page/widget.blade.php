@php
    $side = $isRtl ? 'left' : 'right';
    $oppSide = $isRtl ? 'right' : 'left';
    $primaryColor = "darkMode ? 'text-primary-400' : 'text-primary-600'";

    $tabs = [
        'clock' => [
            'aria' => 'Clock',
            'path' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        'timer' => [
            'aria' => 'Timer',
            'path' => 'M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0',
        ],
        'music' => [
            'aria' => 'Music',
            'path' => 'M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3',
        ],
    ];

    $presets = [
        '5m' => 300,
        '10m' => 600,
        '15m' => 900,
        '30m' => 1800,
        '60m' => 3600,
    ];
@endphp

<div x-data="triWidget()" dir="ltr">
    <div class="fixed top-1/5 sm:top-1/6 {{ $side }}-4 sm:{{ $side }}-6 z-50 flex flex-col gap-1.5">
        <div x-show="widgetOpen && !widgetMinimized" x-cloak @click.away="if (!music.playing) widgetOpen = false"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute {{ $side }}-0 mt-2 lp-float rounded-lg overflow-hidden min-w-[320px] z-50">

            <div class="flex items-center justify-between p-3.5 border-b lp-divider">
                <div class="flex items-center gap-3">
                    <div class="rounded-md p-2 border lp-divider">
                        <svg class="w-5 h-5" :class="{!! $primaryColor !!}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div>
                        <div class="text-sm font-semibold mb-1" :class="darkMode ? 'text-white' : 'text-slate-900'">
                            {{ __('dashboard/strings.widget_title') ?? 'Widgets' }}
                        </div>
                        <div class="text-xs text-slate-500">{{ __('dashboard/strings.widget_subtitle') ?? 'Clock · Timer · Music' }}</div>
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <button @click="widgetMinimized = true"
                            class="lp-surface-hover p-1.5 rounded-md transition-colors duration-150 text-slate-500 dark:text-slate-400"
                            title="{{ __('dashboard/strings.widget_minimize') ?? 'Minimize' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14"/>
                        </svg>
                    </button>
                    <button @click="widgetOpen=false; stopMusic()" class="lp-surface-hover p-1.5 rounded-md transition-colors duration-150 text-slate-500 dark:text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="p-3.5 space-y-2">
                <div class="flex gap-2">
                    @foreach ($tabs as $key => $tab)
                        <button @click="tab='{{ $key }}'"
                                class="lp-tab flex-1 text-center p-2"
                                :class="tab==='{{ $key }}' ? 'lp-tab-active' : ''"
                                aria-label="{{ $tab['aria'] }}"
                                :title="tab==='{{ $key }}' ? 'Selected' : '{{ $tab['aria'] }}'">
                            <svg class="w-4.5 h-4.5 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $tab['path'] }}"/>
                            </svg>
                        </button>
                    @endforeach
                </div>

                <div x-show="tab==='clock'" x-cloak>
                    <div class="text-center py-6">
                        <div class="relative mx-auto mb-6 w-16 h-16 flex items-center justify-center rounded-full border lp-divider">
                            <svg class="w-8 h-8" :class="{!! $primaryColor !!}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>

                        <div class="mb-2">
                            <div class="text-xs text-slate-500 mb-2">{{ __('dashboard/strings.widget_local_time') ?? 'Local time' }}</div>
                            <div class="text-4xl font-bold tracking-tight mb-3 tabular-nums" x-text="clockString" :class="{!! $primaryColor !!}"></div>
                        </div>

                        <div class="space-y-1">
                            <div class="text-sm" :class="darkMode ? 'text-primary-400/80' : 'text-primary-600/80'" x-text="dateString"></div>
                            <div class="text-xs text-slate-500" x-text="shamsiDateString"></div>
                        </div>
                    </div>
                </div>

                <div x-show="tab==='timer'" x-cloak>
                    <div class="text-center py-4 mb-4">
                        <div class="relative mx-auto mb-4 w-32 h-32 flex items-center justify-center">
                            <svg class="absolute inset-0 w-full h-full -rotate-90">
                                <circle cx="64" cy="64" r="56" fill="none" stroke="currentColor" :class="darkMode ? 'text-white/10' : 'text-slate-200'" stroke-width="6"/>
                                <circle cx="64" cy="64" r="56" fill="none" stroke="currentColor" :class="{!! $primaryColor !!}" stroke-width="6" stroke-linecap="round" :stroke-dasharray="`${(timer.seconds / 300) * 351.86} 351.86`" style="transition: stroke-dasharray 1s linear;"/>
                            </svg>

                            <div class="text-3xl font-bold tabular-nums" x-text="formatSeconds(timer.seconds)" :class="{!! $primaryColor !!}"></div>
                        </div>

                        <div class="text-xs text-slate-500">
                            <span x-text="timer.running ? 'Running...' : 'Paused'"></span>
                        </div>
                    </div>

                    <div class="flex justify-center gap-3 mb-4">
                        <button @click="toggleTimer()" class="lp-surface lp-surface-hover p-3.5 rounded-full cursor-pointer" :class="{!! $primaryColor !!}" :title="timer.running ? 'Pause' : 'Start'">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path x-show="!timer.running" d="M8 5v14l11-7z"/>
                                <path x-show="timer.running" d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                            </svg>
                        </button>

                        <button @click="resetTimer()" class="lp-surface lp-surface-hover p-3.5 rounded-full cursor-pointer" :class="{!! $primaryColor !!}" title="Reset">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </button>

                        <button x-show="alarmInterval" @click="stopAlarm()" class="p-3.5 rounded-full border animate-pulse transition-colors duration-150" :class="darkMode ? 'border-red-500/30 bg-red-500/10 text-red-400' : 'border-red-200 bg-red-50 text-red-700'" title="Stop Alarm">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 6h12v12H6z"/>
                            </svg>
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div class="text-xs text-slate-500 mb-2">{{ __('dashboard/strings.widget_presets') ?? 'Quick presets' }}</div>
                        <div class="flex gap-2 flex-wrap">
                            @foreach ($presets as $label => $seconds)
                                <button class="chip" @click="setTimerPreset({{ $seconds }})" :class="{!! $primaryColor !!}">{{ $label }}</button>
                            @endforeach
                        </div>

                        <input type="number" min="1" step="1" class="input-inline w-full"
                               placeholder="{{ __('dashboard/strings.widget_custom_mins') ?? 'Custom minutes' }}"
                               :class="{!! $primaryColor !!}"
                               x-model.number="customMins"
                               @dblclick="setTimerPreset(customMins*60)"
                               @blur="setTimerPreset(customMins*60)"
                               @keydown.enter.prevent="setTimerPreset(customMins*60)">
                    </div>
                </div>

                <div x-show="tab==='music'" x-cloak>
                    <div class="text-sm text-slate-500 mb-3">{{ __('dashboard/strings.widget_player') ?? 'Player' }}</div>

                    <div class="relative mx-auto mb-4 overflow-hidden rounded-lg border lp-divider"
                         :style="music.playing ? 'width: 120px; height: 120px;' : 'width: 96px; height: 96px;'"
                         style="transition: width 0.2s var(--md-motion), height 0.2s var(--md-motion);">

                        <div class="relative w-full h-full" x-show="music.playing" x-cloak>
                            <img :src="music.playing ? currentTrack.image : ''" :alt="currentTrack.title" class="w-full h-full object-cover">
                        </div>

                        <div x-show="!music.playing" class="absolute inset-0 flex items-center justify-center" :class="darkMode ? 'bg-white/5' : 'bg-slate-100'">
                            <svg class="w-10 h-10 opacity-40" :class="{!! $primaryColor !!}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                            </svg>
                        </div>
                    </div>

                    <div class="text-center mb-4">
                        <div class="font-semibold text-base mb-1" x-text="currentTrack.title" :class="{!! $primaryColor !!}"></div>
                        <div class="text-xs text-slate-500" x-text="currentTrack.time"></div>
                    </div>

                    <div class="mb-4">
                        <input type="range" min="0" max="100" step="0.5"
                               x-model.number="music.progress"
                               @input="seek($event)"
                               class="w-full h-1.5 rounded-full appearance-none cursor-pointer"
                               :class="darkMode ? 'bg-white/10' : 'bg-slate-200'"
                               style="background: linear-gradient(to right, currentColor var(--progress, 0%), transparent var(--progress, 0%));"
                               :style="`--progress: ${music.progress}%`">
                        <div class="flex justify-between text-xs text-slate-500 mt-2 tabular-nums">
                            <span x-text="formatSeconds(Math.round(music.position||0))"></span>
                            <span x-text="formatSeconds(Math.round(music.duration||0))"></span>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-3 mb-4">
                        <button @click="prev()" class="lp-surface lp-surface-hover p-2.5 rounded-full" :class="{!! $primaryColor !!}" title="Previous">
                            <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/>
                            </svg>
                        </button>

                        <button @click="playPause()" class="lp-surface lp-surface-hover p-3.5 rounded-full" :class="{!! $primaryColor !!}" :title="music.playing ? 'Pause' : 'Play'">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path x-show="!music.playing" d="M8 5v14l11-7z"/>
                                <path x-show="music.playing" d="M6 4h4v16H6V4zm8 0h4v16h-4V4z"/>
                            </svg>
                        </button>

                        <button @click="next()" class="lp-surface lp-surface-hover p-2.5 rounded-full" :class="{!! $primaryColor !!}" title="Next">
                            <svg class="w-4.5 h-4.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                            </svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-3 px-2">
                        <svg class="w-4 h-4 opacity-60" :class="{!! $primaryColor !!}" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02z"/>
                        </svg>
                        <input type="range" min="0" max="1" step="0.01"
                               class="flex-1 h-1.5 rounded-full appearance-none cursor-pointer"
                               :class="darkMode ? 'bg-white/10' : 'bg-slate-200'"
                               x-model.number="music.volume"
                               @input="setVolume()">
                        <span class="text-xs text-slate-500 w-8 text-right tabular-nums" x-text="Math.round(music.volume * 100) + '%'"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div x-show="widgetOpen && widgetMinimized" x-cloak
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed bottom-4 sm:bottom-6 {{ $oppSide }}-4 sm:{{ $oppSide }}-6 z-50">
        <button @click="widgetMinimized = false"
                class="lp-surface lp-surface-hover rounded-full p-3 shadow-lg flex items-center gap-2 group"
                title="{{ __('dashboard/strings.widget_expand') ?? 'Expand widget' }}">
            <svg class="w-4.5 h-4.5" :class="{!! $primaryColor !!}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6l4 2M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span x-show="music.playing" x-cloak class="w-1.5 h-1.5 rounded-full lp-dock-pulse animate-pulse"></span>
        </button>
    </div>
</div>
