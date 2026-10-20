@if ($guide['sections'] !== [])
    @php
        $sectionIcons = ['filters' => 'heroicon-o-funnel', 'cards' => 'heroicon-o-squares-2x2', 'charts' => 'heroicon-o-chart-bar', 'table' => 'heroicon-o-table-cells', 'issues' => 'heroicon-o-exclamation-triangle'];
        $partIcons = ['meaning' => 'heroicon-m-light-bulb', 'how' => 'heroicon-m-calculator', 'change' => 'heroicon-m-adjustments-horizontal'];
    @endphp

    <x-filament::section
        collapsible
        collapsed
        icon="heroicon-o-information-circle"
        :heading="__('resources/report/strings.guide.heading')"
        :description="$guide['intro'] !== '' ? $guide['intro'] : null"
    >
        <div
            class="rounded-xl border border-gray-200 bg-gray-50/70 p-3 dark:border-white/10 dark:bg-white/5 sm:p-4"
            x-data="{ tab: '{{ array_key_first($guide['sections']) }}' }"
        >
            <x-filament::tabs>
                @foreach ($guide['sections'] as $name => $section)
                    <x-filament::tabs.item
                        alpine-active="tab === '{{ $name }}'"
                        x-on:click="tab = '{{ $name }}'"
                        :icon="$sectionIcons[$name] ?? null"
                        wire:key="guide-tab-{{ $name }}"
                    >
                        {{ $section['title'] ?? $name }}
                    </x-filament::tabs.item>
                @endforeach
            </x-filament::tabs>

            @foreach ($guide['sections'] as $name => $section)
                <div x-show="tab === '{{ $name }}'" x-cloak class="mt-4 space-y-4" wire:key="guide-{{ $name }}">
                    @if (! empty($section['intro']))
                        <p class="rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" style="color: var(--custom-fourth)">{{ $section['intro'] }}</p>
                    @endif

                    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                        @foreach ($section['items'] ?? [] as $key => $item)
                            <div class="space-y-2 rounded-lg bg-white p-3 text-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10" wire:key="guide-{{ $name }}-{{ $key }}">
                                <h4 class="font-semibold">{{ $item['title'] ?? $key }}</h4>
                                @foreach ($partIcons as $part => $partIcon)
                                    @if (! empty($item[$part]))
                                        <div class="flex gap-2">
                                            <x-filament::icon :icon="$partIcon" class="mt-0.5 h-4 w-4 shrink-0" style="color: var(--custom-fourth)" />
                                            <p>
                                                <span class="font-medium" style="color: var(--custom-fourth)">{{ __('resources/report/strings.guide.'.$part) }}:</span>
                                                {{ $item[$part] }}
                                            </p>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
@endif
