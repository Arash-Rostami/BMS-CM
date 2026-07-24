@php
    use Illuminate\Support\Js;
    use Illuminate\Support\Str;

    $terms = $content['terms'] ?? [];
    $process = $content['process'] ?? [];
    $dos = $content['dos'] ?? [];
    $donts = $content['donts'] ?? [];
    $covers = $content['covers'] ?? [];
    $media = $content['media'] ?? null;
    $hasPlayableMedia = $media && (! empty($media['audio']) || ! empty($media['video']));
    $defaultPanel = ! empty($media['audio']) ? 'audio' : null;
    $isRtl = app()->getLocale() === 'fa';

    $tabTextLabel = __('resources/general/strings.desk_reference.tab_text');
    $tabMediaLabel = __('resources/general/strings.desk_reference.tab_media');
    if ($hasPlayableMedia && ! empty($media['duration'])) {
        $tabMediaLabel .= ' (~' . $media['duration'] . ')';
    }
@endphp

<div
    x-data="{ tab: 'text', q: '', acked: false, openPanel: {{ Js::from($defaultPanel) }}, pick(p) { const other = p === 'audio' ? 'video' : 'audio'; if (this.openPanel === p) { this.$refs[p + 'El']?.pause(); this.openPanel = null; } else { this.$refs[other + 'El']?.pause(); this.openPanel = p; } } }"
    dir="{{ $isRtl ? 'rtl' : 'ltr' }}"
    x-init="if (!acked) {
        acked = true;
        fetch('{{ route('desk-reference.acknowledge') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
            },
            body: JSON.stringify({ group: {{ Js::from($group) }}, version: {{ Js::from($version) }} }),
        });
    }"
    class="dr-panel space-y-4"
>
    <x-filament::tabs>
        <x-filament::tabs.item icon="heroicon-o-document-text" :alpine-active="'tab === \'text\''" :x-on:click="'tab = \'text\''">
            {{ $tabTextLabel }}
        </x-filament::tabs.item>
        @if ($hasPlayableMedia)
            <x-filament::tabs.item icon="heroicon-o-film" :alpine-active="'tab === \'media\''" :x-on:click="'tab = \'media\''">
                {{ $tabMediaLabel }}
            </x-filament::tabs.item>
        @endif
    </x-filament::tabs>

    <div x-show="tab === 'text'" class="space-y-4">
        @if (!empty($covers))
            <div class="fi-section flex flex-wrap items-center gap-2 p-3">
                <x-filament::icon icon="heroicon-o-map" class="h-4 w-4 dr-label" />
                <span class="text-sm font-medium dr-label">
                    {{ $content['scope_label'] ?? '' }}
                </span>
                <span class="flex flex-wrap gap-1">
                    @foreach ($covers as $module)
                        <span class="tb-badge {{ $module === $currentModule ? 'tb-info' : '' }}">
                            {{ __("resources/{$module}/strings.general.model_label") }}
                        </span>
                    @endforeach
                </span>
            </div>
        @endif

        @if (!empty($content['tips']))
            <div class="fi-section space-y-2 p-3" style="border-left: 3px solid var(--google-fourth-light);">
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <x-filament::icon icon="heroicon-o-light-bulb" class="h-4 w-4" style="color: var(--google-fourth-light)" />
                    {{ __('resources/general/strings.desk_reference.tips_heading') }}
                </div>
                <ul class="space-y-1.5">
                    @foreach ($content['tips'] as $tip)
                        <li x-show="q === '' || {{ Js::from(Str::lower($tip)) }}.includes(q.toLowerCase())" class="flex items-start gap-2 text-sm">
                            <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full" style="background: var(--google-fourth-light)"></span>
                            <span>{{ $tip }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (!empty($terms) || !empty($process) || !empty($dos) || !empty($donts) || !empty($content['tips']))
            <input
                type="search"
                x-model="q"
                placeholder="{{ __('resources/general/strings.desk_reference.search_placeholder') }}"
                class="fi-input block w-full rounded-lg text-sm"
            />
        @endif

        @if (!empty($terms))
            <details class="fi-section p-3">
                <summary class="cursor-pointer text-sm font-semibold">
                    {{ __('resources/general/strings.desk_reference.terms_heading') }}
                </summary>
                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                    @foreach ($terms as $term)
                        @php
                            $termLabel = $term['term'] ?? '';
                            $termDefinition = $term['definition'] ?? '';
                        @endphp
                        <div
                            x-show="q === '' || {{ Js::from(Str::lower($termLabel . ' ' . $termDefinition)) }}.includes(q.toLowerCase())"
                            class="flex items-start gap-2"
                        >
                            <span class="tb-badge tb-info shrink-0">{{ $termLabel }}</span>
                            <span class="text-sm leading-relaxed">{{ $termDefinition }}</span>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        @if (!empty($process))
            <details class="fi-section p-3">
                <summary class="cursor-pointer text-sm font-semibold">
                    {{ __('resources/general/strings.desk_reference.process_heading') }}
                </summary>
                <ol class="mt-3 space-y-2">
                    @foreach ($process as $i => $step)
                        @php
                            $stepTitle = $step['title'] ?? '';
                            $stepDescription = $step['description'] ?? '';
                        @endphp
                        <li
                            x-show="q === '' || {{ Js::from(Str::lower($stepTitle . ' ' . $stepDescription)) }}.includes(q.toLowerCase())"
                            class="flex items-start gap-3"
                        >
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                style="background: var(--custom-third-light); color: var(--filament-dark);"
                            >{{ $i + 1 }}</span>
                            <span class="text-sm leading-relaxed">
                                <strong>{{ $stepTitle }}</strong>
                                <span> — {{ $stepDescription }}</span>
                                @if (!empty($step['t']))
                                    <span class="tb-badge">{{ $step['t'] }}</span>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ol>
            </details>
        @endif

        @if (!empty($dos) || !empty($donts))
            <details class="fi-section p-3">
                <summary class="cursor-pointer text-sm font-semibold">
                    {{ __('resources/general/strings.desk_reference.dos_donts_heading') }}
                </summary>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <ul class="space-y-2">
                        @foreach ($dos as $item)
                            <li
                                x-show="q === '' || {{ Js::from(Str::lower($item)) }}.includes(q.toLowerCase())"
                                class="flex items-start gap-2 text-sm"
                            >
                                <span class="tb-badge tb-success shrink-0 rounded-full !px-1.5 !py-1.5">
                                    <x-filament::icon icon="heroicon-o-check-circle" class="h-3.5 w-3.5" />
                                </span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <ul class="space-y-2">
                        @foreach ($donts as $item)
                            <li
                                x-show="q === '' || {{ Js::from(Str::lower($item)) }}.includes(q.toLowerCase())"
                                class="flex items-start gap-2 text-sm"
                            >
                                <span class="tb-badge tb-danger shrink-0 rounded-full !px-1.5 !py-1.5">
                                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-3.5 w-3.5" />
                                </span>
                                <span>{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </details>
        @endif

        @if (!empty($content['as_of']))
            <p class="text-xs dr-fine">
                {{ __('resources/general/strings.desk_reference.disclaimer', ['date' => $content['as_of']]) }}
            </p>
        @endif
    </div>

    @if ($hasPlayableMedia)
        <div x-show="tab === 'media'" x-transition.opacity.duration.200ms class="space-y-4">
            @if (!empty($media['audio']))
                <div class="fi-section p-3">
                    <button type="button" @click="pick('audio')" class="flex w-full items-center gap-2 text-sm font-medium dr-label">
                        <x-filament::icon icon="heroicon-o-musical-note" class="h-4 w-4" />
                        <span>{{ __('resources/general/strings.desk_reference.listen_prompt') }}</span>
                        <span class="ms-auto inline-flex transition-transform" :class="openPanel === 'audio' ? 'rotate-180' : ''">
                            <x-filament::icon icon="heroicon-o-chevron-down" class="h-4 w-4" />
                        </span>
                    </button>
                    <div x-show="openPanel === 'audio'" x-transition.duration.200ms class="mt-3 space-y-2">
                        @if (!empty($media['poster']))
                            <img
                                src="{{ asset('img/desk-reference/' . $media['poster']) }}"
                                alt="{{ $content['scope_label'] ?? '' }}"
                                class="dr-poster w-full rounded-lg"
                            />
                        @endif
                        <div class="dr-waveform" aria-hidden="true"></div>
                        <audio x-ref="audioEl" controls preload="none" class="w-full">
                            <source src="{{ asset('audio/desk-reference/' . $media['audio']) }}">
                        </audio>
                    </div>
                </div>
            @endif

            @if (!empty($media['video']))
                <div class="fi-section p-3">
                    <button type="button" @click="pick('video')" class="flex w-full items-center gap-2 text-sm font-medium dr-label">
                        <x-filament::icon icon="heroicon-o-film" class="h-4 w-4" />
                        <span>{{ __('resources/general/strings.desk_reference.watch_prompt') }}</span>
                        <span class="ms-auto inline-flex transition-transform" :class="openPanel === 'video' ? 'rotate-180' : ''">
                            <x-filament::icon icon="heroicon-o-chevron-down" class="h-4 w-4" />
                        </span>
                    </button>
                    <div x-show="openPanel === 'video'" x-transition.duration.200ms class="mt-3">
                        <video x-ref="videoEl" controls preload="none" class="w-full rounded-lg">
                            <source src="{{ asset('video/desk-reference/' . $media['video']) }}">
                        </video>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>