# Views Organization

Single reference for how `resources/views/` is organized in this project, plus the landing-page feature in detail. For the CSS token system see `resources/css/stylesPattern.md`; for Alpine.js factories see `resources/js/scriptPattern.md`.

## Folder convention — where a new view file goes

Three top-level buckets, decided by one question each:

| Folder | Goes here when… | Example |
|---|---|---|
| `resources/views/components/` | It's a genuinely reusable `<x-tag>` component, invoked from more than one unrelated place (or trivially, safely reusable even on a single call site). No Livewire class behind it — pure Blade, `@props`/slots/attribute-passthrough only. | `<x-modal>`, `<x-icon-button>` — see "Shared components" below. |
| `resources/views/filament/{feature}/` | Filament-only Blade content — a render-hook partial, a `modalContent(view(...))` target, a custom form-field stub — with **no** Livewire class rendering it. Plain `@include()`/`view()` targets. | `filament/desk-reference/panel.blade.php`, `filament/partials/*.blade.php`, `filament/landing-page/*.blade.php` (the landing page's shell chrome). |
| `resources/views/livewire/` | A `render()` target for a real `Livewire\Component` class in `app/Livewire/`. Always a 1:1 match — every file here has exactly one class pointing at it. | `livewire/calendar-toggle.blade.php` ↔ `App\Livewire\CalendarToggle`; `livewire/landing-page/workflow.blade.php` ↔ `App\Livewire\LandingPage\Workflow`. |

`resources/views/components/filament/` (an anonymous-component namespace some landing-page partials briefly used) is retired — nothing in this codebase is invoked via `<x-filament::...>`-style custom tags from that path. Don't resurrect it for new work.

Other top-level dirs, unrelated to the above split, left as-is: `resources/views/errors/` (the 401/403/404/etc. shell — one `layout.blade.php` + a one-line per-code `@include` each, already at maximum reuse), `resources/views/pdf/` (mPDF-rendered invoice view, different rendering constraints).

## Dead-code sweeps — how to tell "wrong folder" from "actually dead"

A file that doesn't fit the folder convention above is sometimes a placement question, sometimes just dead code — same symptom, different fix (delete, not relocate). The test: **never committed to git** (`git log --all -- <path>` returns nothing) **and zero references anywhere** (`grep` the class/view name across `app/` and `resources/`) — a live, in-progress, uncommitted feature is still *referenced* from somewhere even before its first commit; a truly abandoned one is referenced from nowhere. If a file's *name* strongly suggests it backs an existing shipped feature, verify what actually renders that feature before concluding the obvious-sounding file is the live one — e.g. a topbar desk-reference dropdown attempt (`DeskReferenceMenu` + its view) looked like it might back the shipped desk-reference feature, but `FilamentRenderHooks::configure()` only ever registered `calendar-toggle`/`nav-dock-toggle`; the real feature is `HasDeskReferenceAction`'s header action, unrelated.

---

# Landing Page

The Filament panel's landing page (`filament.landing-page`, rendered by `App\Filament\Pages\LandingPage` on its own custom `layout` view, not the standard panel layout) — three tabs (Customize / Workflow / Search) over the 8 operational + 7 master-data modules, plus a floating clock/timer/music widget and a locale/theme/logout switcher cluster. It reuses Filament's own visual language (tokens, colors) rather than inventing a separate one — see the sync mechanism below.

Each tab body is its own Livewire component under `App\Livewire\LandingPage\*`; the shell chrome around them (loader, switchers, widget, header) is plain Blade + Alpine, no server round-trip, living in `resources/views/filament/landing-page/`.

## The sync mechanism — not a claim, a mapping

"Sync with Filament" means concretely: every surface, active state, and accent color on the landing page is drawn from the same CSS custom properties and the same live `--primary-*` color that `fi-custom.css` and Filament's own components use. There is no second, parallel color system.

| Landing page | Mirrors | Real Filament rule (`fi-custom.css` / Filament core) |
|---|---|---|
| `.lp-surface` | `.fi-section` | `background-color: var(--custom-third-light)` light / `var(--filament-dark-mid)` dark, `box-shadow: var(--md-elevation-1)` |
| `.lp-surface-hover` | `.fi-section:hover` | `background-color: var(--custom-neutral)` light / `var(--google-first-dark)` dark, `box-shadow: var(--md-elevation-2)` |
| `.lp-bar` | `.fi-topbar` | `background-color: var(--custom-third-mid)` light / `var(--filament-dark-mid)` dark, `box-shadow: var(--md-elevation-2)` |
| `.lp-tab` / `.lp-tab-active` | `.fi-tabs-item` / `.fi-tabs-item.fi-active` | inactive: `color: var(--custom-fourth)` light / `var(--google-third-light)` dark. Active: `background-image: var(--gradient-secondary)` light / `var(--gradient-google-deep)` dark, `box-shadow: var(--md-elevation-1)` |
| `.light` / `.dark` (root background) | `.fi-body` | `radial-gradient(ellipse at 50% 0%, var(--google-second-light), transparent 70%)` light / `radial-gradient(ellipse at 50% 120%, var(--custom-third-mid), transparent 60%)` dark |
| `.lp-panel` / `.lp-well` | — (nesting depth, no direct `fi-custom.css` counterpart) | `.lp-panel`: `--custom-third-mid` light / `--google-second-dark` dark, no shadow (tonal recess only, one step in from `.lp-surface`). `.lp-well`: `--custom-neutral-light` light / `--google-first-dark` dark, one step further in. Used for the Customize tab's accordion body so card → panel → scrollable list reads as distinct layers. |
| `.lp-float` | `.fi-modal-window` | `background-color: var(--custom-neutral)` light / `var(--filament-dark-mid)` dark, `box-shadow: var(--md-elevation-3)`. Opaque — for `fixed`/floating overlays only (the tri-widget). `.lp-surface`'s light-mode bg is intentionally translucent for inline cards, but that bled through a floating popover, so floating elements get this opaque variant instead. |
| `text-primary-600` / `text-primary-400` (accent) | Filament's `--primary` panel color | Resolves through the `@theme inline` block in `vendor/filament/support/resources/css/index.css` to the panel's actual configured color — `Color::Slate` in `DashboardPanelProvider.php`. **Not a hardcoded hex** — changes automatically if the panel's primary color ever changes. |

One deliberate deviation: `.lp-tab-active`'s light-mode text is solid `var(--filament-dark)` rather than the literal 40%-opacity gray token — reads too pale against the gradient pill in practice.

`landing-page.css` declares its own tokens only for what `fi-custom.css` doesn't provide (`--font-default`/`--font-fa`/`--font-mono`). The loader introduces no custom properties of its own — every accent is `var(--primary-600)`/`var(--primary-400)` via `color-mix(in oklab, ...)`.

## Semantic palette

Five tones, reused everywhere a module/step/result needs a color:

| Tone | Used for | Matches Filament's `.tb-badge` family |
|---|---|---|
| `blue` | Request & Approval (Purchase Requests, Proforma Invoices) | `.tb-info` |
| `green` | Order Processing (Registered Orders, Bank Profiles) | `.tb-success` |
| `yellow` | Procurement & Payment (Purchase Orders, Payments) | `.tb-warning` |
| `red` | Logistics (Shipments, Customs) | `.tb-danger` |
| `slate` | All master-data modules — no pipeline stage | matches the panel's `primary: Color::Slate` |

The same mapping is used identically in three places — the Workflow stepper, the Workspace module tiles, and Spotlight search results — so a Purchase Order always reads "yellow" everywhere. Hardcoded three separate times with no shared PHP source: the `$accent` array in `Workflow`'s view, the `$tone`/`TONE` constant in the `Workspace` component, and `SearchService::THEME`. Keep all three in sync by hand when a module's stage color changes.

## Component inventory

| File | Role |
|---|---|
| `filament/landing-page.blade.php` | Root. Locale/dark-mode/tab state (`landingPage()` Alpine factory), loader include, tab-panel switching (`@livewire(...)` for each tab). |
| `filament/landing-page/loader.blade.php` | Full-screen boot animation, shown once per browser session right after login. See "Boot sequence" below. |
| `filament/landing-page/switchers.blade.php` | Floating locale / dark-mode / logout / widget-toggle buttons, top-right (RTL-aware). |
| `filament/landing-page/widget.blade.php` | Floating clock/timer/music panel (`triWidget()` factory). |
| `filament/landing-page/header.blade.php` | "Return to dashboard" bar + the Customize/Workflow/Search tab switcher (`.lp-tab`/`.lp-tab-active`). |
| `livewire.landing-page.workflow` (`App\Livewire\LandingPage\Workflow`) | 4-step horizontal stepper over the operational pipeline. See "Livewire components" below. |
| `livewire.landing-page.workspace` (`App\Livewire\LandingPage\Workspace`) | Module-pinning + record-pinning accordions (built on `<x-accordion-header>`/`<x-empty-state>`/`<x-loading-skeleton>`). See below. |
| `livewire.landing-page.search` (`App\Livewire\LandingPage\Search`) | Spotlight search, breadcrumb pipeline-stage bar, result list/detail. Uses `<x-loading-skeleton>`/`<x-kbd>`. See below. |

## Shared components (`resources/views/components/`)

| Component | Props / slots | Used by |
|---|---|---|
| `<x-modal open="boolVar">` | `open` (Alpine boolean variable name, interpolated raw into `x-show`/`:class`/`x-on:click`), `width` (default `3xl`), optional `<x-slot:heading>` / `<x-slot:description>` (pass the full tag yourself, e.g. `<h2 class="fi-modal-heading" x-text="...">`, so reactive `x-text`/`x-show` bindings stay exactly as authored), default slot = body. **Not** Filament's own `<x-filament::modal>` — that manages open/close via its own event-dispatch convention (`$dispatch('open-modal', {id})`), a different interaction model, deliberately not adopted here. | `livewire/landing-page/workflow.blade.php`'s text + video dialogs. |
| `<x-loading-skeleton muted?>` | `muted` (bool, lighter shade for a secondary line). A single pulsing bar/box primitive, not a full skeleton "shape" — compose multiple instances + your own wrapper (`animate-pulse` stays on the ancestor). | `workspace.blade.php` record-picker rows, `search.blade.php` result cards and chain-loading bars — three different layouts (row/card/stacked block), so the primitive stays unopinionated rather than branching internally. |
| `<x-accordion-header open="boolVar" icon="heroicon-o-x" title="..." count="jsExpr" countLabel="...">` | `open` (toggle variable name), `icon` (heroicon component name, via `<x-dynamic-component>`), `title`, optional `count` (Alpine expression string) + `countLabel`, default slot = panel body (already wrapped in `lp-panel`/`p-3 sm:p-4` body chrome). | `workspace.blade.php`'s Modules and Records accordions — both now consistently animate via `x-collapse`. |
| `<x-icon-button tooltip="...">` | `tooltip` (optional static string → sets `x-data` + `x-tooltip.raw` itself). Everything else — `wire:click`, `x-on:click`, or a fully custom reactive `x-data`/`x-effect`-driven tooltip — passes through via plain Blade attribute passthrough; omit `tooltip` when supplying your own `x-tooltip.raw` this way. | `filament/partials/nav-dock-toggle.blade.php` (pure Alpine), `livewire/calendar-toggle.blade.php` (Livewire `wire:click`), plus the topbar-pin toggle. |
| `<x-empty-state icon="heroicon-o-x" hint="..." size="md|lg" hint2="..." ctaLabel="..." ctaAction="jsExpr">` | `size="lg"` = "no results" variant (bigger icon box, `text-sm` hint, no CTA, optional `hint2` second line); `size="md"` (default) = "nothing pinned yet" shape with a CTA. | `workspace.blade.php`'s 3 "nothing here" blocks (no pinned modules, no pinned records, no picker search results). |
| `<x-kbd>⌘K</x-kbd>` | Default slot only. | The keyboard-shortcut hint in `search.blade.php`. |

Deliberately not extracted (judged not meaningful reuse): the workspace/search retry-after-error banners (same concept, different enough layout to not be worth branching); the search progress-ring SVG (clean markup extract, but its supporting Alpine circumference math lives in `alpine/components/search.js` and would need consolidating too — a good small follow-up, not done here); search's label:value inline chips (rendered inside Alpine `x-for` loops — a Blade component's "props" would have to be literal Alpine-expression strings, more confusing than the duplication); workspace's corner action icon-button (already DRY'd at the PHP-variable level via its own `$ui` array).

## Livewire components

Each tab body is its own Livewire component under `App\Livewire\LandingPage\*`, rendered from the root view via `@livewire('landing-page.{name}', [...])` inside the same `x-show="activeTab === '...'"` wrapper divs. All three are **eager, render-only**: none uses `wire:model`/`wire:click`/`#[Lazy]` — each mounts once and never re-renders. Every pixel of interactivity (tips rotation, audio, modals, debounced search-as-you-type, `localStorage` pins) is untouched Alpine, still hitting the same HTTP endpoints as before. PHP prepares data once per tab class; Alpine owns 100% of the interaction.

| Component | View | Mount params | Owns |
|---|---|---|---|
| `Workflow` | `resources/views/livewire/landing-page/workflow.blade.php` (+ nested `workflow/footer.blade.php`) | `counts`, `isRtl` | `$stats` normalization; the desk-reference `insightGroups` build, cached via `Cache::remember("desk_reference_insight_groups:{locale}", 1 hour, ...)` — safe to cache since the resolved array has no closures (unlike `SearchService`, see below). Uses `<x-modal>` for its text/video dialogs. `alpine/components/workflow.js` untouched. |
| `Workspace` | `resources/views/livewire/landing-page/workspace.blade.php` | `counts` | The 15-module list + icon/theme/badge prep + `$workspaceConfig` JSON blob. Record-search-as-you-type stays in `alpine/components/workspace.js`'s `fetch()` against `GET /workspace/records/{resource}` — unchanged. `localStorage['user_shortcuts']` pins stay 100% client-side. |
| `Search` | `resources/views/livewire/landing-page/search.blade.php` | `isRtl` | Five static color-lookup tables + icon-name list as class constants. Spotlight/chain fetching stays in `alpine/components/search.js`'s `axios.get()` calls — unchanged, same 500ms debounce, same `tab-search-focus` wiring. |

**Why no `wire:model`/reactive properties**: converting the interactive fetch loops into native Livewire reactivity was evaluated and rejected. `wire:model.live.debounce.500ms` delays the *whole* property (including hint text that today updates instantly via `x-model`), `wire:loading` doesn't map 1:1 onto the existing `isSearching` boolean, and every keystroke would round-trip a full component snapshot instead of a lean JSON GET. Don't retry this — it changes behavior, not just wiring.

**Why no `#[Lazy]`**: Livewire's `#[Lazy]` defaults to `x-intersect` (viewport-based), not `on-load`. These tab panels sit inside `x-show`-hidden (`display:none`) divs, which never intersect — naive lazy-loading would show a skeleton on every first tab click. If ever needed, use `<livewire:landing-page.search lazy="on-load" />` explicitly (not the bare `#[Lazy]` attribute), reusing the tab's own loading-skeleton (`<x-loading-skeleton>`) as the `placeholder()` view.

**Dual-Alpine caveat (read before adding `wire:model`/`wire:click` to any of these)**: `resources/js/alpine/loader.js` imports the raw npm `alpinejs` package and calls its own `.start()`, separate from Livewire's own bundled Alpine (`@livewireScripts`). This is a known footgun that bites when Livewire performs a *post-mount DOM morph* needing to initialize a *custom* Alpine factory in newly-injected markup — none of the three components trigger this today (no reactivity), so it doesn't block anything currently shipped. Before adding any, check devtools for "Detected multiple instances of Alpine"; if present, switch `alpine/loader.js` to import Alpine from Livewire's bundled ESM export instead. A global `app.js` change — treat as its own reviewed change.

**Caching note — what's *not* cached and why**: `SearchService::registry()`/`::chainMeta()` rebuild sizeable arrays via repeated `__()` calls on every request, but both contain PHP closures (`url`, `title`, `fetch`, `details` callbacks) — closures cannot be serialized, so `Cache::remember()` around them throws on any real cache driver. Correctly caching them means restructuring `SearchService` to separate cacheable label data from behavior closures. Left uncached; a genuine future opportunity, not a bug.

## Boot sequence: before load → after login

1. **Before login**: Filament's own login page (`CustomLogin`) — unrelated, no loader/landing-page markup yet.
2. **Right after login**, Filament redirects into `LandingPage`. Its view renders `@include('filament.landing-page.loader')` first, inside the root `x-data="landingPage()"` div, before the rest of the page (`x-data="{ appReady: false }"`, `x-init="setTimeout(() => appReady = true, 2900)"`) becomes visible.
3. **The loader** checks `sessionStorage.getItem('bms_loaded')`:
   - Not set (first load this session) → full-screen overlay for **2900ms**, then sets the flag and fades out.
   - Already set → loader never renders, but `appReady`'s own 2900ms timer still runs on its own schedule.
   - This is why the loader "disappears" after the first visit per session — by design. A new tab/incognito/`sessionStorage.removeItem('bms_loaded')` resets it.
4. **After 2900ms** (or immediately on repeat visits), `appReady` flips `true` and the page fades in over 700ms.

## Extending this page

- New card/panel → `class="lp-surface"` (+ `lp-surface-hover` if clickable), never hardcode `bg-white dark:bg-zinc-900`.
- New accent color → `text-primary-600 dark:text-primary-400` (or `bg-`/`border-` equivalents), never a hardcoded hue.
- New category needing a color → pick from the 5-tone semantic palette, matching by literal Tailwind family.
- New internal divider → `border lp-divider`, not a hardcoded border color.
- New modal → `<x-modal open="...">`, not a hand-rolled `fi-modal` shell.
- New loading placeholder → `<x-loading-skeleton>` bars inside your own layout wrapper (keep `animate-pulse` on the ancestor), not raw `bg-slate-200 dark:bg-white/10` divs.
- New collapsible section → `<x-accordion-header>`, not a hand-rolled button+chevron+`x-collapse` block.
- New "nothing here yet" block → `<x-empty-state>`, not a hand-rolled icon-box+hint block.
- New small icon-only button (toolbar/toggle) → `<x-icon-button>`, not the hand-copied `h-9 w-9 rounded-lg` chrome.
- New tab data-prep logic → the tab's own `App\Livewire\LandingPage\*` class (`mount()`/a private builder method), not a Blade `@php` block. New tab *interactivity* (client-only, no server round-trip) → the tab's existing Alpine factory in `resources/js/`, not the Livewire component.
- New nested partial for a tab's view → a subfolder under that tab's own `resources/views/livewire/landing-page/{tab}/` directory (see `workflow/footer.blade.php`), not a loose file in `resources/views/filament/landing-page/` (shell chrome only).
