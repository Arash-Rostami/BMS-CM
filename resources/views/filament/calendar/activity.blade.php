@if (empty($rows))
    <div class="cal-act-empty">
        <x-heroicon-o-clock class="w-6 h-6" />
        <p>{{ $empty }}</p>
    </div>
@else
    <ol class="cal-act">
        @foreach ($rows as $row)
            <li class="cal-act-item cal-act-{{ $row['tone'] ?? 'muted' }}">
                <span class="cal-act-icon" tabindex="0" role="img" aria-label="{{ $row['hint'] ?? '' }}" @if (! empty($row['hint'])) x-data x-tooltip.raw="{{ $row['hint'] }}" @endif><x-filament::icon :icon="$row['icon'] ?? 'heroicon-m-clock'" class="w-4 h-4" /></span>
                <span class="cal-act-text">{{ $row['text'] }}</span>
                <span class="cal-act-when" dir="{{ isJalaliCalendar() ? 'rtl' : 'ltr' }}">{{ $row['when'] }}</span>
            </li>
        @endforeach
    </ol>

    @if (($paginator ?? null)?->hasPages())
        <x-filament::pagination :paginator="$paginator" class="mt-3" />
    @endif
@endif
