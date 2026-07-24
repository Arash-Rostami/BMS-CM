<x-filament-widgets::widget>
    <x-filament::section
        :heading="__('resources/dashboard/strings.widgets.pipeline_stalls.heading')"
        :description="__('resources/dashboard/strings.widgets.pipeline_stalls.description')"
    >
        @if (empty($stalls))
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('resources/dashboard/strings.widgets.pipeline_stalls.empty') }}
            </p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-white/10">
                @foreach ($stalls as $stall)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <a href="{{ $stall->url }}" class="flex items-center gap-3 text-sm font-medium text-gray-950 hover:underline dark:text-white">
                            <x-filament::badge color="gray">
                                {{ __("resources/dashboard/strings.widgets.pipeline_stalls.record_types.{$stall->record_type}") }}
                            </x-filament::badge>
                            {{ $stall->record_number }}
                        </a>
                        <x-filament::badge color="danger">
                            {{ $stall->days_overdue }} {{ __('resources/dashboard/strings.widgets.pipeline_stalls.days_overdue') }}
                        </x-filament::badge>
                    </li>
                @endforeach
            </ul>
        @endif

        <x-slot name="footer">
            <x-metric-legend
                :what="__('resources/dashboard/strings.widgets.legend.pipeline_stalls.what')"
                :data="__('resources/dashboard/strings.widgets.legend.pipeline_stalls.data')"
                :why="__('resources/dashboard/strings.widgets.legend.pipeline_stalls.why')"
                :technical="auth()->user()?->isAdmin() ? __('resources/dashboard/strings.widgets.legend.pipeline_stalls.technical') : null"
            />
        </x-slot>
    </x-filament::section>
</x-filament-widgets::widget>
