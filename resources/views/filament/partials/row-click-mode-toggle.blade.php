<div class="hidden shrink-0 items-center lg:flex" style="order: 2">
    <x-icon-button
        x-cloak
        x-show="!$store.rowClickEdit"
        tooltip="{{ __('resources/general/strings.row_click.view_tooltip') }}"
        x-on:click="setRowClickMode('edit')"
    >
        <x-heroicon-o-eye class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
    </x-icon-button>

    <x-icon-button
        x-cloak
        x-show="$store.rowClickEdit"
        tooltip="{{ __('resources/general/strings.row_click.edit_tooltip') }}"
        x-on:click="setRowClickMode('view')"
    >
        <x-heroicon-o-pencil-square class="h-[22px] w-[22px] text-primary-600 opacity-100 transition-all duration-300 dark:text-primary-400" />
    </x-icon-button>
</div>
