<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('resources/dashboard/strings.widgets.calendar.heading')"
        :description="__('resources/dashboard/strings.widgets.calendar.description')"
    >
        <div x-data="{ view: 'month' }">
            @include('filament.widgets.calendar-grid.header')
            @include('filament.widgets.calendar-grid.month')
            @include('filament.widgets.calendar-grid.agenda')
        </div>

        @include('filament.widgets.calendar-grid.legend')

        <x-slot name="footer">
            <x-metric-legend
                :what="__('resources/dashboard/strings.widgets.legend.calendar.what')"
                :data="__('resources/dashboard/strings.widgets.legend.calendar.data')"
                :why="__('resources/dashboard/strings.widgets.legend.calendar.why')"
                :technical="auth()->user()?->isAdmin() ? __('resources/dashboard/strings.widgets.legend.calendar.technical') : null"
            />
        </x-slot>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>