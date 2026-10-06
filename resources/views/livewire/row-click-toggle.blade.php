@php
    $tooltip = $editOnClick
        ? __('resources/general/strings.row_click.edit')
        : __('resources/general/strings.row_click.view');
@endphp

<div class="hidden shrink-0 items-center lg:flex" style="order: 2">
    <x-icon-button
        wire:click="toggle"
        wire:loading.attr="disabled"
        :tooltip="$tooltip"
        class="disabled:pointer-events-none disabled:opacity-50"
    >
        @if ($editOnClick)
            <x-heroicon-o-pencil-square class="h-[22px] w-[22px] text-primary-600 opacity-100 transition-all duration-300 dark:text-primary-400" />
        @else
            <x-heroicon-o-eye class="h-[22px] w-[22px] opacity-80 transition-opacity duration-300 group-hover:opacity-100" />
        @endif
    </x-icon-button>
</div>
