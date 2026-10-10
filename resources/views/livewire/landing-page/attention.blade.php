@php
    $rowClass = 'flex items-center gap-3 py-2.5 px-2 -mx-2 rounded-md transition-colors duration-150 lp-surface-hover';
    $textPrimary = "darkMode ? 'text-slate-100' : 'text-slate-800'";
    $textSecondary = "darkMode ? 'text-slate-400' : 'text-slate-500'";
@endphp

<div x-data="{ attentionOpen: true }">
    <x-accordion-header open="attentionOpen"
                        icon="heroicon-o-bell-alert"
                        title="{{ $title }}"
                        count="{{ $total }}"
                        countLabel="{{ $countLabel }}">

        @if ($rows === [])
            <x-empty-state icon="heroicon-o-check-circle" hint="{{ $emptyHint }}" size="lg"/>
        @else
            <ul class="divide-y" :class="darkMode ? 'divide-white/5' : 'divide-slate-100'">
                @foreach ($rows as $row)
                    <li>
                        <a @if ($row['url']) href="{{ $row['url'] }}" target="_blank" rel="noopener noreferrer" @endif
                           class="{{ $rowClass }}">
                            <span class="cal-dot flex-shrink-0" style="--cal-c: var(--cal-color-{{ $row['color'] }})"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold truncate" :class="{!! $textPrimary !!}">{{ $row['label'] }}</span>
                                <span class="block text-[11px] truncate" :class="{!! $textSecondary !!}">{{ $row['module'] }} · {{ $row['rule'] }}</span>
                            </span>
                            <span class="text-[11px] whitespace-nowrap flex-shrink-0" :class="{!! $textSecondary !!}">{{ $row['date'] }}</span>
                            @if ($row['overdue'])
                                <span class="tb-badge tb-danger whitespace-nowrap flex-shrink-0">{{ $overdueLabel }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            @if($moreLabel)
                <p class="text-[11px] pt-1" :class="{!! $textSecondary !!}">{{ $moreLabel }}</p>
            @endif
        @endif

        <div class="mt-3 pt-3 border-t lp-divider flex justify-end">
            <a href="{{ $dashboardUrl }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-600 dark:text-primary-400 hover:underline">
                <x-heroicon-o-calendar-days class="w-3.5 h-3.5"/>
                {{ $openCalendarLabel }}
            </a>
        </div>
    </x-accordion-header>
</div>