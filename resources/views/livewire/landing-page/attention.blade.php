@php
    $rowClass = 'flex items-center gap-3 py-2.5 px-2 -mx-2 rounded-md transition-colors duration-150 lp-surface-hover';
    $textPrimary = "darkMode ? 'text-slate-100' : 'text-slate-800'";
    $textSecondary = "darkMode ? 'text-slate-400' : 'text-slate-500'";
    $str = fn (string $key): string => __('resources/dashboard/strings.landing_page.attention.'.$key);
@endphp

<div class="cal-att" x-data="{ attentionOpen: true }">
    <x-accordion-header open="attentionOpen"
                        icon="heroicon-o-bell-alert"
                        title="{{ $title }}"
                        count="{{ $total }}"
                        countLabel="{{ $countLabel }}">

        <div class="flex flex-wrap items-center gap-2 mb-3">
            <button type="button" wire:click="prevMonth" class="cal-nav" title="{{ $prevLabel }}" aria-label="{{ $prevLabel }}">
                <x-heroicon-o-chevron-left class="h-4 w-4 rtl:rotate-180"/>
            </button>
            <span class="min-w-[8rem] text-center text-sm font-bold" :class="{!! $textPrimary !!}" aria-live="polite">{{ $monthLabel }}</span>
            <button type="button" wire:click="nextMonth" class="cal-nav" title="{{ $nextLabel }}" aria-label="{{ $nextLabel }}">
                <x-heroicon-o-chevron-right class="h-4 w-4 rtl:rotate-180"/>
            </button>
            @unless ($isCurrentMonth)
                <button type="button" wire:click="goToCurrentMonth" class="cal-nav cal-nav-today">{{ $currentLabel }}</button>
            @endunless
            <div class="cal-legend !mt-0 ms-2">
                <button type="button" class="cal-chip cursor-pointer {{ $time === 'previous' ? 'cal-chip-clear' : '' }}" wire:click="setTime(@js('previous'))" wire:key="att-time-previous" aria-pressed="{{ $time === 'previous' ? 'true' : 'false' }}">{{ $str('filter_previous') }}</button>
                <button type="button" class="cal-chip cursor-pointer {{ $time === 'upcoming' ? 'cal-chip-clear' : '' }}" wire:click="setTime(@js('upcoming'))" wire:key="att-time-upcoming" aria-pressed="{{ $time === 'upcoming' ? 'true' : 'false' }}">{{ $str('filter_upcoming') }}</button>
                <button type="button" class="cal-chip cursor-pointer {{ $unread && ! $hiddenOnly ? 'cal-chip-clear' : '' }}" wire:click="toggleUnread" wire:key="att-unread" aria-pressed="{{ $unread && ! $hiddenOnly ? 'true' : 'false' }}">{{ $str('filter_unread') }}</button>
                <button type="button" class="cal-chip cursor-pointer {{ $hiddenOnly ? 'cal-chip-clear' : '' }}" wire:click="showHidden" wire:key="att-hidden" aria-pressed="{{ $hiddenOnly ? 'true' : 'false' }}">{{ $str('filter_hidden') }}</button>
                <button type="button" class="cal-chip cursor-pointer {{ ! $unread && ! $hiddenOnly && $time === '' ? 'cal-chip-clear' : '' }}" wire:click="showAll" wire:key="att-show-all" aria-pressed="{{ ! $unread && ! $hiddenOnly && $time === '' ? 'true' : 'false' }}">{{ $str('filter_all') }}</button>
            </div>
        </div>

        @if ($paginator->isEmpty())
            <x-empty-state icon="heroicon-o-check-circle" hint="{{ $emptyHint }}" size="lg"/>
        @else
            @if (count($modules) > 1)
                <div class="cal-legend !mt-0 mb-2">
                    <button type="button" class="cal-chip cursor-pointer {{ $module === '' ? 'cal-chip-clear' : '' }}" wire:click="filterModule(@js($module))" wire:key="att-all">
                        {{ __('resources/dashboard/strings.landing_page.attention.all_modules') }}
                    </button>
                    @foreach ($modules as $mod)
                        <button type="button" class="cal-chip cursor-pointer {{ $module === $mod['module'] ? 'cal-chip-clear' : '' }}" wire:click="filterModule(@js($mod['module']))" wire:key="att-{{ $loop->index }}">
                            {{ $mod['label'] }} · {{ $mod['count'] }}
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($pageUnread)
                <div class="flex justify-end mb-1">
                    <button type="button" class="cal-chip cursor-pointer" wire:click="markPageRead" wire:key="att-mark-page">
                        <x-heroicon-o-eye-slash class="h-3.5 w-3.5"/>
                        {{ $str('mark_page_read') }}
                    </button>
                </div>
            @endif

            <ul class="divide-y" :class="darkMode ? 'divide-white/5' : 'divide-slate-100'">
                @foreach ($paginator as $row)
                    <li wire:key="att-row-{{ $paginator->currentPage() }}-{{ $loop->index }}">
                        @if ($loop->first || $paginator[$loop->index - 1]['tier'] !== $row['tier'])
                            <p class="pt-2 pb-1 text-[10px] font-bold uppercase tracking-wide opacity-60">{{ $tierLabels[$row['tier']] }}</p>
                        @endif
                        <div class="{{ $rowClass }} {{ $row['read'] ? 'opacity-60' : '' }}">
                        <a @if ($row['url']) href="{{ $row['url'] }}" target="_blank" rel="noopener noreferrer" @endif
                           class="flex min-w-0 flex-1 items-center gap-3">
                            <span class="cal-dot flex-shrink-0" style="--cal-c: var(--cal-color-{{ $row['color'] }})"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-xs font-semibold truncate" :class="{!! $textPrimary !!}">{{ $row['label'] }}</span>
                                <span class="block text-[11px] truncate" :class="{!! $textSecondary !!}">{{ $row['module'] }} · {{ $row['rule'] }}</span>
                                @if ($row['reason'] !== '')
                                    <span class="block text-[10px] leading-snug opacity-70" :class="{!! $textSecondary !!}" title="{{ $row['reason_full'] }}">{{ $row['reason'] }}</span>
                                @endif
                                @if ($row['read'])
                                    <span class="block text-[10px] leading-snug" :class="{!! $textSecondary !!}">{{ $row['seen'] }}</span>
                                @endif
                            </span>
                            <span class="text-[11px] whitespace-nowrap flex-shrink-0" :class="{!! $textSecondary !!}">{{ $row['date'] }}</span>
                            @if ($row['overdue'])
                                <span class="tb-badge tb-danger whitespace-nowrap flex-shrink-0">{{ $overdueLabel }}</span>
                            @endif
                        </a>
                        <button type="button" class="cal-nav flex-shrink-0" wire:click="{{ $row['read'] ? 'markUnread' : 'markRead' }}(@js($row['id']))"
                                title="{{ ($row['read'] ? $str('mark_unread') : $str('mark_read')).' — '.$hint }}" aria-label="{{ $row['read'] ? $str('mark_unread') : $str('mark_read') }}">
                            @if ($row['read'])
                                <x-heroicon-o-eye class="h-4 w-4"/>
                            @else
                                <x-heroicon-o-eye-slash class="h-4 w-4"/>
                            @endif
                        </button>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($paginator->hasPages())
                <x-filament::pagination :paginator="$paginator" class="mt-2"/>
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
