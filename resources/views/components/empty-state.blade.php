@props([
    'icon',
    'hint',
    'hint2' => null,
    'size' => 'md',
    'ctaLabel' => null,
    'ctaAction' => null,
])

@php
    $isLg = $size === 'lg';
@endphp

<div {{ $attributes->class([$isLg ? 'px-4 py-6 flex flex-col items-center gap-2 text-center' : 'flex flex-col items-center gap-2.5 py-6 text-center']) }}>
    <div class="{{ $isLg ? 'w-10 h-10' : 'w-9 h-9' }} rounded-md flex items-center justify-center" :class="darkMode ? 'bg-white/5 text-slate-500' : 'bg-slate-100 text-slate-400'">
        <x-dynamic-component :component="$icon" class="w-4 h-4"/>
    </div>
    <p class="{{ $isLg ? 'text-sm' : 'text-xs' }} font-semibold" :class="darkMode ? 'text-slate-300' : 'text-slate-600'">
        {{ $hint }}
    </p>
    @if ($hint2)
        <p class="text-xs" :class="darkMode ? 'text-slate-500' : 'text-slate-400'">
            {{ $hint2 }}
        </p>
    @endif
    @if ($ctaLabel)
        <button type="button" @click="{{ $ctaAction }}" class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-semibold transition-colors !cursor-pointer" :class="darkMode ? 'bg-primary-500/15 text-primary-300 hover:bg-primary-500/25' : 'bg-primary-50 text-primary-600 hover:bg-primary-100'">
            <x-heroicon-o-plus class="w-3.5 h-3.5"/>
            {{ $ctaLabel }}
        </button>
    @endif
</div>
