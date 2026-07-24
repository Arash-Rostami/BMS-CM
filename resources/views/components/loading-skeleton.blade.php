@props([
    'muted' => false,
])

<div {{ $attributes->merge(['class' => $muted ? 'bg-slate-100 dark:bg-white/5' : 'bg-slate-200 dark:bg-white/10']) }}></div>
