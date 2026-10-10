<div class="cal-jump" x-data="{ pickerOpen: false }">
    <button type="button" class="cal-range-btn" @click="pickerOpen = !pickerOpen" @keydown.escape.window="pickerOpen = false">
        {{ $this->rangeLabel }}
        <x-filament::icon icon="heroicon-o-chevron-down" class="h-3.5 w-3.5" />
    </button>

    <div x-show="pickerOpen" @click.outside="pickerOpen = false" x-cloak class="cal-popover">
        <div class="cal-popover-row">
            <select wire:model.live="jumpYear" class="fi-input">
                @foreach($this->miniMonth['years'] as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>

            <select wire:model.live="jumpMonth" class="fi-input">
                @foreach($this->miniMonth['months'] as $label)
                    <option value="{{ $loop->iteration }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <button type="button" wire:click="jump" class="cal-jump-btn">
            {{ __('resources/dashboard/strings.widgets.calendar.jump') }}
        </button>
    </div>
</div>