<div class="hidden shrink-0 items-center lg:flex" style="order: 4">
    <x-icon-button
        x-cloak
        x-show="!$store.tableDensityCompact"
        tooltip="{{ __('resources/general/strings.table_density.compact') }}"
        x-on:click="setTableDensity('compact')"
    >
        <x-heroicon-o-bars-3-bottom-left class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
    </x-icon-button>

    <x-icon-button
        x-cloak
        x-show="$store.tableDensityCompact"
        tooltip="{{ __('resources/general/strings.table_density.comfortable') }}"
        x-on:click="setTableDensity('comfortable')"
    >
        <x-heroicon-o-bars-3-bottom-left class="h-[22px] w-[22px] text-primary-600 opacity-100 transition-all duration-300 dark:text-primary-400" />
    </x-icon-button>
</div>
