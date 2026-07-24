<div class="hidden shrink-0 items-center lg:flex">
    <x-icon-button
        x-cloak
        x-show="$store.navDock !== 'bottom'"
        tooltip="{{ __('resources/general/strings.nav_dock.switch_to_bottom') }}"
        x-on:click="localStorage.setItem('nav_dock', 'bottom'); $store.navDock = 'bottom';"
    >
        <x-heroicon-o-view-columns class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
    </x-icon-button>

    <x-icon-button
        x-cloak
        x-show="$store.navDock === 'bottom'"
        tooltip="{{ __('resources/general/strings.nav_dock.switch_to_side') }}"
        x-on:click="localStorage.setItem('nav_dock', 'side'); $store.navDock = 'side';"
    >
        <x-heroicon-o-view-columns class="h-[22px] w-[22px] rotate-90 opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
    </x-icon-button>
</div>
