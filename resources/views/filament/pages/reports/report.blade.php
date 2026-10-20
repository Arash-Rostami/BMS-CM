@php
    $banner = $this->banner;
    $tones = ['info', 'success', 'warning', 'danger'];
@endphp

<x-filament-panels::page>
    @include('filament.pages.reports.guide', ['guide' => $this->guide])

    {{ $this->filtersForm }}

    @if ($this->summary !== [])
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ($this->summary as $card)
                <x-filament::section :heading="$card['label']" compact wire:key="report-card-{{ $card['key'] }}">
                    <div class="space-y-1">
                        @foreach ($card['lines'] as $line)
                            <div
                                @class([
                                    'flex items-center justify-between gap-3 rounded px-1 text-sm',
                                    'cursor-pointer transition hover:bg-gray-100 dark:hover:bg-white/10' => isset($line['quick']),
                                    'bg-primary-50 ring-1 ring-primary-500 dark:bg-primary-500/10' => isset($line['quick']) && $this->quick === $line['quick'],
                                ])
                                @isset($line['quick'])
                                    role="button"
                                    tabindex="0"
                                    wire:click="filterTableBy('{{ $line['quick'] }}')"
                                    wire:key="report-line-{{ $card['key'] }}-{{ $line['quick'] }}"
                                @endisset
                            >
                                <span style="color: var(--custom-fourth)">{{ $line['label'] }}</span>
                                @isset($line['url'])
                                    <a href="{{ $line['url'] }}" x-on:click.stop class="ms-auto text-xs underline" style="color: var(--custom-fourth)">{{ __('resources/report/strings.quick.open') }}</a>
                                @endisset
                                <span
                                    dir="ltr"
                                    @class([
                                        'font-semibold',
                                        'tb-badge tb-'.($card['tone'] ?? '') => in_array($card['tone'] ?? null, $tones, true),
                                    ])
                                >{{ $line['value'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    {{ $this->analytics }}

    {{ $this->table }}

    @if ($banner['total'] > 0)
        <x-filament::section
            collapsible
            collapsed
            icon="heroicon-m-exclamation-triangle"
            :heading="__('resources/report/strings.banner.open_attention', ['count' => $banner['total']])"
        >
            <div class="flex flex-wrap items-center gap-3">
                @foreach (['error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $severity => $color)
                    @if ($banner[$severity] > 0)
                        <x-filament::badge tag="button" :color="$color" wire:key="banner-{{ $severity }}" wire:click="$dispatch('report-severity', { severity: '{{ $severity }}' })">
                            {{ __('resources/report/strings.attention.severity.'.$severity) }}: {{ $banner[$severity] }}
                        </x-filament::badge>
                    @endif
                @endforeach
                <x-filament::badge tag="button" color="gray" wire:click="$dispatch('report-severity', { severity: null })">
                    {{ __('resources/report/strings.banner.show_all') }}
                </x-filament::badge>
            </div>
            @if ($banner['info'] > 0)
                <p class="mt-2 text-xs" style="color: var(--custom-fourth)">
                    {{ __('resources/report/strings.banner.no_history') }}
                </p>
            @endif

            @if ($attention = $this->attention())
                <div class="mt-6 border-t border-gray-200 pt-6 dark:border-white/10">
                    @livewire($attention, ['pageFilters' => $this->filters ?? [], ...$attention::getDefaultProperties()], key('report-attention'))
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>
