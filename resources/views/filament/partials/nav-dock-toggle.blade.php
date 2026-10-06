<div class="hidden shrink-0 items-center lg:flex" style="order: 5">
    <x-icon-button
        x-cloak
        x-show="$store.navDock === 'side'"
        tooltip="{{ __('resources/general/strings.nav_dock.switch_to_bottom') }}"
        x-on:click="setNavDock('bottom')"
    >
        <x-heroicon-o-view-columns class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
    </x-icon-button>

    <x-icon-button
        x-cloak
        x-show="$store.navDock !== 'side'"
        tooltip="{{ __('resources/general/strings.nav_dock.switch_to_side') }}"
        x-on:click="setNavDock('side')"
    >
        <x-heroicon-o-view-columns class="h-[22px] w-[22px] rotate-90 opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
    </x-icon-button>
</div>