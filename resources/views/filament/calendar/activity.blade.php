@if (empty($rows))
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</p>
@else
    <ul class="divide-y divide-gray-200 dark:divide-white/10">
        @foreach ($rows as $row)
            <li class="flex flex-wrap items-baseline gap-x-3 gap-y-1 py-1.5">
                <span class="text-xs whitespace-nowrap text-gray-500 dark:text-gray-400" dir="ltr">{{ $row['when'] }}</span>
                <span class="text-sm">{{ $row['text'] }}</span>
            </li>
        @endforeach
    </ul>
@endif