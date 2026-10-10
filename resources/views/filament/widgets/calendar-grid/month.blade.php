<div class="cal-grid" :class="view === 'month' ? '' : 'cal-hide'">
    @foreach($this->weekdays as $weekday)
        <div class="cal-weekday">{{ $weekday }}</div>
    @endforeach

    @for($blank = 0; $blank < $this->offset; $blank++)
        <div class="cal-day cal-day--blank" wire:key="cal-blank-{{ $blank }}"></div>
    @endfor

    @foreach($this->cells as $cell)
        <button
            type="button"
            wire:key="cal-day-{{ $cell['iso'] }}"
            wire:click="selectDate('{{ $cell['iso'] }}')"
            class="cal-day @if($cell['isToday']) cal-day--today @endif @if($cell['isSelected']) cal-day--selected @endif @if($cell['isPast']) cal-day--past @endif @if($cell['hasOverdue']) cal-day--overdue @endif"
        >
            <span class="cal-day-num">{{ $cell['day'] }}</span>

            @if($cell['hasOverdue'])
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="cal-alert" />
            @endif

            <span class="cal-dots">
                @foreach($cell['colors'] as $color)
                    <span class="cal-dot" style="--cal-c: var(--cal-color-{{ $color }})"></span>
                @endforeach
            </span>

            @if($cell['count'])
                <span class="cal-badge">+{{ $cell['count'] }}</span>
            @endif
        </button>
    @endforeach

    @for($blank = 0; $blank < $this->trailing; $blank++)
        <div class="cal-day cal-day--blank" wire:key="cal-tail-{{ $blank }}"></div>
    @endfor
</div>