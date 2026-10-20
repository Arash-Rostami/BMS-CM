<div class="cal-jump" x-data="{ pickerOpen: false, year: {{ $this->miniMonth['currentYear'] }}, month: {{ $this->miniMonth['currentMonth'] }} }">
    <button type="button" class="cal-range-btn"
            @click="pickerOpen = !pickerOpen; year = {{ $this->miniMonth['currentYear'] }}; month = {{ $this->miniMonth['currentMonth'] }}"
            @keydown.escape.window="pickerOpen = false">
        {{ $this->rangeLabel }}
        <x-filament::icon icon="heroicon-o-chevron-down" class="h-3.5 w-3.5" />
    </button>

    <div x-show="pickerOpen" @click.outside="pickerOpen = false" x-cloak class="cal-popover">
        <div class="cal-popover-row">
            <select x-model.number="year" class="fi-input">
                @foreach($this->miniMonth['years'] as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>

            <select x-model.number="month" class="fi-input">
                @foreach($this->miniMonth['months'] as $label)
                    <option value="{{ $loop->iteration }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <button type="button" @click="$wire.jump(year, month); pickerOpen = false" class="cal-jump-btn">
            {{ __('resources/dashboard/strings.widgets.calendar.jump') }}
        </button>
    </div>
</div>
