@php
    $isRtl = app()->getLocale() === 'fa';
@endphp

<div dir="{{ $isRtl ? 'rtl' : 'ltr' }}" class="space-y-3">
    <div class="space-y-2">
        @foreach ($pipeline['ordered'] as $stage)
            <div class="flex items-center gap-3">
                <span dir="ltr" class="flex shrink-0 items-center gap-1 text-sm font-semibold">
                    <span>{{ $stage['icon'] }}</span>
                    <span>{{ $stage['order'] }}.</span>
                </span>
                <span class="text-sm">
                    <strong>{{ $stage['name'] }}</strong>
                    <span style="color: var(--custom-fourth)"> — {{ $stage['responsible'] }}</span>
                </span>
            </div>
        @endforeach
    </div>

    @if (! empty($pipeline['unordered']))
        <hr style="border-color: var(--custom-third-light);" />

        <div class="space-y-2">
            @foreach ($pipeline['unordered'] as $stage)
                <div class="flex items-center gap-3">
                    <span dir="ltr" class="shrink-0 text-sm">{{ $stage['icon'] }}</span>
                    <span class="text-sm">
                        <strong>{{ $stage['name'] }}</strong>
                        <span style="color: var(--custom-fourth)"> — {{ $stage['available_text'] }}</span>
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
