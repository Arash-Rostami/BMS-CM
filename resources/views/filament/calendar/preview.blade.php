<div class="space-y-1 text-sm">
    @if (blank($preview))
        <p class="text-gray-500">{{ __('resources/calendarRule/strings.form.preview_empty') }}</p>
    @elseif (($preview['count'] ?? 0) === 0)
        <p class="font-medium">{{ __('resources/calendarRule/strings.form.preview_none') }}</p>
    @else
        <p class="font-medium">{{ __('resources/calendarRule/strings.form.preview_count', ['count' => $preview['count']]) }}</p>
        <ul class="list-disc ps-4 space-y-0.5">
            @foreach ($preview['items'] ?? [] as $item)
                <li>
                    <span>{{ $item['label'] ?? '' }}</span>
                    <span class="text-gray-500" dir="ltr"> — {{ isset($item['date']) ? adaptiveDate($item['date']) : '' }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
