@if(count($this->legend))
    <div class="cal-legend">
        <span class="cal-legend-title">{{ __('resources/dashboard/strings.widgets.calendar.legend') }}</span>

        @if($this->ruleId)
            <button type="button" class="cal-chip cal-chip-clear cursor-pointer" wire:key="cal-legend-clear"
                    wire:click="toggleRule({{ (int) $this->ruleId }})">
                <x-heroicon-m-x-mark class="w-3 h-3" />
                {{ __('resources/dashboard/strings.widgets.calendar.rule.clear') }}
            </button>
        @endif

        @foreach($this->legend as $rule)
            @php($color = 'var(--cal-color-'.($rule->color?->value ?? 'slate').')')
            <button type="button" class="cal-chip cursor-pointer" wire:key="cal-legend-{{ $rule->id }}"
                    wire:click="toggleRule({{ $rule->id }})"
                    style="--cal-c: {{ $color }}{{ $this->ruleId === $rule->id ? '; background: color-mix(in srgb, '.$color.' 30%, transparent)' : '' }}">
                <span class="cal-dot"></span>
                {{ $rule->name }}
            </button>
        @endforeach
    </div>

    @if($this->legend->hasPages())
        <x-filament::pagination :paginator="$this->legend" class="mt-2" />
    @endif
@endif
