<div class="hidden shrink-0 items-center lg:flex" style="order: 1">
    <x-icon-button
        x-cloak
        x-show="!$store.isFullscreen"
        tooltip="{{ __('resources/general/strings.fullscreen.enter') }}"
        x-on:click="toggleFullscreen()"
    >
        <x-heroicon-o-arrows-pointing-out class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
    </x-icon-button>

    <x-icon-button
        x-cloak
        x-show="$store.isFullscreen"
        tooltip="{{ __('resources/general/strings.fullscreen.exit') }}"
        x-on:click="toggleFullscreen()"
    >
        <x-heroicon-o-arrows-pointing-in class="h-[22px] w-[22px] text-primary-600 opacity-100 transition-all duration-300 dark:text-primary-400" />
    </x-icon-button>
</div>
