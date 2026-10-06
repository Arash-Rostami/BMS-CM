<div class="hidden shrink-0 items-center lg:flex" style="order: 3">
    <x-icon-button
        x-cloak
        x-show="!$store.tableStackingClassic"
        tooltip="{{ __('resources/general/strings.stacked_table.disable') }}"
        x-on:click="setTableStacking('classic')"
    >
        <x-heroicon-o-rectangle-group class="h-[22px] w-[22px] text-primary-600 opacity-100 transition-all duration-300 dark:text-primary-400" />
    </x-icon-button>

    <x-icon-button
        x-cloak
        x-show="$store.tableStackingClassic"
        tooltip="{{ __('resources/general/strings.stacked_table.enable') }}"
        x-on:click="setTableStacking('stacked')"
    >
        <x-heroicon-o-rectangle-group class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
    </x-icon-button>
</div>
