@if($details = $this->ruleDetails)
    <div class="cal-rule">
        <div class="cal-rule-head">
            <span class="cal-dot" style="--cal-c: var(--cal-color-{{ $details['color'] }})"></span>
            <strong class="cal-rule-name">{{ $details['name'] }}</strong>
            <span class="cal-rule-type">{{ $details['type'] }}</span>
            <button type="button" class="cal-rule-close" wire:click="toggleRule({{ $ruleId }})" title="{{ __('resources/dashboard/strings.widgets.calendar.rule.clear') }}">
                <x-heroicon-m-x-mark class="w-3.5 h-3.5" />
            </button>
        </div>

        <dl class="cal-rule-grid">
            <dt>{{ __('resources/dashboard/strings.widgets.calendar.rule.date') }}</dt>
            <dd>{{ $details['date'] }}</dd>
            <dt>{{ __('resources/dashboard/strings.widgets.calendar.rule.lead') }}</dt>
            <dd>{{ $details['lead'] }}</dd>
            @if($details['conditions'])
                <dt>{{ __('resources/dashboard/strings.widgets.calendar.rule.conditions') }}</dt>
                <dd class="cal-rule-tags">
                    @foreach($details['conditions'] as $line)
                        <span>{{ $line }}</span>
                    @endforeach
                    @if($details['conditions_more'])
                        <span>+{{ $details['conditions_more'] }}</span>
                    @endif
                </dd>
            @endif
            <dt>{{ __('resources/dashboard/strings.widgets.calendar.rule.changes') }}</dt>
            <dd>
                @forelse($details['changes'] as $change)
                    <div class="cal-rule-change">{{ $change['text'] }} <span>{{ $change['when'] }}</span></div>
                @empty
                    <span class="cal-rule-muted">{{ __('resources/dashboard/strings.widgets.calendar.rule.no_changes') }}</span>
                @endforelse
            </dd>
        </dl>
    </div>
@endif
