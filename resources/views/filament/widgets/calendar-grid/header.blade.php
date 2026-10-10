<div class="cal-toolbar">
    <div class="cal-toolbar-start">
        @include('filament.widgets.calendar-grid.mini-month')

        <button type="button" wire:click="prevMonth" class="cal-nav" title="{{ __('resources/dashboard/strings.widgets.calendar.previous') }}">
            <x-filament::icon icon="heroicon-o-chevron-left" class="h-4 w-4" @class(['cal-rtl-flip' => app()->isLocale('fa')]) />
        </button>

        <button type="button" wire:click="goToToday" class="cal-nav cal-nav-today">
            {{ __('resources/dashboard/strings.widgets.calendar.today') }}
        </button>

        <button type="button" wire:click="nextMonth" class="cal-nav" title="{{ __('resources/dashboard/strings.widgets.calendar.next') }}">
            <x-filament::icon icon="heroicon-o-chevron-right" class="h-4 w-4" @class(['cal-rtl-flip' => app()->isLocale('fa')]) />
        </button>
    </div>

    <div class="cal-toolbar-end">
        <select wire:model.live="ruleId" class="fi-input">
            <option value="">{{ __('resources/dashboard/strings.widgets.calendar.filters.all_rules') }}</option>
            @foreach($this->ruleOptions as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </select>

        <select wire:model.live="module" class="fi-input">
            <option value="">{{ __('resources/dashboard/strings.widgets.calendar.filters.all_modules') }}</option>
            @foreach($this->moduleOptions as $fqcn => $label)
                <option value="{{ $fqcn }}">{{ $label }}</option>
            @endforeach
        </select>

        <div class="cal-toggle hidden md:inline-flex">
            <button type="button" @click="view = 'month'" :class="view === 'month' ? 'cal-toggle-active' : ''">
                {{ __('resources/dashboard/strings.widgets.calendar.month') }}
            </button>
            <button type="button" @click="view = 'agenda'" :class="view === 'agenda' ? 'cal-toggle-active' : ''">
                {{ __('resources/dashboard/strings.widgets.calendar.agenda') }}
            </button>
        </div>

        {{ $this->calendarActivityAction }}
    </div>
</div>