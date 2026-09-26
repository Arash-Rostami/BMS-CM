<div class="hidden shrink-0 items-center lg:flex">
    <div class="tb-divider" aria-hidden="true"></div>

    <div class="relative" x-data="{ open: false }">
        <x-icon-button
            x-on:click="open = !open"
            tooltip="{{ __('resources/general/strings.topbar.preferences') }}"
            aria-haspopup="true"
            x-bind:aria-expanded="open"
        >
            <x-heroicon-o-adjustments-horizontal class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
        </x-icon-button>

        <div x-cloak
             x-show="open"
             @click.away="open = false"
             @keydown.escape.window="open = false"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="tb-menu w-80">
            @include('filament.partials.calendar-toggle')

            <button type="button" class="tb-pref-row" role="switch"
                    x-bind:aria-checked="$store.navDock === 'bottom'"
                    x-on:click="setNavDock($store.navDock === 'side' ? 'bottom' : 'side')">
                <span class="tb-pref-label">{{ __('resources/general/strings.topbar.pref_dock') }}</span>
                <span class="tb-pref-state">
                    <span x-cloak x-show="$store.navDock === 'side'">{{ __('resources/general/strings.topbar.state_side') }}</span>
                    <span x-cloak x-show="$store.navDock !== 'side'">{{ __('resources/general/strings.topbar.state_bottom') }}</span>
                </span>
            </button>

            <button type="button" class="tb-pref-row" role="switch"
                    x-bind:aria-checked="$store.topbarPinned"
                    x-on:click="localStorage.setItem('topbar_pinned', $store.topbarPinned ? '0' : '1'); $store.topbarPinned = !$store.topbarPinned">
                <span class="tb-pref-label">{{ __('resources/general/strings.topbar.pref_pin') }}</span>
                <span class="tb-pref-state">
                    <span x-cloak x-show="$store.topbarPinned">{{ __('resources/general/strings.topbar.state_pinned') }}</span>
                    <span x-cloak x-show="!$store.topbarPinned">{{ __('resources/general/strings.topbar.state_auto') }}</span>
                </span>
            </button>

            <button type="button" class="tb-pref-row" role="switch"
                    x-bind:aria-checked="$store.tableDensityCompact"
                    x-on:click="setTableDensity($store.tableDensityCompact ? 'comfortable' : 'compact')">
                <span class="tb-pref-label">{{ __('resources/general/strings.topbar.pref_density') }}</span>
                <span class="tb-pref-state">
                    <span x-cloak x-show="$store.tableDensityCompact">{{ __('resources/general/strings.topbar.state_compact') }}</span>
                    <span x-cloak x-show="!$store.tableDensityCompact">{{ __('resources/general/strings.topbar.state_comfortable') }}</span>
                </span>
            </button>

            <button type="button" class="tb-pref-row" role="switch"
                    x-bind:aria-checked="$store.isFullscreen"
                    x-on:click="toggleFullscreen()">
                <span class="tb-pref-label">{{ __('resources/general/strings.topbar.pref_fullscreen') }}</span>
                <span class="tb-pref-state">
                    <span x-cloak x-show="$store.isFullscreen">{{ __('resources/general/strings.topbar.state_on') }}</span>
                    <span x-cloak x-show="!$store.isFullscreen">{{ __('resources/general/strings.topbar.state_off') }}</span>
                </span>
            </button>

            <div class="tb-pref-row">
                <span class="tb-pref-label">{{ __('resources/general/strings.topbar.pref_palette') }}</span>
                @include('filament.partials.theme', ['surface' => 'row'])
            </div>
        </div>
    </div>
</div>