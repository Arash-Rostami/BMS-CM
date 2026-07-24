# Desk Reference — Phase 2: Landing Page "Insights" Tab

**Status: implementation-ready.** Grounded against the real landing-page files (`landing-page.blade.php`, `header.blade.php`, `landing-page-alpine.js`, `tri-widget-alpine.js`, `LandingPage.php`) and against Phase 1's shipped code. Two Fable 5 passes shaped this: the first proposed folding posters into the existing workflow stepper and dropping the rotating tip card; the founder explicitly overrode that — the "Did you know" rotation is kept, non-negotiable — so this plan reconciles both (a richer version of the rotation, not a replacement).

**Updated after a Phase 1 relocation:** Desk Reference moved from an infolist tab (buried inside a record's Details view — rejected by the founder as "worst place possible") to a **header Action button on each module's List page, opening a two-tab modal** (text + media). Trait renamed `HasDeskReferenceTab` → `HasDeskReferenceAction`; `panel.blade.php` rewritten as a modal. This plan has **zero code dependency** on that trait, the Action, or `panel.blade.php` — it reads the same `deskReference/{group}` lang content files directly via `Lang::has()`/`trans()`, independent of whatever renders them — so nothing here breaks. Only the *wording* below changed (see §1 Zone B) to reflect where the reference actually lives now.

## Context

Phase 1 shipped Desk Reference — glossary/process/do's-and-don'ts/media per stage — reachable via a header button + modal on each of the 8 operational resources' List pages (see the update note above; originally an infolist tab, since relocated). That's done, live, and unaffected by this plan. This phase adds a **4th landing-page tab** surfacing the same content at the discovery layer: a rotating "Did you know?" hero using the real poster+audio assets already recorded, plus a static grid giving deterministic access to all 4 stages. Nothing here duplicates or replaces the List-page action/modal — it's a discovery layer pointing at it.

## Architecture

### 1. Vehicle: a 4th tab, "Insights"

Confirmed cheap: `activeTab` (in `landing-page-alpine.js`) is a free-form string with zero validation, persisted to `localStorage['lp_tab']` — adding a new value needs no state-machine change. `header.blade.php`'s tab bar already has `overflow-x-auto`, so a 4th button is visually safe. Landing-page.blade.php already has the exact `x-show="activeTab === '...'"` pattern for its 3 existing panels — the 4th is a straight copy of that pattern.

Two-zone layout inside the new tab:
- **Zone A — "Did you know?" hero** (top): one featured card. Framed poster thumbnail (fixed width, `rounded-xl border`, NOT full-bleed — matches the flat/bordered design language, no glassmorphism), the current tip's text, a stage-colored badge (reusing the exact same accent map `workflow.blade.php` already defines), a "Next tip" shuffle button, and an inline mini play/pause button for that stage's narration.
- **Zone B — static stage grid** (below, always visible): 4 compact cards, one per stage, same accent colors as the hero and as the Workflow tab — poster thumbnail, stage name, mini play button, and a link to that stage's primary module **List page** — matching exactly how `workflow.blade.php`'s own links already work. This is now a stronger link than originally designed: since the relocation, that List page is exactly where the real "Desk Reference" header button + modal lives (next to Create), so the click lands the user directly next to the full reference, not just "somewhere in the module, go find it."

### 2. Content — `tips` key, authored for the first time

`tips` does not exist anywhere yet (confirmed — zero references in Phase 1's shipped code or lang files). Add to the existing 4 `lang/fa/deskReference/{group}.php` files (schema was always optional-and-ready for this, per Phase 1's own content model):
```php
'tips' => [
    'یک نکته کوتاه و کاربردی درباره این مرحله.',
],
```
One tip per group is enough for v1 (four total, one per stage) — the array shape stays open for adding more later without any code change. English tips are out of scope for now, same fa+en split precedent as Phase 1 — actually here, since there's no English narration/poster asset distinction blocking it, author English tips too (cheap, text-only): add the same key to the 4 `lang/en/deskReference/*.php` files.

### 3. Data flow — one new private method on `LandingPage.php`

Not `DashboardStats` (that's model-count-shaped, wrong fit — confirmed by reading it) and not a new Support/Service class (four groups, one loop — a dedicated class would be the premature abstraction this project's own coding philosophy rejects). A small private method on the page class itself, mirroring `HasDeskReferenceAction`'s own existence-check pattern (`Lang::has` then a real emptiness check) but enumerating all groups instead of resolving one resource:

```php
protected function getInsightGroups(): array
{
    $accents = ['request_approval' => 'blue', 'order_processing' => 'green', 'procurement_payment' => 'yellow', 'logistics' => 'red'];
    $routes = [
        'request_approval' => 'filament.dashboard.resources.purchase-requests.index',
        'order_processing' => 'filament.dashboard.resources.registered-orders.index',
        'procurement_payment' => 'filament.dashboard.resources.purchase-orders.index',
        'logistics' => 'filament.dashboard.resources.shipments.index',
    ];

    $groups = [];

    foreach (array_keys($accents) as $group) {
        if (! Lang::has("deskReference/{$group}")) {
            continue;
        }

        $content = trans("deskReference/{$group}");

        if (empty($content['tips'])) {
            continue;
        }

        $groups[] = [
            'key' => $group,
            'title' => $content['tab_label'] ?? $group,
            'accent' => $accents[$group],
            'tips' => $content['tips'],
            'poster' => ! empty($content['media']['poster']) ? asset('img/desk-reference/' . $content['media']['poster']) : null,
            'audio' => ! empty($content['media']['audio']) ? asset('audio/desk-reference/' . $content['media']['audio']) : null,
            'route' => route($routes[$group]),
        ];
    }

    return $groups;
}
```
Same two-step gate as Phase 1's trait (`Lang::has` before array access, then a real emptiness check) — this time gated on `tips` specifically, not the terms/process/dos/donts bundle, since this tab's job is the tip rotation, not the full reference. A group with full Desk Reference content but no `tips` authored yet simply doesn't appear in Zone A/B — silent-until-authored, same promise as Phase 1.

`getViewData()` becomes:
```php
protected function getViewData(): array
{
    return array_merge(parent::getViewData(), [
        'counts' => $this->counts,
        'insightGroups' => $this->getInsightGroups(),
    ]);
}
```
(add `use Illuminate\Support\Facades\Lang;` to the file's imports.)

### 4. Blade partial

`resources/views/components/filament/landing-page/insights.blade.php`:
```blade
@php
    $accentMap = [
        'blue'   => ['border' => 'border-blue-200 dark:border-blue-500/30', 'text' => 'text-blue-700 dark:text-blue-400', 'bg' => 'bg-blue-50 dark:bg-blue-500/10'],
        'green'  => ['border' => 'border-green-200 dark:border-green-500/30', 'text' => 'text-green-700 dark:text-green-400', 'bg' => 'bg-green-50 dark:bg-green-500/10'],
        'yellow' => ['border' => 'border-yellow-200 dark:border-yellow-500/30', 'text' => 'text-yellow-800 dark:text-yellow-400', 'bg' => 'bg-yellow-50 dark:bg-yellow-500/10'],
        'red'    => ['border' => 'border-red-200 dark:border-red-500/30', 'text' => 'text-red-700 dark:text-red-400', 'bg' => 'bg-red-50 dark:bg-red-500/10'],
    ];
@endphp

@if (!empty($insightGroups))
    <div x-data="insights(@js($insightGroups))" class="space-y-6">
        {{-- Zone A: rotating hero --}}
        <div class="lp-surface rounded-lg p-5 flex flex-col sm:flex-row gap-4"
             :class="accentMap[current.accent]?.border">
            <template x-if="current.poster">
                <img :src="current.poster" loading="lazy" decoding="async"
                     class="w-full sm:w-48 h-32 object-cover rounded-xl border lp-divider flex-shrink-0" alt="">
            </template>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs font-bold px-2 py-0.5 rounded" :class="[accentClasses(current.accent, 'bg'), accentClasses(current.accent, 'text')]" x-text="current.title"></span>
                </div>
                <p class="text-sm leading-relaxed mb-3" :class="darkMode ? 'text-slate-200' : 'text-slate-700'" x-text="current.tip"></p>
                <div class="flex items-center gap-2">
                    <button @click="playPause(current)" x-show="current.audio"
                            class="lp-tab flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-md border lp-divider">
                        <x-heroicon-o-play class="w-3.5 h-3.5" x-show="!playing"/>
                        <x-heroicon-o-pause class="w-3.5 h-3.5" x-show="playing" x-cloak/>
                        <span x-text="playing ? '{{ __('dashboard/strings.insights_pause') }}' : '{{ __('dashboard/strings.insights_listen') }}'"></span>
                    </button>
                    <button @click="shuffle()" class="lp-tab-active flex items-center gap-1.5 px-3 py-1.5 text-xs rounded-md">
                        <x-heroicon-o-arrow-path class="w-3.5 h-3.5"/>
                        <span>{{ __('dashboard/strings.insights_next_tip') }}</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- Zone B: static stage grid --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($insightGroups as $group)
                <a href="{{ $group['route'] }}" target="_blank" rel="noopener noreferrer"
                   class="lp-surface lp-surface-hover rounded-lg overflow-hidden border {{ $accentMap[$group['accent']]['border'] }} flex flex-col">
                    @if ($group['poster'])
                        <img src="{{ $group['poster'] }}" loading="lazy" decoding="async" class="w-full h-24 object-cover" alt="">
                    @endif
                    <div class="p-3">
                        <span class="text-xs font-semibold {{ $accentMap[$group['accent']]['text'] }}">{{ $group['title'] }}</span>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
@endif
```

### 5. Alpine — `insights()` component + audio coordination

`resources/js/insights-alpine.js`:
```js
export default function insights(groups) {
    return {
        groups,
        idx: 0,
        playing: false,
        audio: null,
        accentMap: {
            blue:   { bg: 'bg-blue-50 dark:bg-blue-500/10', text: 'text-blue-700 dark:text-blue-400' },
            green:  { bg: 'bg-green-50 dark:bg-green-500/10', text: 'text-green-700 dark:text-green-400' },
            yellow: { bg: 'bg-yellow-50 dark:bg-yellow-500/10', text: 'text-yellow-800 dark:text-yellow-400' },
            red:    { bg: 'bg-red-50 dark:bg-red-500/10', text: 'text-red-700 dark:text-red-400' },
        },
        init() {
            window.addEventListener('lp-audio-play', (e) => {
                if (e.detail.source !== 'insights' && this.playing) this.stopAudio();
            });
        },
        get current() {
            const g = this.groups[this.idx] || {};
            return { ...g, tip: (g.tips && g.tips[0]) || '' };
        },
        accentClasses(accent, kind) {
            return this.accentMap[accent]?.[kind] || '';
        },
        shuffle() {
            this.stopAudio();
            this.idx = (this.idx + 1) % this.groups.length;
        },
        playPause(group) {
            if (this.playing) {
                this.stopAudio();
                return;
            }
            window.dispatchEvent(new CustomEvent('lp-audio-play', { detail: { source: 'insights' } }));
            this.audio = new Audio(group.audio);
            this.audio.play().then(() => this.playing = true).catch(() => {});
            this.audio.onended = () => this.playing = false;
        },
        stopAudio() {
            if (this.audio) { this.audio.pause(); this.audio = null; }
            this.playing = false;
        },
    };
}
```
Registered lazily in `alpine-loader.js`, matching the existing `landingPage`/`triWidget` DOM-presence-gated pattern:
```js
import insights from './insights-alpine.js';
// ...
if (document.querySelector('[x-data^="insights("]')) Alpine.data('insights', insights);
```
(`^=` attribute-prefix selector since the real markup is `x-data="insights(@js($insightGroups))"`, not the literal string `insights()` — the existing `landingPage()`/`triWidget()` checks use exact-match because those really are called with no arguments; this one takes a JSON payload, so the guard must match the prefix instead.)

**Audio coordination**, the other half: `tri-widget-alpine.js`'s `playPause()`, `next()`, and `prev()` each need one line added — dispatch the same event before playing, and listen for it in `init()`:
```js
// inside init():
window.addEventListener('lp-audio-play', (e) => {
    if (e.detail.source !== 'widget' && this.music.playing) {
        this.music.audio?.pause();
        this.music.playing = false;
    }
});

// inside playPause()/next()/prev(), immediately before .play():
window.dispatchEvent(new CustomEvent('lp-audio-play', { detail: { source: 'widget' } }));
```
Two independent players, one shared event, no singleton/library — exactly Fable's recommendation, minimal surface.

### 6. Wiring — 3 small edits to existing files

**`header.blade.php`** — add a 4th button after Search, same pattern as the other three:
```blade
<button @click="activeTab = 'insights'"
        class="lp-tab flex items-center gap-2 px-3 sm:px-4 py-2.5 text-sm whitespace-nowrap"
        :class="activeTab === 'insights' ? 'lp-tab-active' : ''">
    <x-heroicon-o-light-bulb class="w-4 h-4 flex-shrink-0"/>
    <span>{{ __('dashboard/strings.insights') ?? 'Insights' }}</span>
</button>
```

**`landing-page.blade.php`** — add a 4th `x-show` panel, copying the exact transition classes already used by the other three, after the Search panel:
```blade
<div x-show="activeTab === 'insights'"
     x-transition:enter="transition-opacity duration-150 ease-in"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity duration-100 ease-out"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     x-cloak>
    @include('components.filament.landing-page.insights')
</div>
```

**`lang/{en,fa}/dashboard/strings.php`** — add 4 new keys (same file the other 3 tab labels already live in): `insights`, `insights_listen`, `insights_pause`, `insights_next_tip`.

### 7. Icon & color audit

Every color in this plan reuses the **exact same accent map** `workflow.blade.php` already defines (`blue`/`green`/`yellow`/`red`, same Tailwind classes, same border/text/bg triplets) — no new colors invented, and the mapping is 1:1 with the same 4 stage keys Phase 1's config already uses. `heroicon-o-light-bulb` (tab icon) was the icon Fable proposed back in Phase 1 planning for exactly this "did you know" concept and was never used elsewhere. `heroicon-o-play`/`heroicon-o-pause`/`heroicon-o-arrow-path` are plain, unclaimed, standard-meaning icons. `.lp-surface`/`.lp-surface-hover`/`.lp-divider`/`.lp-tab`/`.lp-tab-active` are all pre-existing landing-page tokens — zero new CSS needed.

## Dry Run Trace Log

- *No group has `tips` authored yet* (before this plan's content step runs): `getInsightGroups()` returns `[]` → the whole `@if (!empty($insightGroups))` block doesn't render → the Insights tab still shows in the header (it's not content-gated at the tab-button level) but its panel is empty. **Decision needed, not yet resolved**: should the tab itself hide when there's zero content, matching Phase 1's per-resource "silent until authored" promise? Given this plan assumes `tips` gets authored as part of implementing it, this is a low-probability transient state, but the header button should arguably be wrapped in the same `!empty($insightGroups)` check for consistency — flagged in the checklist below, not fixed silently.
- *A group has `tips` but no `media.poster`/`media.audio`*: `'poster' => null`, `'audio' => null` — the hero's `x-if="current.poster"` skips the image cleanly, and the play button's `x-show="current.audio"` hides it. Clean, no broken `<img src="">`.
- *Rapid repeated "Next tip" clicks while audio is playing*: `shuffle()` calls `stopAudio()` first, so switching stages always kills any in-flight narration rather than layering multiple `Audio()` instances. Clean.
- *User plays Insights narration, then opens the floating widget and plays music*: the `lp-audio-play` event fires from the widget's `playPause()`, Insights' listener sees `source !== 'insights'` and stops itself. Symmetric in the other direction. Clean, verified both directions are wired (not just one).
- *RTL (fa locale)*: all new markup uses Tailwind logical/flex utilities already used elsewhere on this page (`flex`, `gap`, no manual `ml-`/`mr-`) — same convention as the rest of the landing page, no separate RTL branch needed.

## Files to create / touch

**New:**
```
resources/views/components/filament/landing-page/insights.blade.php
resources/js/insights-alpine.js
```

**Modified:**
```
app/Filament/Pages/LandingPage.php                                  + getInsightGroups() + insightGroups in getViewData()
resources/views/components/filament/landing-page/header.blade.php   + 4th tab button
resources/views/components/filament/landing-page.blade.php          + 4th x-show panel
resources/js/alpine-loader.js                                       + insights registration
resources/js/tri-widget-alpine.js                                   + lp-audio-play dispatch/listener (4 small edits)
lang/en/dashboard/strings.php, lang/fa/dashboard/strings.php        + 4 new keys each
lang/fa/deskReference/*.php (4 files), lang/en/deskReference/*.php (4 files)   + tips key
```

## Pre-flight Checklist

- [ ] Resolve the "empty insightGroups" question from the dry run — either gate the header button on the same check, or accept an empty-but-visible tab as fine for v1 (decide before shipping, don't leave it accidental).
- [ ] Confirm `alpine-loader.js`'s attribute-prefix selector (`[x-data^="insights("]`) actually matches — this is the one Alpine wiring detail that differs from the existing exact-match precedent, worth a real browser check that the component registers.
- [ ] Confirm both directions of audio coordination live-test: play Insights → open widget → play music → Insights stops; and the reverse.
- [ ] Confirm `route($routes[$group])` resolves correctly for all 4 groups (the 4 hardcoded route names match real registered route names — verify against `php artisan route:list` or the existing `workflow.blade.php` links, which already use the identical route names).
- [ ] `npm run build` required (new JS file).
- [ ] Verify poster/audio URLs resolve correctly via `asset('img/desk-reference/...')` — same helper Phase 1's embedded panel already uses successfully, low risk but confirm visually.
- [ ] **Post-relocation sanity check**: confirm the `media` key actually present in the 8 `lang/{fa,en}/deskReference/*.php` files (wired as part of the List-page relocation work) uses the same sub-keys this plan expects — `poster`/`audio`/`video`/`duration`. Confirmed matching at plan-write time (both plans independently arrived at identical key names); re-check if either plan's content schema changes before this one is implemented.
