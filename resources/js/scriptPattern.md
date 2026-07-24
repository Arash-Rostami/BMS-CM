# BMS-CM JavaScript / Alpine.js Pattern

Verified against source on branch `master` (2026-07-25).

## Purpose

BMS-CM's browser interactivity is a small, hand-rolled Alpine.js layer: five pure-function factories (`landingPage`, `triWidget`, `search`, `workspace`, `workflow`) wired through a single `alpine/loader.js` hub, plus two standalone panel-wide scripts (`filament/nav-dock.js`, `filament/topbar-autohide.js`) that use `Alpine.store` directly. No build-time framework, no PWA/service worker, no Three.js, no global Proxy hub.

## Core idea

**Lazy, DOM-gated registration + pure factories + lazy `Audio` + localStorage persistence.** Every Alpine component is `function Name()` returning a plain object — never `class`, never `new`, never `Alpine.store()` inside a landing-page factory. `alpine/loader.js` is the only place `document.querySelector` is permitted: it gates each factory's registration on the presence of its root element, so unused factories cost zero registration. Heavy objects (`Audio`) are created on first use, not in `init()`. Cross-session state survives in `localStorage`; in-session cross-component state survives via `window` `CustomEvent`s.

BMS-CM deliberately avoids a Proxy-hub/store-based architecture (centralized `Alpine.store()`, PWA caching, shared mixins): each landing-page component owns its local state and persists only what a user would notice losing (theme, active tab, pinned shortcuts). The two panel-wide scripts are the one exception — they use `Alpine.store()` because they need to react across full Livewire SPA navigations, not just within one component tree (see §7).

## Directory & entry pipeline

```
resources/js/
├── app.js                                  ← Vite entry for the landing page (imports bootstrap + alpine/loader.js)
├── alpine/
│   ├── loader.js                           ← registration hub — stays at the alpine/ root, not nested
│   └── components/
│       ├── landing-page.js                 ← landingPage()
│       ├── tri-widget.js                   ← triWidget()
│       ├── search.js                       ← search()
│       ├── workspace.js                    ← workspace(config)
│       └── workflow.js                     ← workflow(groups)
└── filament/
    ├── nav-dock.js                         ← separate Vite entry, Filament-panel-wide (NOT an alpine/ factory)
    └── topbar-autohide.js                  ← same category as nav-dock.js

vite.config.js
└── input: resources/js/app.js, resources/js/filament/{nav-dock,topbar-autohide}.js
    └── viteStaticCopy targets: resources/img, resources/audio, resources/video, resources/fonts
```

**Why `filament/*.js` isn't in `alpine/`:** organized by *where it runs* (inside the Filament panel itself, loaded via `FilamentAssets.php`'s `Js::make()`) not *what it is*. The `alpine/` folder is organized by the other axis — every file in it is a pure-function factory registered through `alpine/loader.js`, used only within the landing page's own Alpine scope. Mirrors the `resources/views/filament/` vs `resources/views/livewire/` split (see `resources/views/viewsPattern.md`).

**Registration flow:** Vite bundles `app.js` + the five factory modules into one asset → `app.js` imports `./alpine/loader.js` → the loader listens for `alpine:init` and registers each factory with `Alpine.data(name, factory)`, gating `landingPage`/`triWidget`/`workflow` on DOM presence → `Alpine.start()` runs once, guarded by `window.__alpine_running`.

## 1. The registration hub — `resources/js/alpine/loader.js`

```js
document.addEventListener('alpine:init', () => {
    if (document.querySelector('[x-data="landingPage()"]')) Alpine.data('landingPage', landingPage);
    if (document.querySelector('[x-data="triWidget()"]')) Alpine.data('triWidget', triWidget);
    if (document.querySelector('[x-data^="workflow("]')) Alpine.data('workflow', workflow);

    Alpine.data('search', search);
    Alpine.data('workspace', workspace);
});

if (!window.__alpine_running) {
    Alpine.start();
    window.__alpine_running = true;
}
```

- `landingPage`/`triWidget` use an **exact-match** guard (`[x-data="Name()"]`) — they take no args, so the real attribute is the literal string `Name()`.
- `workflow` uses a **prefix-match** guard (`[x-data^="workflow("]`) because it takes a JSON payload argument, so the real attribute is `workflow({...})`, never the literal `workflow()`. Use this form for any future factory that takes an argument — an exact-match guard on an argument-taking factory silently never matches.
- `search`/`workspace` are always registered (used unconditionally in the landing-page shell).
- `document.querySelector` appears **nowhere else** in any Alpine data function — only in this hub.
- `window.__alpine_running` is checked **before** `Alpine.start()` and set **after** — prevents double-init if the bundle is evaluated twice.

## 2. Factory signature rule (all five factories)

```js
export default function Name(args) {
    return { /* plain object */ };
}
```

No `class`, no `new`, no `Alpine.store(...)`. Configuration enters via function arguments (`workspace` and `workflow` each take one); state leaves via `localStorage`, `window` events, and the DOM.

## 3. `landingPage()` — `alpine/components/landing-page.js`

```js
darkMode: false, activeTab: 'workflow', widgetOpen: false, widgetMinimized: false,
init() {
    // reads 'theme' (truthy set: '1'/'true'/'dark'/'on'), 'lp_tab', 'lp_widget_min', 'lp_widget_open'
    // toggles html.dark, listens for 'dark-mode-toggled', then $watch()es all four back to localStorage
}
```

- `darkMode` default `false`; `activeTab` default `'workflow'` (only overridden when `lp_tab` is non-null).
- `widgetOpen`/`widgetMinimized` together gate the floating `triWidget` panel: `widgetOpen && !widgetMinimized` shows the full panel, `widgetOpen && widgetMinimized` shows a small opposite-corner dock button. Both persist independently (`lp_widget_open`/`lp_widget_min`) — they used to disagree after a reload (widget appeared to "randomly vanish") before `widgetOpen` persistence was added. Minimizing never touches `triWidget`'s clock/timer/music state, only visibility (`x-show`, not teardown).
- Both `widget.blade.php` wrappers carry `x-cloak` — prevents a hydration-frame flash of the wrong panel before Alpine evaluates `x-show`.
- `localStorage` reads/writes are wrapped in module-level `getItem`/`setItem` helpers that swallow quota/privacy-mode failures. Replicate this for any new key.
- The Three.js torus background is gone — do not reintroduce.

## 4. `triWidget()` — `alpine/components/tri-widget.js`

Three tabs: Clock / Timer / Music. State: `tab: 'clock'`, `clockString`/`dateString`/`shamsiDateString`, `timer: {running, seconds: 300}`, `customMins`, `alarm: 'alarm.mp3'`, `alarmInterval`, `alarmAudioInstance`, `music: {tracks: [...4 local...], idx, audio, playing, position, duration, progress, volume: 0.8}`.

**Clock:** `_tick()` runs on its own `setInterval` (`_clockTimerId`), sets `clockString`/`dateString` via `toLocaleTimeString()`/`toLocaleDateString()`, and `shamsiDateString` via `toLocaleDateString('fa-IR', {..., numberingSystem: 'latn'})`. No `calendar: 'persian'` key — `fa-IR` resolves to the Persian calendar implicitly; `numberingSystem: 'latn'` forces Western digits.

**Timer:** `_countdownTick()` runs on a separate `setInterval` (`_countdownTimerId`); at 0 it stops and calls `startAlarmLoop()`. Presets are exactly 300/600/900/1800/3600 seconds (5/10/15/30/60 min) via `setTimerPreset(seconds)`.

**Alarm (lazy `Audio`):**
```js
startAlarmLoop() { this.stopAlarm(); /* new Audio, loop=true, play(), setInterval(stopAlarm, 60000) */ }
stopAlarm() { /* clearInterval + null the interval handle; pause + reset + null the Audio instance */ }
```
Created only in `startAlarmLoop()`, never in `init()`. Auto-stop is a **self-clearing `setInterval`** (not `setTimeout`) — `stopAlarm()` guards on `if (this.alarmInterval)` before clearing, so it fires exactly once even if called again. Keep this shape.

**Music (lazy `Audio`, reused across tracks):**
- `loadCurrentTrack()` creates the single `Audio` instance on first call (`preload='none'`, volume seeded from `music.volume`), wires `onloadedmetadata`/`ontimeupdate`/`onended`. The final `src` assignment is guarded (`!audio.src || !audio.src.includes(currentTrack.src)`) so re-selecting the same track doesn't restart it.
- `next()`/`prev()` call a shared `_switchTrack(idx)` which updates `idx`, persists, and swaps only `audio.src` — never recreates the `Audio` object.
- `playPause()`/`_switchTrack()` route through `_broadcastAndPlay()`, which dispatches `lp-audio-play` (`{source: 'widget'}`) **before** calling `.play()` — see §7.
- `init()` registers a `lp-audio-play` listener (`_onExternalPlay`) that pauses `music` if another player (`workflow`) starts; `destroy()` tears down both intervals and this listener.
- Four local tracks (`LoFi`/`Vocale`/`Pomodoro`/`Electronic`) served from `/audio/music/*` (Vite static-copies `resources/audio/*`). Cover art (`/img/widget/*.png`) is lazy-bound (`:src="music.playing ? currentTrack.image : ''"`) so it's only fetched during playback.
- `idx`/`volume` persist to `localStorage['lp_music']` (JSON), restored via `_loadMusicPrefs()` in `init()`. Browsers block autoplay after reload — only the *selection* is restored, not playback.
- The widget mounts once on the landing-page root (outside the tab panels), so closing/switching tabs never destroys the `Audio` — only `x-show` hides it. The panel won't `@click.away`-close while `music.playing`; the explicit X button (`stopMusic()`) is the only way to close-and-stop.

## 5. `workspace(config)` — `alpine/components/workspace.js`

Spread into the Blade's outer Alpine scope: `x-data="{ ...workspace({{ json_encode($workspaceConfig) }}), showModulePicker: false, showRecordPicker: false }"`.

**Storage:** `localStorage['user_shortcuts']` = `{ modules: [...ids], records: [...pins] }`, pin shape `{key, resourceId, recordId, label, subtitle, url}`. `readStorage()` migrates the legacy bare-array shape (`[...]` → `{modules: parsed.map(id), records: []}`) — keep this migration, old users still have it.

**Record search (request-ID race guard):**
```js
async searchRecords() {
    if (!this.pickerResource) { this.recordResults = []; return; }
    const reqId = ++this.recordReqId;
    // fetch with Accept:application/json, credentials:same-origin, throws on !r.ok
    // every branch re-checks `reqId === this.recordReqId` before mutating state
}
```
Any new call (including the clear-search button in `workspace.blade.php`) bumps `recordReqId`, invalidating in-flight responses. Never bypass this guard with a fire-and-forget fetch.

**`decorateRecord(p)`** merges a pin with its parent module's `icon`/`theme` (fallback `from-slate-500 to-slate-600`).

**`initials(value)`** — empty/whitespace-only input returns `'#'`; otherwise splits on `/[\s\-_/.]+/` and takes first letters of the first two words, or (single-word fallback) the first two alphanumeric characters uppercased — a value with exactly one alphanumeric character returns that one character, not `'#'`. Only zero alphanumeric characters falls through to `'#'`.

**Accordion-open invariant:** `modulesOpen`/`recordsOpen` each init to `true` only if their *own* section has ≥1 pin — never OR-in an `=== 0` fallback on the other section's count, or both stay permanently open.

**`recordsUrl` contract:** the Blade passes `url('/workspace/records/__RES__')`; the factory does `recordsUrl.replace('__RES__', pickerResource)`. No compile-time check links the two sides — change both together.

## 6. `search()` — `alpine/components/search.js`

```js
searchQuery: '', isSearching: false, results: [], selectedResult: null, byUser: null,
chain: [], chainLoading: false, chainError: false,
breadcrumb: { purchaseRequest, proformaInvoice, purchaseOrder, registeredOrder, bankProfile, payment, shipment, custom }  // 8 keys, each {state, label}
```

- **`performSearch()`** — debounced 500ms in the Blade (`@input.debounce.500ms`, not in JS), `GET /api/search/spotlight?q=`. Reads `r.data.by_user` (snake_case — matches the API payload; not `byUser`). Does **not** touch `breadcrumb`.
- **`selectResult(result)`** — sets `selectedResult`, then (if `result.type`/`result.id` present) lazily loads `GET /api/search/chain?type=&id=` into `chain[]`, and replaces `breadcrumb` wholesale from the response (`r.data.breadcrumb`). This is the only place `breadcrumb` changes — the spotlight response no longer drives it, so there's no pre-selection flash of wrong states.
- **`clearSelected()`** resets `selectedResult`/`chain`/`chainError`.
- **`getOffset(p)`/`getOffsetL(p)`** — SVG progress-ring stroke-offset for the small/large ring (`circumference - (p/100) * circumference`), circumferences precomputed from `r=16`/`r=22`.
- **`breadcrumbStages()`** — maps the `breadcrumb` object into an ordered array with `isLast` for template iteration.
- Focus trap (`tab-search-focus` → `$refs.searchInput?.focus()`) and both debounces are wired in the Blade, not this file.

## 7. `workflow(groups)` — `alpine/components/workflow.js`

Powers the Workflow tab: 4-stage stepper cards with Listen/Watch/Poster/Guide affordances plus an auto-rotating tips ticker. The only factory besides `workspace` that takes an argument, and the only one using a **prefix-match** registration guard.

```js
groups, tips: [], rotateIdx: 0, playing: false, audio: null,
selected: null, textOpen: false, videoOpen: false, paused: false, rotateInterval: null,
accentMap: { blue: {border,bg,text}, green, yellow, red },  // must stay 1:1 with workflow.blade.php's PHP $accent map
```

- `groups` arrives via `x-data="workflow(@js($insightGroups))"`, server-built by the `Workflow` Livewire component — hence the prefix-match guard (`workflow(...)`, never literal `workflow()`).
- `flattenTips()` builds the ticker's flat `tips[]` from each group's `tips[]`. `startRotate()` (7000ms) skips a tick while `textOpen`/`videoOpen`/`playing`/`paused` (hover); `togglePause()` toggles `paused`. `prefers-reduced-motion` disables auto-rotation entirely. Ticker is `x-show="tips.length >= 2"` + `x-cloak` — silent until ≥2 authored tips exist.
- `openText(group)`/`openVideo(group)` set `selected` then toggle `textOpen`/`videoOpen`; `openVideo()` calls `stopAudio()` first so narration and video never overlap. Both modals must stay inside the single `workflow(...)` root or their `x-show` bindings break.
- `playPause(group)` creates a fresh `new Audio(group.audio)` per play (narration is short, one-shot — reuse buys nothing), dispatches `lp-audio-play` (`{source: 'workflow'}`) before playing, and nulls the instance in `stopAudio()`.
- Each stage card's four buttons are gated server-side per `$insight` field: Listen (`@click="playPause"`), Watch (`@click="openVideo"`), Poster (plain `<a href target="_blank">`, no JS — `href` is `asset()`-generated, never user input), Guide (`@click="openText"`). `hasContent(group)` is the JS mirror of Guide's server-side `terms/process/dos/donts` gate.
- **Blade gotcha:** put Alpine `:class` on a raw `<span>`/`<svg>` wrapper, never directly on an `<x-heroicon-*>` Blade component — Blade PHP-evaluates the expression on component attributes and fatals ("Call to undefined function").

## 8. Custom events

| Event | Direction | Producer | Consumer | Notes |
|---|---|---|---|---|
| `dark-mode-toggled` | `window` → Alpine | **none** | `landing-page.js` `init()` | Listener exists, no producer — `switchers.blade.php` mutates `darkMode` directly in the same Alpine scope. Reserved for a future external (Filament-panel) bridge. **Do not assume it fires.** |
| `calendar-toggled` | Livewire `dispatch` | `app/Livewire/CalendarToggle.php` | `#[On('calendar-toggled')]` on Filament CRUD pages | Pairs with the `maybeJalali()` PHP helper reading `session('calendar_type')`. |
| `tab-search-focus` | Alpine `$dispatch` → `.window` listener | `landing-page.blade.php` (Ctrl/Cmd+K) and `header.blade.php` (search-tab click), both after setting `activeTab = 'search'` | `search.blade.php`'s `@tab-search-focus.window` | Must fire inside `$nextTick(() => $dispatch(...))` — see anti-patterns. |
| `lp-audio-play` | `window` `CustomEvent` | `tri-widget.js` `_broadcastAndPlay()` (`{source:'widget'}`) and `workflow.js` `playPause()` (`{source:'workflow'}`) | both files' own `init()` listener | Mutual-exclusion bus between the two independent audio players — dispatched only on the play-start path, so the event's meaning stays "I am about to play." No singleton, no shared store, two peers. |

## 9. `localStorage` keys (exact contract)

| Key | Values | Writer | Reader |
|---|---|---|---|
| `theme` | `'dark'` \| `'light'` | `landing-page.js` `$watch('darkMode')` | `landing-page.js` `init()` |
| `lp_tab` | `'workflow'` \| `'customize'` \| `'search'` | `landing-page.js` `$watch('activeTab')` | `landing-page.js` `init()` |
| `lp_widget_open` | `'1'` \| `'0'` | `landing-page.js` `$watch('widgetOpen')` | `landing-page.js` `init()` |
| `lp_widget_min` | `'1'` \| `'0'` | `landing-page.js` `$watch('widgetMinimized')` | `landing-page.js` `init()` |
| `lp_music` | `{idx: 0..3, volume: 0..1}` (JSON) | `tri-widget.js` `saveMusic()` | `tri-widget.js` `_loadMusicPrefs()` |
| `user_shortcuts` | `{modules: [...ids], records: [...pins]}` (JSON) | `workspace.js` `persist()` | `workspace.js` `readStorage()` |
| `nav_dock` | `'side'` \| `'bottom'` | inline `x-on:click` in `nav-dock-toggle.blade.php` | `filament/nav-dock.js` (once, into `Alpine.store('navDock')`) |
| `topbar_pinned` | `'1'` \| `'0'` | inline `x-on:click` in `topbar-pin-toggle.blade.php` | `filament/topbar-autohide.js` (once, into `Alpine.store('topbarPinned')`) |

The six landing-page keys go through helper functions that wrap reads/writes in try/catch (quota/privacy-mode failures swallowed) — replicate that for any new landing-page key. `nav_dock`/`topbar_pinned` are the exception: written directly from Blade `x-on:click` (no factory, no try/catch) and read once at Alpine-store init — a genuinely different, simpler pattern because these two toggles live panel-wide, not inside a landing-page factory.

## 10. Filament panel-wide scripts — `resources/js/filament/*.js`

Both register on `alpine:init`, guard against double-binding (`window.__navDockBound` / `window.__topbarPinBound`), seed an `Alpine.store()` from `localStorage`, then re-apply their effect via `Alpine.effect()` **and** on `livewire:navigated` (required because the panel runs `->spa()`, so a plain Alpine watcher doesn't survive an SPA transition).

- **`nav-dock.js`** toggles `html.nav-dock-bottom` and opens/closes the real Filament sidebar (`Alpine.store('sidebar')`, not a CSS-faked collapse) to match `$store.navDock`. It also re-points sidebar tooltip placement via `el._tippy?.setProps({placement})`, deferred one `requestAnimationFrame` past the sidebar toggle — Filament's own tooltip effect has no awareness of dock mode and would otherwise clobber the placement back to left/right if not sequenced after it.
- **`topbar-autohide.js`** toggles `html.topbar-pinned` from `$store.topbarPinned`; when unpinning, it also adds `html.topbar-force-hidden` for 400ms (a CSS rule wins over the hover/focus-within reveal for that transition) so a click-while-hovering doesn't leave the bar stuck open.

**Tooltip lesson (applies project-wide):** a tooltip whose content is a plain PHP string baked into static HTML by a Livewire server round-trip (e.g. `calendar-toggle.blade.php`'s `$tooltip`) is reliable by construction. `nav-dock`/`topbar-pin` have no server round-trip (pure client-side `Alpine.store` + `localStorage`) — reaching for a client-JS-computed `x-tooltip` content expression on a single button is the wrong shape and is unreliable. The working fix is two static button variants per toggle (one per state), each with its own literal Blade-interpolated tooltip string, switched via `x-show`/`x-cloak` on the store value.

## Developer Decision Matrix

| When you need to… | Do this… | Why… |
|---|---|---|
| Add a new Alpine component | Create `alpine/components/<name>.js` exporting `function Name()`; import + register in `alpine/loader.js`. Gate with exact-match `[x-data="Name()"]` if argless, or prefix-match `[x-data^="name("]` if it takes a payload. | Keeps the hub the single registration point; `workflow(groups)` is the prefix-match precedent. |
| Coordinate two independent audio players | Dispatch `lp-audio-play` before `.play()`; listen for it in `init()`. | Matches the `workflow` ↔ `triWidget` bus — no singleton, no shared store. |
| Persist user-visible state across reloads | Add a `localStorage` key; wrap reads/writes in try/catch; read in `init()`, write in a `$watch`. | Matches the six landing-page keys in §9. |
| Debounce user input | `@input.debounce.Nms="handler()"` in the Blade, not `setTimeout` in JS. | Two debounce layers interact unpredictably. |
| Initialize a heavy object (`Audio`, `Worker`) | Create it on first use inside the handler that needs it, never in `init()`. | Avoids memory cost + autoplay-block warnings before any user gesture. |
| Guard an async fetch against stale responses | Increment a `reqId` counter before the fetch; re-check it in every `.then`/`.catch`/`.finally`. | See `workspace.searchRecords()`. |
| Add a tab to `triWidget` | Add the key to `tab`; gate any lazy `Audio` via `$watch('tab', ...)`; ship an inline heroicon SVG (no emoji). | Matches Clock/Timer/Music. |
| Change the workspace search URL shape | Update both `workspace.js`'s `recordsUrl.replace('__RES__', ...)` and `workspace.blade.php`'s `url('/workspace/records/__RES__')`. | No compile-time check links the two sides. |

## Absolute Anti-Patterns (Do Not Do This)

- ❌ **Reintroduce a Three.js/particle/torus background.** Deleted in the enterprise redesign; the landing page uses a CSS-only dot-grid now.
- ❌ **Use `class`/`new` for an Alpine component.** Breaks `Alpine.data(name, factory)`'s `this` binding and the `{ ...workspace(config) }` spread pattern.
- ❌ **Call `document.querySelector` inside an Alpine data function.** Use `$refs`/`$el`/`$watch`/event targets. Only `alpine/loader.js` may use it, as the registration guard.
- ❌ **Instantiate `Audio` in `init()`.** Alarm/music/workflow-narration audio are all lazy by design — early instantiation costs memory and triggers autoplay-block warnings.
- ❌ **Add `calendar: 'persian'` to an `fa-IR` date formatter.** `fa-IR` resolves to the Persian calendar implicitly; only `numberingSystem: 'latn'` belongs.
- ❌ **Recreate the music `Audio` instance on `next()`/`prev()`.** Only `audio.src` swaps via `_switchTrack()`; `loadCurrentTrack()` is the single creation site.
- ❌ **Put debounce logic in JS.** Debounces live in the Blade (`@input.debounce.Nms`) for both `search` (500ms) and `workspace` (300ms).
- ❌ **Assume `dark-mode-toggled` fires.** It has a listener but no producer — code depending on it silently never runs.
- ❌ **Swap the alarm auto-stop to `setTimeout`.** The self-clearing `setInterval` + guarded `clearInterval` pattern is intentional; keep it consistent.
- ❌ **Dispatch `tab-search-focus` without `$nextTick`.** Without it the search panel isn't mounted yet and `$refs.searchInput` is `undefined` (the listener's `?.focus()` is only a defensive no-op backstop).
- ❌ **Register `Alpine.data` outside `alpine/loader.js`.** Scattered registrations break the lazy/DOM-gated invariant.
- ❌ **Introduce `Alpine.store()` inside a landing-page factory, a Proxy hub, a service worker, or a PWA manifest.** `filament/nav-dock.js`/`topbar-autohide.js` are the only sanctioned `Alpine.store()` users, and only because they must survive Livewire SPA navigation — not a precedent for landing-page factories.
- ❌ **Bypass the `recordReqId` guard with a fire-and-forget fetch.** Every `.then`/`.catch`/`.finally` in `searchRecords()` re-checks `reqId === this.recordReqId` before mutating state, so stale responses no-op.
- ❌ **Change the `__RES__` placeholder contract on only one side.** `workspace.blade.php` and `workspace.js` must change together — no compile-time check.
- ❌ **Reach for a client-JS-computed tooltip `x-tooltip` content expression on a store-only toggle (no server round-trip).** See §10's tooltip lesson — use two static button variants instead.
