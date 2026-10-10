<div class="cal-agenda" :class="view === 'agenda' ? '' : 'cal-hide'">
    @forelse($this->agenda as $day)
        <div class="cal-agenda-day" wire:key="cal-agenda-{{ $day['iso'] }}">
            <button type="button" class="cal-agenda-date" wire:click="selectDate('{{ $day['iso'] }}')">
                {{ $day['label'] }}
            </button>

            <ul class="cal-agenda-list">
                @foreach($day['hits'] as $hit)
                    <li class="cal-agenda-item" wire:key="cal-agenda-hit-{{ $hit['id'] }}">
                        <span class="cal-dot" style="--cal-c: var(--cal-color-{{ $hit['color'] ?? 'slate' }})"></span>

                        @if($hit['url'])
                            <a href="{{ $hit['url'] }}" class="cal-agenda-label hover:underline">{{ $hit['label'] }}</a>
                        @else
                            <span class="cal-agenda-label">{{ $hit['label'] }}</span>
                        @endif

                        <span class="cal-agenda-rule">{{ $hit['rule'] }}</span>

                        @if($hit['overdue'])
                            <span class="tb-badge tb-danger">{{ __('resources/dashboard/strings.widgets.calendar.overdue') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="cal-agenda-empty">{{ __('resources/dashboard/strings.widgets.calendar.empty') }}</p>
    @endforelse

    @if($this->agendaCapped)
        <p class="cal-agenda-empty">{{ __('resources/dashboard/strings.widgets.calendar.agenda_capped', ['count' => \App\Services\Calendar\Display\CalendarBoard::AGENDA_LIMIT]) }}</p>
    @endif
</div>