@php
    $isRtl = app()->getLocale() === 'fa';
@endphp
<div class="dock-min shrink-0">
    <x-icon-button tooltip="{{ __('resources/general/strings.nav_dock.switch_to_peek') }}">
        @if ($isRtl)
            <x-heroicon-o-chevron-double-right class="h-[18px] w-[18px] opacity-70 transition-opacity duration-300 group-hover:opacity-100"/>
        @else
            <x-heroicon-o-chevron-double-left class="h-[18px] w-[18px] opacity-70 transition-opacity duration-300 group-hover:opacity-100"/>
        @endif
    </x-icon-button>
</div>