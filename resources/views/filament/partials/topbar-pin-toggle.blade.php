<div class="hidden shrink-0 items-center lg:flex ms-1">
    <x-icon-button
        x-cloak
        x-show="!$store.topbarPinned"
        tooltip="{{ __('resources/general/strings.topbar_pin.pin') }}"
        x-on:click="localStorage.setItem('topbar_pinned', '1'); $store.topbarPinned = true;"
    >
        <x-heroicon-o-map-pin class="h-[22px] w-[22px] rotate-45 opacity-80 transition-all duration-300 group-hover:opacity-100" />
    </x-icon-button>

    <x-icon-button
        x-cloak
        x-show="$store.topbarPinned"
        tooltip="{{ __('resources/general/strings.topbar_pin.unpin') }}"
        x-on:click="localStorage.setItem('topbar_pinned', '0'); $store.topbarPinned = false;"
    >
        <x-heroicon-o-map-pin class="h-[22px] w-[22px] text-primary-600 opacity-100 transition-all duration-300 dark:text-primary-400" />
    </x-icon-button>
</div>
