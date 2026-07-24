@php
    $current = $isJalali ? __('resources/general/strings.calendar_toggle.jalali_abbr') : __('resources/general/strings.calendar_toggle.gregorian_abbr');
    $tooltip = $isJalali ? __('resources/general/strings.calendar_toggle.switch_to_gregorian') : __('resources/general/strings.calendar_toggle.switch_to_jalali');
    $padding = app()->getLocale() === 'fa' ? 'bottom-[12px]' : 'bottom-[10px]';
@endphp

<div class="flex shrink-0 items-center">
    <x-icon-button
        wire:click="toggle"
        wire:loading.attr="disabled"
        :tooltip="$tooltip"
        class="disabled:pointer-events-none disabled:opacity-50"
    >
        <div wire:loading.remove wire:target="toggle" class="relative flex h-full w-full items-center justify-center transition-transform duration-200 active:scale-90">
            <x-heroicon-o-calendar class="h-[24px] w-[24px] opacity-80 transition-opacity duration-200 group-hover:opacity-100" />

            <span class="absolute inset-x-0 {{ $padding }} text-center text-[9px] font-black tracking-tighter transition-colors duration-200 group-hover:text-primary-700 dark:text-primary-400 dark:group-hover:text-primary-300" style="line-height: 1;">
                {{ $current }}
            </span>
        </div>

        <x-filament::loading-indicator wire:loading wire:target="toggle" class="absolute h-4 w-4 text-primary-500" />
    </x-icon-button>
</div>
