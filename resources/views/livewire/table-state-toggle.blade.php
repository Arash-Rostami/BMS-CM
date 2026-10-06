@php
    $tooltip = $persisted ? __('resources/general/strings.table_state.disable') : __('resources/general/strings.table_state.enable');
@endphp

<div class="hidden shrink-0 items-center lg:flex" style="order: 2">
    <x-icon-button
        wire:click="toggle"
        wire:loading.attr="disabled"
        :tooltip="$tooltip"
        class="disabled:pointer-events-none disabled:opacity-50"
    >
        <x-heroicon-o-cpu-chip
            class="h-[22px] w-[22px] transition-all duration-300 group-hover:opacity-100 {{ $persisted ? 'text-primary-600 opacity-100 dark:text-primary-400' : 'opacity-80' }}"
        />
    </x-icon-button>
</div>