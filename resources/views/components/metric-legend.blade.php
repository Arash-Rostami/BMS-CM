@props(['what', 'data', 'why', 'technical' => null])

<div x-data="{ open: false }" class="fi-wi-legend">
    <button
        type="button"
        x-on:click="open = !open"
        class="fi-wi-legend-toggle"
    >
        <x-heroicon-o-information-circle class="w-4 h-4" />
        <span x-text="open ? @js(__('resources/dashboard/strings.widgets.legend.toggle_hide')) : @js(__('resources/dashboard/strings.widgets.legend.toggle_show'))"></span>
    </button>

    <div x-show="open" x-cloak class="fi-wi-legend-body">
        <p><strong>{{ __('resources/dashboard/strings.widgets.legend.what_label') }}:</strong> {{ $what }}</p>
        <p><strong>{{ __('resources/dashboard/strings.widgets.legend.data_label') }}:</strong> {{ $data }}</p>
        <p><strong>{{ __('resources/dashboard/strings.widgets.legend.why_label') }}:</strong> {{ $why }}</p>
        @if ($technical)
            <div class="h-px w-full bg-gradient-to-r from-transparent via-gray-300 to-transparent dark:via-neutral-700"></div>
            <p class="fi-wi-legend-technical"><strong>{{ __('resources/dashboard/strings.widgets.legend.technical_label') }}:</strong> <code>{{ $technical }}</code></p>
        @endif
    </div>
</div>
