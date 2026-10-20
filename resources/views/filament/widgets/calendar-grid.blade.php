<x-filament-widgets::widget>
    <x-filament::section
        :description="__('resources/dashboard/strings.widgets.calendar.description')"
    >
        @include('filament.widgets.calendar-grid.header')
        @include('filament.widgets.calendar-grid.month')

        @include('filament.widgets.calendar-grid.legend')

        <x-slot name="footer">
            <x-metric-legend
                wire:key="cal-guide-{{ $this->ruleDetails ? $ruleId : 'none' }}"
                :open="(bool) $this->ruleDetails"
                :generic="! $this->ruleDetails"
                :what="__('resources/dashboard/strings.widgets.legend.calendar.what')"
                :data="__('resources/dashboard/strings.widgets.legend.calendar.data')"
                :why="__('resources/dashboard/strings.widgets.legend.calendar.why')"
                :technical="auth()->user()?->isAdmin() ? __('resources/dashboard/strings.widgets.legend.calendar.technical') : null"
            >
                @include('filament.widgets.calendar-grid.rule-details')
            </x-metric-legend>
        </x-slot>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
