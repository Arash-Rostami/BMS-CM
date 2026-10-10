@php
    $groupTagStyle = fn (bool $primary) => $primary
        ? 'background-image: var(--gradient-brand); color: var(--google-first-light);'
        : 'background-image: var(--gradient-neutral); color: var(--google-third-dark);';
@endphp

<div>
    <div class="lp-surface p-5 mb-6">
        <h2 class="text-base font-semibold mb-1.5" :class="darkMode ? 'text-white' : 'text-slate-900'">
            {{ $panelTitle }}
        </h2>
        <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            {{ $panelIntro }}
        </p>
    </div>

    <section class="mb-4">
        <p class="text-[11px] font-bold uppercase tracking-wider mb-1" style="color: var(--google-fourth-dark);">
            {{ $distinguishing['section_label'] }}
        </p>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
            {{ $distinguishing['section_note'] }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-10">
            @foreach ($distinguishing['groups'] as $group)
                <div class="lp-surface p-4 flex flex-col">
                    <span class="inline-block self-start text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded mb-2.5"
                          style="{{ $groupTagStyle(true) }}">
                        {{ $group['tag'] }}
                    </span>
                    <h3 class="text-sm font-semibold mb-3" :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ $group['title'] }}
                    </h3>
                    <ul class="space-y-3">
                        @foreach ($group['items'] as $item)
                            <li>
                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    {{ $item['title'] }}
                                </p>
                                <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ $item['description'] }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    <section>
        <p class="text-[11px] font-bold uppercase tracking-wider mb-1 text-slate-500 dark:text-slate-400">
            {{ $standard['section_label'] }}
        </p>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
            {{ $standard['section_note'] }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-4">
            @foreach ($standard['groups'] as $group)
                <div class="lp-surface p-4 flex flex-col">
                    <span class="inline-block self-start text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded mb-2.5 border lp-divider"
                          style="{{ $groupTagStyle(false) }}">
                        {{ $group['tag'] }}
                    </span>
                    <h3 class="text-sm font-semibold mb-3" :class="darkMode ? 'text-white' : 'text-slate-900'">
                        {{ $group['title'] }}
                    </h3>
                    <ul class="space-y-3">
                        @foreach ($group['items'] as $item)
                            <li>
                                <p class="text-xs font-semibold text-slate-700 dark:text-slate-200">
                                    {{ $item['title'] }}
                                </p>
                                <p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ $item['description'] }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>
</div>
