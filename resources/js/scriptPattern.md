# BMS-CM JavaScript / Alpine.js Pattern

Verified against source on branch `feature/landing-page-enterprise-redesign` (2026-07-18). Where this doc conflicts with CLAUDE.md's JavaScript/Alpine section, this doc is authoritative — CLAUDE.md is stale on file names (`workspace.js` vs `workspace-alpine.js`, `search-tab.blade.php` vs `search.blade.php`), defaults (`activeTab 'customize'` vs `'workflow'`, `darkMode` init), timer presets (25 vs 30/60), breadcrumb shape (3 vs 8 keys), and the `dark-mode-toggled` event (listener with no producer).

## Purpose

BMS-CM's browser interactivity is a deliberately small, hand-rolled Alpine.js layer: four pure-function factories (`landingPage`, `triWidget`, `search`, `workspace`) wired through a single `alpine-loader.js` hub. There is no build-time framework, no PWA / service worker, no Three.js, no global store, no Proxy hub. The whole landing page (clock/timer/music widget, workflow stepper, spotlight search, workspace pinning, dark-mode + tab persistence) runs on roughly 450 lines of Alpine data plus inline Blade directives.

## Core idea

**Lazy, DOM-gated registration + pure factories + lazy Audio + localStorage persistence.** Every Alpine component is a `function Name()` returning a plain object — never `class`, never `new`, never `Alpine.store()`. The loader hub (`alpine-loader.js`) is the only place `document.querySelector` is permitted: it gates each factory's registration on the presence of its root element in the DOM, so unused factories cost zero registration. Heavy objects (`Audio`) are created on first use, not in `init()`. Cross-session state survives in three `localStorage` keys; in-session cross-component state survives via two `window` `CustomEvent`s.

**Contrast with heavier Alpine setups:** a Proxy-hub / store-based architecture (e.g. the sibling Fateh project) centralizes reactive state in `Alpine.store()` plus a magic proxy, layers PWA service-worker caching, and shares mixins across components. BMS-CM deliberately rejects all of that — each component owns its local state, persists only what the user would notice losing (theme, active tab, pinned shortcuts), and communicates through plain DOM events. The trade-off: more code duplication, far less abstraction, but zero hidden reactivity and trivial grep-ability.

## Directory & entry pipeline

```
resources/js/
├── app.js                       ← ONLY Vite JS entry (imports bootstrap + alpine-loader.js)
├── alpine-loader.js             ← registration hub (22 lines)
├── landing-page-alpine.js       ← landingPage()        (32 lines)
├── tri-widget-alpine.js         ← triWidget()          (164 lines)
├── search-alpine.js             ← search()             (62 lines)
└── workspace-alpine.js          ← workspace(config)   (170 lines)

vite.config.js
└── input: resources/js/app.js    (single JS entry; CSS entries listed separately)
    └── viteStaticCopy targets: resources/img, resources/audio, resources/video
        (NO JS in static-copy; landing-page.js & 3d.min.js deleted, not replaced)
```

**Registration flow:**

1. Vite bundles `app.js` + the four factory modules into one asset.
2. `app.js` imports `./alpine-loader.js`, which imports `Alpine` + all four factories.
3. `alpine-loader.js` listens for `alpine:init` and registers each factory with `Alpine.data(name, factory)`. `landingPage` and `triWidget` are gated by `document.querySelector('[x-data="…()"]')`; `search` and `workspace` register unconditionally.
4. `Alpine.start()` is called exactly once, guarded by `window.__alpine_running`.

## 1. The registration hub — `resources/js/alpine-loader.js`

```js
import Alpine from 'alpinejs';
import landingPage from './landing-page-alpine.js';
import triWidget from './tri-widget-alpine.js';
import search from './search-alpine.js';
import workspace from './workspace-alpine.js';

window.Alpine = window.Alpine || Alpine;

document.addEventListener('alpine:init', () => {
    if (document.querySelector('[x-data="landingPage()"]')) Alpine.data('landingPage', landingPage);
    if (document.querySelector('[x-data="triWidget()"]')) Alpine.data('triWidget', triWidget);

    Alpine.data('search', search);
    Alpine.data('workspace', workspace);
});

if (!window.__alpine_running) {
    Alpine.start();
    window.__alpine_running = true;
}

export default Alpine;
```

**Rules encoded above:**

- Two factories (`landingPage`, `triWidget`) are **lazy-registered** — each guarded by its own `document.querySelector('[x-data="…()"]')` check. The factory only registers when its root element is in the DOM.
- Two factories (`search`, `workspace`) are **always registered**. (CLAUDE.md's claim that "only `workspace` is always registered" is stale — `search` is too.)
- `window.__alpine_running` is checked **before** `Alpine.start()` and set **after** it, preventing double-init if the bundle is somehow evaluated twice.
- `document.querySelector` appears **nowhere else** in any Alpine data function — only in this hub.

## 2. Factory signature rule (all four factories)

```js
export default function Name(args) {
    return { /* plain object */ };
}
```

No `class`, no `new`, no `Alpine.store(...)`, no magic Proxy. This is mandatory across all four files. Configuration enters via function arguments (only `workspace` takes one); state leaves via `localStorage` keys, `window` events, and the DOM.

## 3. `landingPage()` — `resources/js/landing-page-alpine.js`

```js
export default function landingPage() {
    return {
        darkMode: false,
        activeTab: 'workflow',
        widgetOpen: false,
        init() {
            const get = k => { try { return localStorage.getItem(k); } catch { return null; } };
            const set = (k, v) => { try { localStorage.setItem(k, v); } catch {} };
            const theme = get('theme');
            if (theme !== null) {
                const v = theme.toLowerCase();
                this.darkMode = v === '1' || v === 'true' || v === 'dark' || v === 'on';
            }
            const tab = get('lp_tab');
            if (tab) this.activeTab = tab;
            document.documentElement.classList.toggle('dark', this.darkMode);
            window.addEventListener('dark-mode-toggled', e => { this.darkMode = !!e.detail; });
            this.$watch('darkMode', val => {
                set('theme', val ? 'dark' : 'light');
                document.documentElement.classList.toggle('dark', val);
            });
            this.$watch('activeTab', val => set('lp_tab', val));
        },
    };
}
```

**Corrections vs. stale CLAUDE.md:**

- `darkMode` default is `false` (NOT `localStorage.theme === 'dark'`). The localStorage read happens inside `init()` with a permissive truthy set: `{'1', 'true', 'dark', 'on'}`.
- `activeTab` default is `'workflow'` (NOT `'customize'`). `lp_tab` only overrides when non-null.
- `widgetOpen: false` is extra state driving the floating triWidget panel's visibility — not documented in CLAUDE.md at all.
- The Three.js torus background (`window.torusMaterial` / `window.ringMaterial` opacity watcher) is **gone**, confirmed removed. Do not reintroduce.
- `localStorage` access is wrapped in try/catch — the `get`/`set` helpers swallow quota / privacy-mode failures silently. Replicate this pattern for any new localStorage use.

## 4. `triWidget()` — `resources/js/tri-widget-alpine.js`

Three tabs: Clock / Timer / Music. Key state (verbatim): `tab: 'clock'`, `clockString: ''`, `dateString: ''`, `shamsiDateString: ''`, `timer: { running: false, seconds: 300 }`, `customMins: null`, `alarm: 'alarm.mp3'`, `alarmInterval: null`, `alarmAudioInstance: null`, `music: { tracks: [...3 pCloud...], idx: 0, audio: null, playing: false, position: 0, duration: 0, progress: 0, volume: 0.8 }`.

### 4.1 Clock

`tick()` is defined **inside** `init()` and runs on `setInterval(tick, 1000)`:

```js
clockString   = d.toLocaleTimeString();
dateString    = d.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
shamsiDateString = d.toLocaleDateString('fa-IR', {
    year: 'numeric', month: 'long', day: 'numeric', weekday: 'long', numberingSystem: 'latn'
});
```

**Corrections vs. stale CLAUDE.md:**

- There is **no** `calendar: 'persian'` key — the `fa-IR` locale resolves to the Persian calendar implicitly. Do not add `calendar: 'persian'`.
- `numberingSystem: 'latn'` IS present (forces Western digits in the Persian date).
- Field names are `clockString` / `dateString` / `shamsiDateString` — NOT `timeStr` / `dateStr` / `shamsiDateString`.

### 4.2 Timer

Decrement loop:

```js
setInterval(() => {
    if (timer.running && timer.seconds > 0) {
        timer.seconds--;
        if (timer.seconds === 0) { timer.running = false; this.startAlarmLoop(); }
    }
}, 1000);
```

SVG ring (in `widget.blade.php:106`):

```blade
:stroke-dasharray="{{ (timer.seconds / 300) * 351.86 }} 351.86"
```

Presets (`widget.blade.php:156-168`) call `setTimerPreset(seconds)` with these exact values:

```
300   = 5 min
600   = 10 min
900   = 15 min
1800  = 30 min
3600  = 60 min
```

**Correction:** presets are **5/10/15/30/60**, NOT 5/10/15/25. The `customMins` input calls `setTimerPreset(customMins * 60)` on enter / blur / dblclick.

### 4.3 Alarm (lazy Audio)

```js
startAlarmLoop() {
    this.stopAlarm();
    const a = new Audio('/audio/' + this.alarm);
    a.loop = true;
    this.alarmAudioInstance = a;
    a.play().catch(() => {});
    this.alarmInterval = setInterval(() => this.stopAlarm(), 60000);
}
stopAlarm() {
    if (this.alarmInterval) {
        clearInterval(this.alarmInterval);
        this.alarmInterval = null;
    }
    if (this.alarmAudioInstance) {
        this.alarmAudioInstance.pause();
        this.alarmAudioInstance.currentTime = 0;
        this.alarmAudioInstance = null;
    }
}
```

- Alarm `Audio` is **lazy** — created in `startAlarmLoop()`, NOT in `init()`.
- `startAlarmLoop()` calls `this.stopAlarm()` first, defensively tearing down any prior alarm/interval before starting a new one (relevant if the timer is reset/restarted while an alarm is still ringing).
- Auto-stop uses `setInterval(() => stopAlarm(), 60000)`, **not** `setTimeout`. `stopAlarm()` guards with `if (this.alarmInterval)` before calling `clearInterval` and then nulls `alarmInterval`, so the interval fires exactly once and a stale handle is never re-cleared. The pattern is subtle: a 60-second one-shot implemented via self-clearing interval. Keep it.

### 4.4 Music (lazy Audio, reused across track changes)

```js
loadCurrentTrack() {
    if (!this.music.audio) {
        this.music.audio = new Audio();
        this.music.audio.preload = 'none';
        this.music.audio.volume = this.music.volume;
        this.music.audio.onloadedmetadata = () => { this.music.duration = Math.round(this.music.audio.duration); };
        this.music.audio.ontimeupdate = () => {
            this.music.position = Math.round(this.music.audio.currentTime || 0);
            this.music.progress = (this.music.position / (this.music.duration || 1)) * 100;
        };
        this.music.audio.onended = () => this.next();
    }
    if (!this.music.audio.src || !this.music.audio.src.includes(this.currentTrack.src)) this.music.audio.src = this.currentTrack.src;
}
playPause() {
    if (!this.music.audio || !this.music.audio.src || !this.music.audio.src.includes(this.currentTrack.src)) this.loadCurrentTrack();
    /* play() or pause() */
}
next() { this.music.idx = (this.music.idx + 1) % this.music.tracks.length; /* swap src, auto-play */ }
prev() { this.music.idx = (this.music.idx - 1 + this.music.tracks.length) % this.music.tracks.length; /* swap src, auto-play */ }
get currentTrack() { return this.music.tracks[this.music.idx] || { title: '', src: '' }; }
```

- `Audio` is created **once**, on first `loadCurrentTrack()`, with `volume` seeded from `this.music.volume` (0.8 default) at creation time. `next()`/`prev()` only swap `audio.src` (and call `.play()`) — they do NOT recreate the instance.
- The final `src` assignment in `loadCurrentTrack()` is **guarded** (`!this.music.audio.src || !…includes(currentTrack.src)`), not unconditional — reassigning the same `src` value would restart/reload the currently-playing track.
- `ontimeupdate` rounds `position` via `Math.round(currentTime || 0)`; it is not the raw float `currentTime`.
- `playPause()` re-`loadCurrentTrack()`s if there's no audio yet or `audio.src` doesn't include `currentTrack.src` (handles the case where the user switches track while paused).
- Track artwork: `/img/widget/{pop,lofi,pomodoro}.png`. Three pCloud CDN tracks — track `title` values are exactly `'Ambient Pop'`, `'LoFi'`, `'Pomodoro'` (not "Pomodoro Focus").
- `init()` wires `this.$watch('tab', val => { if (val === 'music') this.loadCurrentTrack(); })` so the first `Audio` is created only when the user opens the Music tab.
- **Correction:** there is **no** localStorage persistence of `music.idx` — the index survives only in-memory across tab switches (the component instance isn't destroyed while mounted). Do not add "remember last track" without changing this design.

### 4.5 Icons (enterprise redesign)

Heroicons (inline SVG) replaced the old emoji tab icons (🕙⏱️🎵). When adding a tab, ship an inline heroicon SVG in the blade — do not re-introduce emoji icons.

## 5. `workspace()` — `resources/js/workspace-alpine.js`

**File-name correction:** the file is `workspace-alpine.js`, NOT `workspace.js` (CLAUDE.md is stale). The Blade consumes it via:

```blade
x-data="{ ...workspace({{ json_encode($workspaceConfig) }}), showModulePicker: false, showRecordPicker: false }"
```

so the factory accepts a `config` object and its return is **spread** into an outer Alpine scope that adds `showModulePicker` / `showRecordPicker` booleans.

### 5.1 Storage shape

```js
const STORAGE_KEY = 'user_shortcuts';
readStorage()  // parses localStorage['user_shortcuts'] as { modules: [...ids], records: [...pins] }
persist()      // writes { modules: this.pinnedModuleIds, records: this.recordPins }
```

Pin shape: `{ key, resourceId, recordId, label, subtitle, url }`.

Legacy migration: if the parsed value is an array (old format), `readStorage()` maps it to `{ modules: parsed.map(s => s.id), records: [] }`. Keep this migration — old users have the array shape.

### 5.2 Record search with request-ID race guard

```js
searchRecords() {
    if (!this.pickerResource) { this.recordResults = []; return; }
    const reqId = ++this.recordReqId;
    this.recordLoading = true;
    this.recordError = false;
    const url = this.recordsUrl.replace('__RES__', this.pickerResource)
              + '?q=' + encodeURIComponent(this.recordQuery || '');
    fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(r => (r.ok ? r.json() : Promise.reject(r)))
        .then(json => { if (reqId === this.recordReqId) this.recordResults = json?.data ?? []; })
        .catch(() => { if (reqId === this.recordReqId) { this.recordError = true; this.recordResults = []; } })
        .finally(() => { if (reqId === this.recordReqId) this.recordLoading = false; });
}
```

**Correction:** the loading flag is `recordLoading`, NOT `isSearchingRecords` (that property doesn't exist anywhere in the file). The guard also bails early with `if (!this.pickerResource) return`, resets `recordError` before each attempt, sends an `Accept: application/json` header, and rejects on a non-OK HTTP status (`r.ok ? r.json() : Promise.reject(r)`) before parsing.

- `recordReqId` is incremented **before** the fetch; every `.then` / `.catch` / `.finally` re-checks `reqId === this.recordReqId` before mutating state.
- Any new call (including the escape-key clear at `workspace.blade.php:350`) invalidates in-flight responses by bumping `recordReqId`.

### 5.3 Helpers

```js
decorateRecord(p) {
    const parent = this.modules.find(m => m.id === p.resourceId) || {};
    return { ...p, icon: parent.icon || p.icon || '', theme: parent.theme || p.theme || 'from-slate-500 to-slate-600' };
}
initials(value) {
    const text = (value || '').toString().trim();
    if (!text) return '#';
    const parts = text.split(/[\s\-_/.]+/).filter(Boolean);
    if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
    const alnum = text.replace(/[^a-zA-Z0-9]/g, '');
    return (alnum.slice(0, 2) || '#').toUpperCase();
}
```

**Correction:** falsy/empty `value` (`null`, `undefined`, `''`, whitespace-only) short-circuits to `'#'` up front. In the fallback branch there is **no** `alnum.length >= 2` gate — `alnum.slice(0, 2)` is taken regardless of length, so a value with exactly **one** alphanumeric character (e.g. `"A"`) returns that single uppercased character, not `'#'`. Only a value with **zero** alphanumeric characters falls through to `'#'`.

### 5.4 Closed-by-default accordion-open

```js
// Closed by default; open only when there's something pinned to show.
this.modulesOpen = this.pinnedModuleIds.length > 0;
this.recordsOpen = this.recordPins.length > 0;
```

Each accordion opens **only** if its own section has at least one pin — otherwise it stays closed, including for a fresh user with zero pins in either section. **Correction:** an earlier revision of this logic was `this.pinnedModuleIds.length > 0 || this.recordPins.length === 0` (and the `recordsOpen` mirror), which is a tautology — the right-hand `=== 0` branch is true whenever the left-hand branch is false, so the expression evaluated to `true` unconditionally and both accordions were *always* open regardless of pin state. That was fixed in this session; do not reintroduce the `|| … === 0` fallback.

### 5.5 `recordsUrl` placeholder contract

The Blade passes `url('/workspace/records/__RES__')`. The `__RES__` literal is replaced at call time via `recordsUrl.replace('__RES__', pickerResource)`. Any change to this protocol must be coordinated between `workspace-alpine.js` and `workspace.blade.php` — there is no compile-time check.

## 6. `search()` — `resources/js/search-alpine.js`

**File-name correction:** the factory lives in `search-alpine.js` and the Blade is `search.blade.php` using `x-data="search"`. The search tab is **not** inline Alpine and **not** `search-tab.blade.php` (CLAUDE.md is stale).

### 6.1 State

```js
searchQuery: '',
isSearching: false,
results: [],
selectedResult: null,
byUser: null,
breadcrumb: {
    purchaseRequest:   { state: 'upcoming', label: '…' },
    proformaInvoice:   { state: 'upcoming', label: '…' },
    purchaseOrder:     { state: 'upcoming', label: '…' },
    registeredOrder:   { state: 'upcoming', label: '…' },
    bankProfile:       { state: 'upcoming', label: '…' },
    payment:           { state: 'upcoming', label: '…' },
    shipment:          { state: 'upcoming', label: '…' },
    custom:            { state: 'upcoming', label: '…' },
}
```

**Correction:** the `breadcrumb` object has **8 keys** (one per operational model), NOT 3 (CLAUDE.md is stale). The whole `breadcrumb` is replaced wholesale by the server response: `if (r.data.breadcrumb) this.breadcrumb = r.data.breadcrumb;`.

### 6.2 Precomputed ring circumferences

```js
const C = 2 * Math.PI * 16;    // small ring
const Cl = 2 * Math.PI * 22;   // large ring
getOffset(p)  = C  - (p / 100) * C;
getOffsetL(p) = Cl - (p / 100) * Cl;
```

### 6.3 `performSearch()`

```js
async performSearch() {
    if (this.searchQuery.length < 2) {
        this.results = []; this.selectedResult = null; this.byUser = null;
        return;
    }
    const r = await axios.get('/api/search/spotlight?q=' + encodeURIComponent(this.searchQuery));
    this.results = r.data.results;
    this.byUser  = r.data.byUser;
    if (r.data.breadcrumb) this.breadcrumb = r.data.breadcrumb;
}
```

- Debounce 500ms is wired **in the Blade** (`search.blade.php:12 @input.debounce.500ms="performSearch"`), not in the JS.
- Focus trap is wired **in the Blade**: `search.blade.php:3 @tab-search-focus.window="$nextTick(() => $refs.searchInput?.focus())"`.
- Loading skeleton: two placeholder cards `x-for="i in 2"` with `animate-pulse`, `x-show="isSearching"`.

## 7. Custom events (verified)

| Event | Direction | Producer | Consumer | Notes |
|---|---|---|---|---|
| `dark-mode-toggled` | `window` → Alpine | **NONE** | `landing-page-alpine.js:22` | Listener exists, no producer in the codebase. `switchers.blade.php` mutates `darkMode` directly within the same Alpine scope. The listener is reserved for a future external bridge (e.g. Filament panel theme change). **Do not assume it fires.** |
| `calendar-toggled` | Livewire `dispatch` | `app/Livewire/CalendarToggle.php:21` `$this->dispatch('calendar-toggled')` | `#[On('calendar-toggled')]` on Filament pages (CreateRecord / ManageRecords / ListRecords / EditRecord) | Pairs with the `maybeJalali()` PHP helper reading `session('calendar_type')`. |
| `tab-search-focus` | Alpine `$dispatch` → `.window` listener | `landing-page.blade.php:31`, `header.blade.php:33`, `tabs.blade.php:19` (all `$nextTick(() => $dispatch('tab-search-focus'))` after setting `activeTab = 'search'`) | `search.blade.php:3` | Wraps `window.dispatchEvent(new CustomEvent(...))` via Alpine's `$dispatch`. |

### Ctrl/Cmd+K → search tab + focus (`landing-page.blade.php:31`)

```blade
@keydown.window="if (($event.metaKey || $event.ctrlKey) && $event.key === 'k') {
    $event.preventDefault();
    activeTab = 'search';
    $nextTick(() => $dispatch('tab-search-focus'));
}"
```

Both `$nextTick` (waits for `activeTab = 'search'` to mount the search panel) and the optional chaining `?.focus()` (defensive against a missing ref) are load-bearing. Same wiring is duplicated in `header.blade.php:33` and `tabs.blade.php:19`.

## 8. `localStorage` keys (exact contract)

| Key | Values | Writer | Reader |
|---|---|---|---|
| `theme` | `'dark'` \| `'light'` | `landing-page-alpine.js` `$watch('darkMode')` | `landing-page-alpine.js` `init()` |
| `lp_tab` | `'workflow'` \| `'customize'` \| `'search'` | `landing-page-alpine.js` `$watch('activeTab')` | `landing-page-alpine.js` `init()` |
| `user_shortcuts` | JSON `{ modules: [...ids], records: [...pins] }` | `workspace-alpine.js` `persist()` | `workspace-alpine.js` `readStorage()` |

All three reads are wrapped in try/catch (quota / privacy-mode failures are swallowed). Do not add new localStorage keys without the same try/catch wrapper.

## Developer Decision Matrix

| When you need to… | Do this… | Why… |
|---|---|---|
| Add a new Alpine component | Create `resources/js/<name>-alpine.js` exporting `function Name()`; import it in `alpine-loader.js`; register with `Alpine.data('name', Name)`. Gate with `document.querySelector('[x-data="Name()"]')` only if the root element is page-specific. | Keeps the hub as the single registration point; preserves lazy, DOM-gated registration for components only used on one page. |
| Persist user-visible state across reloads | Add a `localStorage` key; wrap reads/writes in try/catch; read in `init()`, write in a `$watch`. | Matches `theme` / `lp_tab` / `user_shortcuts` pattern; survives browsers that block localStorage. |
| Debounce user input | Put `@input.debounce.Nms="handler()"` in the Blade, not a JS-side `setTimeout`. | Single source of truth (Blade), no duplicate debounce in JS; matches `search` (500ms) and `workspace` (300ms). |
| Initialize a heavy object (`Audio`, `Worker`, etc.) | Create it on first use inside the handler that needs it, never in `init()`. | Matches alarm `Audio` (in `startAlarmLoop`) and music `Audio` (in `loadCurrentTrack`). Avoids paying init cost for tabs/features the user never opens. |
| Communicate across Alpine scopes | `window.dispatchEvent(new CustomEvent('name', { detail }))` from the producer; `window.addEventListener('name', …)` in the consumer's `init()`. | Matches `tab-search-focus` (the only event actually wired both ways today). |
| Guard an async fetch against stale responses | Increment a `reqId` counter before the fetch; re-check `reqId === this.reqId` in every `.then` / `.catch` / `.finally`. | Matches `workspace.searchRecords()`. Cheaper and clearer than AbortController for the small race windows here. |
| Add a tab to `triWidget` | Add the tab key to the `tab` state; gate any lazy `Audio` via `$watch('tab', val => val === 'newtab' && this.load…())`; ship an inline heroicon SVG in `widget.blade.php` (no emoji). | Matches the Clock/Timer/Music pattern; keeps heavy init deferred. |
| Change the workspace search URL shape | Update both `workspace-alpine.js` (the `recordsUrl.replace('__RES__', …)` call) and `workspace.blade.php` (the `url('/workspace/records/__RES__')` literal). | The `__RES__` placeholder is a string contract with no compile-time check; one-side edits silently break search. |

## Absolute Anti-Patterns (Do Not Do This)

- ❌ **Reintroduce `landing-page.js` or `3d.min.js`** (or any Three.js / particle / torus background).
  *Why:* Deleted in the enterprise redesign. `vite.config.js` no longer references them as entries or static-copy targets. The landing page now uses a cheap CSS-only hairline dot-grid. Adding JS-rendered backgrounds breaks the deliberate-minimalism contract.
- ❌ **Use `class` syntax or `new` for an Alpine component.**
  *Why:* All four factories are `export default function Name()` returning a plain object. Class syntax breaks `Alpine.data(name, factory)`'s `this` binding and the spread-in-Blade pattern (`{ ...workspace(config) }`).
- ❌ **Call `document.querySelector` inside an Alpine data function.**
  *Why:* Use `$refs`, `$el`, `$watch`, or event targets instead. `document.querySelector` appears only in `alpine-loader.js` as the registration guard. Pushing it into components couples them to global DOM structure and breaks the "pure factory" invariant.
- ❌ **Instantiate `Audio` in `init()`.**
  *Why:* The alarm and music `Audio` objects are deliberately lazy — created in `startAlarmLoop()` and `loadCurrentTrack()`. Early instantiation costs memory and triggers browser autoplay-block warnings before any user gesture.
- ❌ **Add `calendar: 'persian'` to the `fa-IR` date formatter.**
  *Why:* `fa-IR` resolves to the Persian calendar implicitly. The only option that belongs is `numberingSystem: 'latn'`. Adding `calendar: 'persian'` is redundant and has caused inconsistent output across browsers.
- ❌ **Recreate the music `Audio` instance on `next()` / `prev()`.**
  *Why:* Only `audio.src` swaps. Re-`new Audio()` leaks the previous instance and re-triggers `onloadedmetadata` wiring. `loadCurrentTrack()` is the single creation site.
- ❌ **Put debounce logic in JS.**
  *Why:* Debounces live in the Blade (`@input.debounce.Nms`). Duplicating in JS creates two debounce layers that interact unpredictably.
- ❌ **Assume `dark-mode-toggled` fires.**
  *Why:* It has a listener in `landing-page-alpine.js:22` but **no producer**. `switchers.blade.php` mutates `darkMode` directly. Code that depends on this event will silently never run.
- ❌ **Use `setTimeout` for the alarm auto-stop.**
  *Why:* The pattern is `setInterval(() => stopAlarm(), 60000)` with `stopAlarm()` calling `clearInterval`. A plain `setTimeout` works too but the codebase chose the self-clearing interval — keep it consistent or the alarm teardown path diverges.
- ❌ **Dispatch `tab-search-focus` without `$nextTick` after switching to the search tab.**
  *Why:* Without `$nextTick`, the search panel isn't yet in the DOM and `$refs.searchInput` is `undefined`. The `.window` listener uses `?.focus()` defensively, but the focus will silently no-op.
- ❌ **Register `Alpine.data` outside `alpine-loader.js`.**
  *Why:* The hub is the single registration point. Scattered registrations break the lazy/DOM-gated invariant and make it impossible to audit which components exist.
- ❌ **Introduce `Alpine.store()`, a Proxy hub, a service worker, or PWA manifests.**
  *Why:* BMS-CM's deliberate minimalism is the point. If you find yourself reaching for a store, extract a small pure function or use `localStorage` + a `$watch` instead.

## Gotchas & load-bearing details

- **Request-ID race guard invalidates in-flight responses.** Any new call to `workspace.searchRecords()` (including the escape-key clear at `workspace.blade.php:350`) bumps `recordReqId`, so every `.then` / `.catch` / `.finally` from the old request no-ops. Keep this contract — do not add a fire-and-forget fetch that bypasses the guard.
- **Lazy Audio + self-clearing interval.** `startAlarmLoop()` uses `setInterval(() => stopAlarm(), 60000)` and `stopAlarm()` calls `clearInterval(this.alarmInterval)` — the interval fires exactly once. `stopAlarm()` also tears down `alarmAudioInstance` (`pause()` + `currentTime = 0` + null). Missing any of these steps leaks audio playback or a dangling interval.
- **Music `Audio` is reused, not recreated.** `next()` / `prev()` only swap `audio.src`. `playPause()` re-calls `loadCurrentTrack()` only when `audio.src` doesn't include `currentTrack.src` (handles "user switched track while paused" without creating a new instance).
- **`$nextTick` before `$dispatch('tab-search-focus')` is mandatory.** Producers set `activeTab = 'search'` first, then `$nextTick(() => $dispatch('tab-search-focus'))` so the search panel is mounted before the consumer calls `$refs.searchInput?.focus()`. The `?.` is defensive; the `$nextTick` is load-bearing.
- **Debounces live in the Blade.** `search` uses `@input.debounce.500ms`; `workspace` uses `@input.debounce.300ms`. Do not add JS-side debounce — it doubles the delay.
- **`__RES__` placeholder is a string contract.** `workspace.blade.php` passes `url('/workspace/records/__RES__')`; `workspace-alpine.js` replaces it with `pickerResource`. There is no compile-time check — edits must be coordinated on both sides.
- **`fa-IR` → Persian calendar is implicit.** `numberingSystem: 'latn'` is explicit. Do not add `calendar: 'persian'`.
- **`dark-mode-toggled` listener with no producer.** The `window.addEventListener` in `landing-page-alpine.js:22` is real, but no code in the repo dispatches the event. `switchers.blade.php` mutates `darkMode` in the same Alpine scope. Treat the listener as forward-compat scaffolding for a future Filament-panel bridge, not as active wiring.
- **`window.__alpine_running` is checked before `Alpine.start()` and set after.** Order matters — inverting it would allow double-init on re-evaluation.
- **`localStorage` access is wrapped in try/catch.** `landing-page-alpine.js` uses `get` / `set` helpers that swallow quota and privacy-mode failures. Replicate this for any new key.
- **`widgetOpen: false` is the floating triWidget panel toggle.** It lives in `landingPage()` (not `triWidget()`) because the panel is rendered in the landing-page root scope, not inside the widget's own `x-data`. Do not move it.
- **CLAUDE.md JS section is stale on ~11 points.** This doc is authoritative. The stale items: file names (`workspace.js` → `workspace-alpine.js`, `search-tab.blade.php` → `search.blade.php`), `darkMode` default (`localStorage.theme === 'dark'` → `false`), `activeTab` default (`'customize'` → `'workflow'`), timer presets (5/10/15/25 → 5/10/15/30/60), field names (`timeStr`/`dateStr` → `clockString`/`dateString`), `calendar: 'persian'` claim (doesn't exist), music `idx` localStorage claim (no persistence), `workspace` only-always-registered claim (`search` is too), breadcrumb shape (3 → 8 keys), `dark-mode-toggled` producer claim (no producer).