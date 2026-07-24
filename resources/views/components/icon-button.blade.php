@props([
    'tooltip' => null,
])

<button
    type="button"
    @if ($tooltip)
        x-data
        x-tooltip.raw="{{ $tooltip }}"
    @endif
    {{ $attributes->class(['group relative flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 outline-none transition-all duration-200 hover:bg-gray-50 hover:text-gray-700 focus-visible:bg-gray-50 focus-visible:ring-2 focus-visible:ring-primary-500 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200 dark:focus-visible:bg-white/5']) }}
>
    {{ $slot }}
</button>
