# BMS-CM CSS Design Pattern

Verified against source on branch `master` (2026-07-25). Authoritative reference for the CSS token system, the `.fi-*` morphing overrides, and the landing-page design system.

## Core idea

BMS-CM has **two coexisting CSS systems sharing one token layer**:

- **(a) `fi-custom.css`** — the Filament panel morphing layer. Overrides Filament's internal `.fi-*` classes via a token bridge (`--custom-*`, `--google-*`, `--gradient-*`) declared in `:root`.
- **(b) `landing-page.css`** — a flat, data-dense, enterprise landing-page design system (rewritten away from glassmorphism/3D in the 2026-07-18 redesign).

**After editing `fi-custom.css`, run `php artisan filament:assets` — not just wait for Vite HMR.** `fi-custom.css` is registered in `app/Configurators/FilamentAssets.php` via `Css::make()`; Filament copies its own registered assets into `public/css/`/`public/js/` and serves THAT copy, a step Vite's dev-server hot-reload does not trigger. An edit with no `filament:assets` run after it is invisible in the browser no matter how many times the page is refreshed or the dev server restarted — reproduced live 2026-09-22, this was the actual cause of a CSS fix appearing to "not work."

A theme change in `fi-custom.css :root` re-themes both systems. Do not fork the token layer, do not introduce a third system, and do not hardcode a color that already has a `--custom-*` / `--google-*` variable.

The one sanctioned way to re-theme at runtime is `themes.css` (§1b): it overrides the same tokens per `html[data-theme="*"]` — the token layer stays single.

## Directory & pipeline

```
resources/css/
├── app.css                  ← Tailwind base (scans resources/views)
├── fi-custom.css            ← Filament panel morph layer + :root tokens (load-bearing, 1478 lines)
├── themes.css               ← Palette override layer: html[data-theme="*"] blocks (see §1b)
├── layout/
│   └── fonts.css            ← @font-face: Roboto (woff2/woff), IranYekan (woff/ttf), Baloo 2 ExtraBold 800-only (woff2, brand wordmark)
└── landing-page.css         ← Landing page design system (flat, 928 lines)
```

See `resources/js/scriptPattern.md`'s "Directory & entry pipeline" section for entry order and static-copy targets.

### Dark-mode variant strategy

`app.css` and `fi-custom.css` are two **independent** Tailwind v4 build entries. Tailwind v4's `dark:` variant defaults to `@media (prefers-color-scheme: dark)` unless overridden — both entries declare `@custom-variant dark (&:where(.dark, .dark *));` right after their `tailwindcss` import, so `dark:` utilities follow the app's `html.dark` class (toggled by `landingPage()`'s `darkMode` watcher / Filament's theme toggle), not the OS preference. Any **new** Tailwind entry point must declare this too, or its `dark:` classes will silently ignore the in-app toggle.

### Dead code — do not reintroduce

`resources/js/3d.min.js` and `resources/js/landing-page.js` were deleted in the 2026-07-18 redesign (Three.js torus/particle background). Zero references remain anywhere. Do not re-add them to `vite.config.js`.

---

## 1. `fi-custom.css :root` token layer

Single source of truth for color, motion, and elevation. Both CSS systems consume these tokens; the landing page only adds `--font-*` tokens of its own (see §5).

### Core surface palette

```css
--custom-first:  #F8FAFC;   /* lightest surface */
--custom-second: #D9EAFD;
--custom-third:  #BCCCDC;
--custom-fourth: #9AA6B2;   /* muted text */
--custom-neutral:#E8E8E8;
--filament-dark: #09090B;   /* dark bg */
```

### Mid-alpha (0.7) / Light-alpha (0.4) variants

```css
--custom-second-mid / --custom-third-mid / --custom-fourth-mid                          /* color-mix(in srgb, var(--custom-N) 70%, transparent) */
--custom-second-light / --custom-third-light / --custom-fourth-light                    /* … 40% … */
--custom-neutral-light                                                                  /* … 50% … */
```

These are **`color-mix()` compositions of the solids, not independent literals** (refactored from `rgb(..., 0.7)` literals 2026-09-22) — so a `themes.css` block that overrides only the solid `--custom-*` tokens automatically recomposes every variant. `--filament-dark-mid` remains a solid hex on purpose (it is a dark surface, not an alpha).

### §1b Theme palettes — `themes.css`

Runtime palette switching lives entirely in `themes.css`: one light block + one `.dark` sibling per palette:

```css
html[data-theme="purple"] { --primary-50…950; --custom-first/second/third/fourth; --custom-neutral;
                            --filament-dark; --filament-dark-mid; --google-fourth-light; --google-fourth-dark; }
html[data-theme="purple"].dark { --primary-400/500; --google-first/second/third-dark;
                                 --gray-800/900/950; }
```

- **Palette keys are single words** (`slate`/`indigo`/`zinc`/`steel`/`purple`/`olive` — user rule, matches the one-word-filename rule; config order = picker order, with `indigo` deliberately right after `slate` and `steel` right after `zinc`): the key doubles as the `data-theme` value, the `config/palettes.php` key, and the `theme_palette.palettes.*` lang key — three-way identity, no mapping. (The FA label for `slate` is deliberately «آبی», not «سربی» — user's own wording, 2026-09-22.)
- **`slate` (the `:root` identity) has NO block** — `data-theme="slate"` falls through to `:root`.
- Specificity `html[data-theme="x"]` (0,1,1) beats Filament's plain `:root` `<style>` tag (0,1,0) for `--primary-*`/`--gray-*` — no `!important`, no order dependency. `--gradient-*` recompose for free (they're `var()` compositions).
- The `.dark` blocks also override `--gray-*` because Filament's dark card/input surfaces consume zinc grays directly, not `--custom-*` — without this, tinted dark bodies sit under zinc cards.
- **`data-theme` is set pre-paint** by an inline script in `filament/partials/meta.blade.php` (also exports `window.BMS_THEMES`), reading `localStorage['theme_palette']` — Vite JS is deferred, only the inline head script prevents a first-paint flash.
- **Canonical palette list: `config/palettes.php`** (keys + picker dot hexes). Adding a palette = one `themes.css` block + one config entry + one `theme_palette.palettes.*` label per locale (`lang/{en,fa,fr}/resources/general/strings.php`) — `tests/Feature/Config/ConfigTest.php` fails on drift.
- Picker partial: `filament/partials/theme.blade.php` (`$surface`: `panel` | `lp` | `row`), topbar (`GLOBAL_SEARCH_AFTER`) + landing switchers + table view-preferences row. Switching calls `window.setTheme(key)` (`resources/js/filament/theme.js`), which validates against `window.BMS_THEMES`.
- `olive` (renamed from `emerald` by the user 2026-09-22) resolved the old emerald≈`success` collision — semantic status greens now stay clearly distinct from the olive brand ramp. (`corporate-blue` was removed 2026-09-22 on user call — read too close to the default slate/indigo look.)
- **The whole palette set was hand-tuned by the user 2026-09-22** — every ramp/surface/accent hex in `themes.css` is their own professionalized version (each palette pairs a deep, desaturated primary ramp with a vivid complementary `--google-fourth-*` accent: zinc→sky, purple→lime, indigo→amber, olive→orange). `gray` was dropped from the list on their call — the tuned zinc already covers the neutral slot, so do not re-add a gray entry. (`steel`, added 2026-10-10 on the user's call, is the sanctioned second neutral: a true-neutral ramp seeded on their own #52525B/#D8D8D8 anchors — zinc stays the blue-tinted neutral, steel the pure one — with rose as its accent. On the same call zinc's blue cast was deepened from the subtle #555962 ramp to a clearly steel-blue #535F7A ramp so the two themes don't read as near-identical; zinc's old near-white surfaces were the collision.)
- **Theme light backgrounds are washed with `--google-second-light`** — `.fi-body`'s radial glow (fi-custom.css "App Background") consumes it, so each theme block overrides it with a hue matched to its `--custom-second` family; without this, every theme's background wash stays the neutral gray of the base `:root` and looks off-harmony.
- Picker dots are presentation, not theme samples: `slate`'s dot is a true blue (`#2E6BC6`) to match its FA label «آبی», even though the slate block itself stays the `:root` identity.

### Google Material palette (light / dark)

```css
--google-first-light:  #FFFFFF;   --google-first-dark:  #1E1E1E;
--google-second-light: #E1E4E8;   --google-second-dark: #2C2B2F;
--google-third-light:  #C1C6CC;   --google-third-dark:  #49454F;
--google-fourth-light: #5C6AC4;   --google-fourth-dark: #6750A4;
```

### Named gradients (15)

All `linear-gradient(135deg, A, B)`; only the two stops vary:

| Name | Stop A | Stop B |
|---|---|---|
| `--gradient-primary` | `--custom-first` | `--custom-second` |
| `--gradient-secondary` | `--custom-second` | `--custom-third` |
| `--gradient-accent` | `--custom-third` | `--custom-fourth` |
| `--gradient-dark` | `--custom-fourth` | `--filament-dark` |
| `--gradient-neutral` | `--custom-neutral` | `--custom-second-mid` |
| `--gradient-deep` | `--filament-dark` | `--filament-dark-mid` |
| `--gradient-light` | `--custom-second-light` | `--custom-third-light` |
| `--gradient-soft` | `--custom-fourth-light` | `--custom-neutral-light` |
| `--gradient-google-light` | `--google-first-light` | `--google-second-light` |
| `--gradient-google-accent` | `--google-third-light` | `--google-fourth-light` |
| `--gradient-google-dark` | `--google-first-dark` | `--google-second-dark` |
| `--gradient-google-deep` | `--google-third-dark` | `--google-fourth-dark` |
| `--gradient-contrast` | `--custom-first` | `--custom-fourth` |
| `--gradient-brand` | `--google-fourth-light` | `--google-fourth-dark` |
| `--gradient-hero` | `--custom-second` | `--filament-dark` |

### Motion & elevation

```css
--md-motion:      cubic-bezier(0.2, 0, 0, 1);   /* enter / standard */
--md-motion-exit: cubic-bezier(0.4, 0, 1, 1);   /* exit */

/* each elevation is a TWO-layer shadow (ambient + key) */
--md-elevation-1/2/3        /* light */
--md-elevation-1/2/3-dark   /* dark */
```

---

## 2. `.fi-*` morphing declarations

Every override goes through a `--custom-*` / `--google-*` / `--gradient-*` token — never a hardcoded hex. Selectors below are searchable class names, not line numbers (the file is 1478 lines and reflows often).

| Selector | Behavior | Non-obvious gotcha |
|---|---|---|
| `.fi-body` | Body bg: `--custom-first` + radial gradient light, `--filament-dark` + radial dark. Light-only `gradient-shift` animation, gated behind `prefers-reduced-motion: no-preference`. | Dark glow uses `--custom-third-mid`, not a repeat of the light `--google-second-light` value. |
| `.fi-topbar` | `--custom-third-mid` bg, `max-height: 20px`, `slide-in-top` entrance. | — |
| `.fi-topbar-close-collapse-sidebar-btn` | The sidebar-collapse chevron Filament ships next to the topbar logo; pinned `position: absolute; inset-inline-start: calc(16rem - 2rem)` at ≥1024px, `static` below. | **Must use logical (`inset-inline-start`), never physical `right:`/`left:`** — a physical `right:` renders correctly in fa (RTL) but parks the button on the far side in en/fr. Any panel-side positioning must be logical so locale flips it. |
| `.fi-sidebar-item > a` | `--gradient-neutral` bg; hover → `translateX(3px)` + `--custom-neutral` + elevation-1. | Dark mode drops the gradient for a flat `--filament-dark-mid`. |
| `.fi-sidebar-group-label` | Nav group headings (e.g. "【1】 PR Mangs."). `white-space:nowrap` + `overflow:hidden` + `text-overflow:ellipsis` keeps every label on one line regardless of length (fa's longer strings included) — added 2026-09-26. On `:hover`, `text-overflow` switches to `clip` and a `text-indent` keyframe animation (`sidebar-label-ticker`, 6s linear infinite) scrolls the full text into view like a ticker, so truncation never actually hides content, just defers it to hover. The reset is a snap, not a visible reverse-slide — `92% → 92.01%` jumps `text-indent` back to `0` over a near-zero fraction of the cycle, so it reads as "text loops back to start" rather than "text slides backward" (a true wrap-to-the-other-side loop needs duplicate text content, which would mean overriding Filament's own sidebar-label markup — deliberately not done, this single-element snap is the safe equivalent). Font stays at the original `0.6875rem`/`600`/`0.06em` (a first pass shrank these for legibility-neutral wrapping relief, but the hover-ticker made that trade-off unnecessary — reverted once the real fix landed). | `text-indent` (not `transform:translateX`) is what makes this RTL-correct for free — it's direction-aware or logical-inline-start by spec, so the fa sidebar's ticker scrolls the correct way without a separate RTL override, unlike a fixed-px `transform` would need. `-100%` is a deliberate overshoot (relative to the label's own width, not the true overflow amount) — for the realistic overflow ratios here it reliably reveals the full string; don't "fix" it to a precise value without a real reason, the imprecision is harmless in this direction. `prefers-reduced-motion: reduce` disables the animation entirely, ellipsis truncation still applies. |
| `.fi-sidebar-item.fi-active > a::before` | 🔹 (U+1F539, not 🔵) pulsing indicator dot. | — |
| Collapsed icon rail block (added 2026-10-07) | `@media (min-width: 1024px)` + `.fi-body-has-sidebar-collapsible-on-desktop .fi-sidebar:not(.fi-sidebar-open)` scope only: `.fi-sidebar-nav` gets symmetric `padding-inline: 0.375rem !important`; `.fi-sidebar-nav-groups` gets `margin-inline: auto` (the logical `mx-auto` — vendor's collapsed `lg:w-max` shrinks the group column to icon width and block flow parks it at the inline-start edge, so without this it hugs the browser border); `.fi-sidebar-item` padding drops to `0.125rem`; item hover's `translateX(3px)` is disabled (`transform: none`); the active `::before` swaps its glyph for `content: ''` — a 3px × 1.25rem pill at `inset-inline-start: 0.125rem`, vertically centered via `top: calc(50% - 0.625rem)`. | Vendor's own collapsed styles keep the open-sidebar `px-6` start padding and force `lg:pe-0`, so in the 4.5rem rail the icons were pushed into the `scrollbar-gutter: stable` reserve — the scrollbar drew on top of them and the glyph's `top: -7px` poked over the icon above. The `!important` on `padding-inline` is required: vendor's `lg:pe-0` rule has higher specificity than the override. The centering is dynamic (auto margins inside the nav's content box, which excludes the stable scrollbar gutter), not a fixed offset — it stays centered whatever the rail width or scrollbar state. In the `::before` rule the physical `right: auto` must be declared **before** `inset-inline-start` — same-rule cascade order lets the logical property win the shared `right` slot in fa/RTL while `right` clears for LTR. Open-sidebar styling is deliberately untouched — every selector is gated on `:not(.fi-sidebar-open)`. **Every selector also carries an `html:not(.nav-dock-bottom)` prefix (added 2026-10-07): the dock modes close the sidebar store too, so `:not(.fi-sidebar-open)` matches in the bottom dock and peek as well — without the prefix these rules bleed into the docked bar's row layout (peek carries `.nav-dock-bottom` alongside `.nav-dock-peek`, so the one exclusion covers both docked states).** |
| `.fi-section` | `--custom-third-light` bg + elevation-1; hover → elevation-2. | Hover elevation is suppressed inside modals and widgets (separate override rule). |
| `.fi-ta` (tables) | Border-none + elevation-1. Header row bg lives on `.fi-ta-header-ctn` (`--custom-third-light`), the `<th>` text row is `thead > tr th` using the *solid* `--custom-third` (not `-light`), pagination is `nav.fi-ta-pagination` (`--custom-second-light`). | There is no `.fi-ta thead th` selector — it's the unscoped `thead > tr th`. |
| `html.table-density-compact` (row-density toggle, added 2026-09-25; tightened, root-caused, and re-loosened slightly all on 2026-09-27 — see history below) | Final values: `thead > tr th { padding-block: 0.25rem !important; padding-inline: 0.5rem !important }`; `.fi-ta-cell`, `.fi-ta-cell-content`, and `.fi-ta-cell-label` all `padding-block: 0 !important`; `.fi-ta-col` (the row's click-to-view `<button>`) `padding-block: 0.1875rem !important; padding-inline: 0.5rem !important; min-height: 0 !important`; `.fi-ta-col > *` and `.fi-ta-text-item` both `padding-block: 0 !important` (`.fi-ta-text-item` also `line-height: 1.25 !important`). `.fi-ta-cell-label` is the stacked-mobile-only row label (rendered when the topbar's classic↔stacked table toggle is in "stacked" mode) — it carries its own `pt-2` independent of the desktop cell chain, so it needed its own zeroing to keep classic and stacked density consistent. All body-row spacing now lives on the single `.fi-ta-col` rule (the deliberately "generous" knob); every other layer is zeroed so there's exactly one place to retune density from. | **The original selector (`.fi-ta-cell > .fi-ta-col > *`, a direct-child chain) never matched real body rows — confirmed 2026-09-27 from the actual rendered `<tr>` markup.** The true DOM shape is `<td class="fi-ta-cell"><div class="fi-ta-cell-label">…</div><div class="fi-ta-cell-content"><button class="fi-ta-col"><div class="fi-ta-text-item …">…</div></button></div></td>` — `.fi-ta-col` sits two levels inside `.fi-ta-cell` (via `.fi-ta-cell-content`), not as a direct child. A first fix switched to descendant combinators and still wasn't enough on its own — the padding was distributed across more than one layer at once, so the working fix zeroes every layer in the chain rather than hunting for the single "correct" one; redundant coverage on a `!important` toggle-only rule set is cheap. Deliberately does NOT touch the selection-checkbox/row-actions `.fi-ta-cell`s' own comfortable spacing — not a bug, just unscoped. |
| `.fi-ta-header-ctn` sticky-on-scroll (added 2026-09-25) | `position: sticky; top: 20px; z-index: 1`, scoped `@media (min-width: 1024px)` only. `top: 20px` matches this same file's `.fi-topbar { max-height: 20px !important }` — when the topbar is pinned (vendor default `position: sticky`), both compete for the same viewport row unless offset; when unpinned (this project's default — `position: fixed`, auto-hiding to a 14px sliver), the topbar reserves no flow space and the 20px gap is a harmless, non-dynamic constant rather than a JS-computed one. | Verified clean: `.fi-ta-ctn` only gets `overflow: hidden` (a real sticky-breaking ancestor) when `:not(.fi-ta-ctn-with-header)` — i.e. a table with no search/filters/header-actions/column-manager. Every real List/Manage page table in this app has at least global search, so it always carries `.fi-ta-ctn-with-header` and never hits the clip. A header-less RelationManager table (rare) would still clip — not fixed here, flagged as the one known edge case. No `transform` sits between `.fi-ta-header-ctn` and the document scroll root (`.fi-layout`'s own `overflow-x-clip` is x-axis only); the topbar's own `transform`/`position:fixed` rules live on `.fi-topbar-ctn`, a sibling of `.fi-layout`, not an ancestor of the table — doesn't interfere. |
| `html.table-stacking-classic` (mobile-stack opt-out, added 2026-09-26) | The Alpine stacked-table toggle's CSS lever (see `filamentPattern.md` §1.2 / `scriptPattern.md`): re-flips vendor's `block sm:table` stacked rendering back to a real table while the class is on `<html>` — `> tbody` → `table-row-group` + `white-space: nowrap`, `> tbody > tr` → `table-row` + `position: static` + zero padding, selection/body cells → `table-cell` + `static`, `.fi-ta-cell-label` hidden, `> tfoot` → `table-footer-group` and `> tfoot > tr` → `table-row` (all `!important` — the block loads after vendor's compiled CSS). | `> tfoot > tr` MUST be `table-row`, never `table-footer-group` — the latter is only valid on the `tfoot` itself; sharing one declaration across both selectors (the first-cut bug, fixed same day) mis-boxes summary rows. Known unfixed edge: rows set `position: static` but vendor's `.fi-selected::before` absolute selection bar isn't neutralized, so it floats against the nearest positioned ancestor on mobile — cosmetic, flagged not fixed. |
| `.fi-modal-window` | `--custom-neutral` bg, `border-radius: 16px`, elevation-3. Backdrop is `.fi-modal-window-ctn` (NOT `.fi-modal-overlay`), theme-tinted since 2026-09-22: `color-mix(in srgb, var(--primary-200) 85%, transparent)` light / `color-mix(in srgb, var(--filament-dark) 90%, transparent)` dark — each palette washes the scrim in its own hue; do not revert to the old hardcoded `rgba` values. | When `.fi-modal-open`, sidebar + topbar get `opacity:0; pointer-events:none` via `body:has(.fi-modal-open) :is(nav.fi-topbar, .fi-sidebar-nav, aside.fi-sidebar...)`; scrollbars hidden too. `:has(.fi-in-entry) .fi-modal-content` variant (added 2026-09-22) floors View-action/infolist modals' content area at `min-height: min(75vh, 650px) !important`, so switching infolist tabs never visibly shrinks the modal. **Scope this to `.fi-modal-content` only, never `.fi-modal-window`** — the window is one flex column holding header/content/footer as siblings; forcing `min-height` onto the whole window fights Filament's own JS-computed `max-height` and pushes the non-sticky footer (the Close button) outside the visible clipped area — reproduced live, footer rendered detached below the modal. `!important` is needed either way because this file loads after Filament's own compiled CSS. |
| `.fi-tabs` / `.fi-tabs-item` | Flex row, `overflow-x:auto`, thin custom scrollbar. Item color `--custom-fourth`; active → `--gradient-secondary` light / `--gradient-google-deep` dark. | — |
| `.tb-badge` (backs `tabBadge()` helper) | `.tb-info/-success/-warning/-danger` — solid pale-tint background (not an alpha of the accent), `border-color` carries the alpha channel. | `success`/`warning` use a *different* RGB triplet for border vs. text, not a reused one — check source before assuming symmetry. |
| `.tb-menu` family (added 2026-09-26) | The topbar's two popover menus (Quick-create / Recent-records, from `filament/partials/work-actions.blade.php`): `.tb-menu` (surface: `--google-first-light/dark` + `--md-elevation-3(-dark)`), `.tb-menu-group` (uppercase group header, `--custom-fourth` light / `--google-third-light` dark), `.tb-menu-item` (row + hover), `.tb-menu-scroll` (max-height 20rem + its own scoped `::-webkit-scrollbar` triplet with the mandated `opacity: 0 !important` / `:hover opacity: 1 !important` reveal — see Custom scrollbar row), `.tb-menu-footer` (Clear button row), `.tb-chip` (the resource-group pill on each recents entry). | A pill/divider/popover consolidation of the six toggles was built and reverted on user call 2026-09-26 — `.tb-cluster`/`.tb-divider`/`.tb-pref-*` are gone, do not reintroduce; the six toggles stay side-by-side icon buttons. |
| `.fi-topbar-end` logical order (added 2026-09-26, row-click-toggle tied in 2026-10-05) | Pure flex `order` on the vendor cluster's direct children plus inline `style="order: N"` on each toggle partial's root. Start→end: fullscreen 1, **row-click-toggle 2 (tied with table-state, registered first so it renders first among the tie)**, table-state (memory) 2, stacked-table 3, density 4, dock 5, pin 6, theme 7, work-actions 8 (hairline divider + create + recents inside), language 9, calendar 10, bell 11, search-bell divider 12 (`filament/partials/topbar-divider.blade.php`), search 13, account 14 — equivalently from the end: account, search, divider, bell, calendar, language, history, create, divider, theme, pin, dock, density, stacked-table, table-state (memory), row-click-toggle (tied), fullscreen (`order` is direction-agnostic so one sequence serves LTR and RTL). Two same-value `order` items are valid CSS — the browser falls back to source/registration order between them, which is why `row-click-toggle`'s render hook is registered in `FilamentRenderHooks.php` just BEFORE `table-state-toggle`'s own registration call, placing it first within the tied pair without renumbering any other file (a first attempt, same day, inserted a dedicated slot at 2 and bumped 12 other files by one — reverted along with that attempt; this tie is the lower-effort alternative that approach's own retrospective note suggested). Every toggle self-hides below `lg` via its own `hidden … lg:flex` classes (including the language wrapper and the published vendor blade that carries its inline `order: 9`) — there is no `@media` order/hide rule left in this file; from `sm` up the search ctn is widened to `24rem` (`width` on `.fi-topbar .fi-global-search-ctn`) to match the results dropdown (`sm:w-screen sm:max-w-sm` — the dropdown's end edge anchors to the search box, so equal widths line them up exactly). | No vendor view is published/overridden except the language-switch trigger — web research + vendor source confirmed there is NO render hook between the bell and the user menu, so `order` is the minimal lever; deleting the block restores vendor order instantly. The bell's flex item (direct child of `.fi-topbar-end`) is the `DatabaseNotifications` Livewire component's root `div.fi-no-database` (from `filament-notifications::database-notifications`) — the `-btn` class sits on a deeply nested button and `fi-modal-trigger` on a nested div, neither is the flex item; the order rule therefore targets `.fi-no-database`. The component is also rendered NON-lazy via `->databaseNotifications(isLazy: false)` in `DashboardPanelProvider` — its lazy placeholder root is a classless div that must never get an order rule (an earlier `div:not([class])` selector for the language switcher was deleted once the plugin wrapper gained classes; do not reintroduce a classless-div selector — re-verify which node is the flex item if the markup ever changes). Theme partial gotcha: an inline attr built as `{{ $isPanel ? 'style="…"' : '' }}` gets the quotes HTML-escaped by Blade (`style="&quot;…&quot;"` → invalid, silently ignored) — build it as `style="{{ $isPanel ? 'order: 7' : '' }}"` with the quotes outside the expression. |
| `.language-switch-trigger` (added 2026-09-26) | Restyles the language-switch plugin's trigger to match the topbar's `x-icon-button` family — same 36px box (plugin's own `w-9 h-9 rounded-lg` kept), plugin's flag look dropped in favor of its text mode (`EN`/`FA`/`FR` char avatar): ring/box-shadow killed, `bg-primary-500/10` → transparent with the icon-buttons' gray-50 hover, compact bold 12px text. | Enabled by removing `->flags()`/`->flagsOnly()` from `app/Configurators/LanguageSwitcher.php` (the plugin renders its flag markup whenever `$isFlagsOnly || $hasFlags` — either one forces flags). The flag SVG *files* under `resources/img/flags/` are deliberately kept (landing-page switchers still use `img/flags/*` via their own markup). |
| `.user-row-inactive` (added 2026-10-08) | The only table row-class hook in the project: `UserResource::table()` returns it from `->recordClasses()` when `status` is Inactive; `tbody .fi-ta-row.user-row-inactive td` dims to `opacity: .55` and returns to `1` on row `:hover`. Opacity only, no color token needed. | Reuse this shape (a `->recordClasses()` closure + one scoped rule in `fi-custom.css`) for any future "muted row" state; run `php artisan filament:assets` after editing, as for any `fi-custom.css` change. |
| Custom scrollbar | 8px wide; track `--custom-second` (dark `--google-first-dark`); thumb `--custom-third` (dark `--google-third-dark`); inline SVG arrow buttons. Hidden-until-hover since 2026-09-25: the bare `::-webkit-scrollbar-thumb` opacity defaults to `0`, revealed via `body:hover ::-webkit-scrollbar-thumb{opacity:1}` (track/buttons stay always-visible — only the thumb toggles); `html` carries Firefox's `scrollbar-width:thin` + `scrollbar-color:transparent transparent`, revealed the same way via `html:hover`/`.dark:hover`. **Self-contained scroll boxes get their own precisely-scoped copy of this same pattern instead of relying on the global rule** — `.fi-tabs`, `.landing-page.css`'s `.custom-scrollbar`, and `.fi-in-repeatable-item` (see `.fi-in-repeatable-spaced` row) each declare their own `::-webkit-scrollbar`/`::-webkit-scrollbar-thumb`/`:hover` triplet scoped to themselves (e.g. `.fi-tabs:hover::-webkit-scrollbar-thumb`, `.fi-in-repeatable-item:hover::-webkit-scrollbar-thumb`), never inheriting the bare global selector — user-corrected 2026-09-25 after the first pass left `.fi-in-repeatable-item` on the global `body:hover` fallback, which reveals on any mouse movement anywhere in a modal, not specifically over the scrollable item row. **The global bare-selector rule is reserved for the actual page-level/default-scrolling elements it was written for (main content area, sidebar) — any NEW self-contained scroll box must get its own scoped copy from the start, never left to "inherit for free."** **A single-class own selector (`.fi-tabs::-webkit-scrollbar-thumb`, `.custom-scrollbar::-webkit-scrollbar-thumb`) has LOWER specificity than `body:hover ::-webkit-scrollbar-thumb` — the global rule's extra type selector (`body`) plus pseudo-class (`:hover`) outweighs one class, so without `!important` on both the container's base `opacity:0` and its own `:hover` `opacity:1`, the global rule silently wins the moment the mouse is anywhere on the page and the "scoped" reveal never actually isolates itself. Both now carry `opacity: 0 !important` / `opacity: 1 !important` for this reason (fixed 2026-09-25). `.fi-in-repeatable-item`'s two-class compound selector (`.fi-in-repeatable-spaced .fi-in-repeatable-item::-webkit-scrollbar-thumb`) is naturally specific enough to win outright and needs no `!important`.** | Reduced-motion disables the thumb transition (hover-reveal still toggles instantly, just without the fade). `body:hover`/`html:hover` is intentionally broad ONLY for the page's own default scroll containers — it is not a substitute for scoping a nested/self-contained scroll box's own hover state. A single-class self-scoped selector alone is not enough to beat the global rule's specificity — verify with `!important` or a compound (2+ class) selector, don't assume a narrower-looking selector actually wins. |
| `.fi-sc-text` (helper text) | Gradient left-border `::before` indicator (`--gradient-accent` light / `--gradient-brand` dark); expands on hover. | — || `.fi-in-repeatable-spaced` (opt-in, via `->extraAttributes(['class' => ...])` on a `RepeatableEntry`) | `padding-block` + hairline `border-top` on each `.fi-in-repeatable-item`, first item exempted from the border — breaks up a compact one-row-per-item infolist list (RO/PI/PR's line-items tab) so rows don't visually run together. At `≤1023px` (below Filament's own `lg:fi-grid-cols` breakpoint, matched deliberately, not the usual 768px "mobile" cutoff), each `.fi-in-repeatable-item` gets `overflow-x:auto` + thin scrollbar, and its child `.fi-sc` gets `grid-template-columns: var(--cols-lg); min-width: max-content` — forces the item's field grid back to its desktop column count and scrolls horizontally instead of collapsing to Filament's default single-column stack. Its scrollbar carries its own dedicated `::-webkit-scrollbar`/`::-webkit-scrollbar-thumb`/`:hover` override, not the bare global fallback (see Custom scrollbar row). | Uses the same literal `rgba(0,0,0,.05)`/`rgba(255,255,255,.05)` divider pair already repeated elsewhere in this file (e.g. `.fi-ta-pagination`) rather than a `--custom-*` token — an established exception in this file for hairline dividers specifically, not a new violation. `--cols-lg` is set by Filament's own `grid()` macro (`ComponentAttributeBag::macro('grid', …)` in `vendor/filament/support/src/SupportServiceProvider.php`) as a full `repeat(N, minmax(0,1fr))` track-list, not a bare number — safe to reference directly in `grid-template-columns`. |

---

## 3. LOAD-BEARING — login CSS (DO NOT RESTRUCTURE)

The login background effect lives in `::before` / `::after` pseudo-elements on `.fi-body .fi-simple-layout` — **not** in the blade view, which has no `<video>` element. Never restructure `.fi-body .fi-simple-layout` or `.fi-simple-main`.

```css
.fi-body .fi-simple-layout {
  background: linear-gradient(135deg in oklch,
    var(--custom-fourth) 0%, var(--custom-third-mid) 30%, var(--custom-first) 55%,
    var(--custom-third-mid) 80%, var(--custom-fourth) 100%);
  box-shadow: inset 0 0 220px 80px rgba(0,0,0,.12);
}
.dark .fi-body .fi-simple-layout { background: var(--filament-dark); box-shadow: inset 0 0 240px 90px rgba(0,0,0,.5); }
```

```css
.fi-body .fi-simple-layout::before {
  position: fixed; right: 0; top: 0; width: 45%; height: 100vh;
  background: url("../video/2.webp") center center / cover no-repeat !important;   /* light */
  mix-blend-mode: screen; opacity: .65;
  mask-image: linear-gradient(to left, rgba(0,0,0,1) 85%, rgba(0,0,0,0) 100%);
  z-index: -1;
}
.dark .fi-body .fi-simple-layout::before {
  background: url("../video/1.webp") center center / cover no-repeat !important;
  mix-blend-mode: lighten; opacity: .28;
}
```

`1.webp`/`2.webp` are **static/animated WebP images** used as a CSS `background-image` on `::before` — not `<video>` elements. `url("../video/2.webp")` resolves after Vite build because CSS output lands in `public/build/assets/`, two `../` hops from `public/video/`.

`.fi-body .fi-simple-layout::after` is a fractal-noise grain overlay (inline SVG `feTurbulence`, `background-size:180px`, `mix-blend-mode: soft-light`, `opacity: .55` light / `.35` dark), animated via `grain-drift 14s linear infinite` (gated behind `prefers-reduced-motion`). Do not remove.

`.fi-simple-main` (the login card): `position:absolute; left:15%; border-radius:16px;` elevation-2 → elevation-3 on hover.

`.fi-simple-header .fi-logo` is currently height-authoritative (`width:auto; height:3rem !important`, matching the panel's `brandLogoHeight('3rem')`) — this value has been re-tuned before; check the live file rather than trusting any specific number here.

`.fi-login-brand` (the login page app-name wordmark, rendered by `CustomLogin::getHeading()` wrapping `config('app.name')`) uses `font-family: "Baloo 2", …` at `font-weight: 900`. Note: `fonts.css` registers the Baloo 2 `@font-face` at `font-weight: 800` only — requesting `900` means the browser synthesizes a fake-bolder layer on top of the real 800 glyph. If the wordmark ever looks subtly off, this mismatch (900 requested vs. 800 embedded) is the first thing to check. The loader's `.ldr-letter` (§6) correctly requests `800` on both themes — `.fi-login-brand` does not currently match it.

---

## 4. `fi-custom.css` @keyframes

`gradient-shift`, `jello-horizontal` (defined redundantly 4× in the file), `fade-in`, `pulse` (box-shadow ring, used by the active-sidebar `::before`), `slide-in-top`, `slide-in-down`, `slide-right`, `slide-left`, `slide-down`, `grain-drift`, plus `dr-ping` (desk-reference unread-badge pulse, unrelated to the landing page/loader systems documented here).

`pulse` and the `slide-*` keyframes live in `fi-custom.css`, NOT `landing-page.css`.

---

## 5. `landing-page.css` — flat enterprise system (post-2026-07-18)

Bordered `bg-white dark:bg-zinc-900` surfaces, `rounded-lg` / `shadow-sm`, 150–200ms transitions. No scale/rotate/spring hover flourishes, no 3D tilt. Scope is the landing page only.

### Removed classes — do not reintroduce

`.card-3d`, `.glass`, `.tri-widget-panel`, `.shimmer-effect`, `.floating`, `.glow-orb`, `.pulse-ring`, `.shadow-elegant`, `.workflow-connector`, `.thread-path`, `.workflow-node`, `.btn-wrapper`, `.badge-float`, `.btn-gradient`, `.icon-container`, `.btn-inline` — none exist in `landing-page.css`/`fi-custom.css` (confirmed via grep). `.glow-orb` does still exist verbatim inside `resources/views/errors/404.blade.php`'s own self-contained `<style>` block — that page is a standalone document outside the Filament panel/landing page, so it's not a counter-example.

### Classes that exist

| Class | Notes |
|---|---|
| `.widget` | Just `direction: ltr; position: relative;` — a 2-declaration utility, not a glass panel. |
| `.loader-overlay` + `.ldr-*` | Loader system — see §6. |
| `.light` / `.dark` | Soft top/bottom ellipse radial-gradient glow — not a dot-grid (the dot-grid is `.ldr-grid`, loader-only). |
| `.lp-surface` / `.lp-surface-hover` / `.lp-bar` / `.lp-divider` / `.lp-tab` / `.lp-tab-active` | Flat surface tokens for the enterprise landing UI. The underline tab switcher is `.lp-tab` / `.lp-tab-active` — there is no bare `.tab` / `.tab-active`. |
| `.lp-panel` / `.lp-well` | Nested-depth surfaces inside an `.lp-surface` card. `.lp-panel` = `--custom-third-mid` light / `--google-second-dark` dark. `.lp-well` = `--custom-neutral-light` light / `--google-first-dark` dark, one step further recessed. Neither carries its own `box-shadow` — the parent `.lp-surface` keeps the elevation. |
| `.lp-float` | Opaque surface for floating/overlay elements (tri-widget popover, landing-page locale dropdown). `--custom-neutral` light / `--filament-dark-mid` dark, elevation-3 — solid (unlike `.lp-surface`'s translucent bg) so page content doesn't bleed through a floating popover. |
| `.lp-fab` | Landing switchers corner FAB only — the button carries no surface/padding utilities, this class is its entire styling (user-tuned: `padding: 5px`, `border-radius: 9px !important`, elevation-1, opaque `--custom-neutral`/`--filament-dark-mid` bg `!important`). Exists because the translucent `.lp-surface` over the page's radial-glow bg rendered as a "circle" through every non-forced fix — the `!important` pair is cascade-proof, don't strip it when touching the FAB. |
| `.lp-dock` | The below-`lg` switcher dock row (next to the corner FAB). No chrome of its own (user dropped the container pill background 2026-10-10); its rules force opaque backgrounds on `.lp-dock .lp-surface` (`!important`) and flip `.lp-dock .absolute` popups to open upward (the dock sits at the screen bottom, so `top-full` popups would render off-screen). |
| `.lp-dock-pulse` | `--google-fourth-light`/`-dark` — small activity-indicator dot color (tri-widget dock). |
| `.lp-insight-flag` | Pill badge (Workflow tab tip callouts); its `svg` runs `bulbPulse` (opacity pulse), disabled under reduced-motion. |
| `.lp-ticker` | Auto-rotating tips strip at the bottom of the Workflow tab. `overflow:hidden; white-space:nowrap`; each tip is an absolutely-positioned `x-show="i === rotateIdx"` button that cross-fades — row never wraps, height stays fixed. Gated by `x-show="tips.length >= 2"` in the `workflow()` Alpine scope. |
| `.truncate-2` | 2-line clamp. |
| `.input-inline` / `.chip` / `.range` / `.custom-scrollbar` / `.stepper-connector` | Inline form input / workspace resource chip / custom range input / local scrollbar utility / workflow stepper connector line. |
| `.ldr-slogan-hard` / `.ldr-slogan-smart` / `.ldr-cm` / `.ldr-status-icon` | Loader text/mark decorations — see §6. |

### Accent scope

Indigo `#4f46e5` (light) / cyan `#06b6d4` (dark) is hardcoded in exactly one place: `.range` (track + both thumb variants). It is not a landing-page-wide accent — the loader and tab-switcher route through the `--primary-*`/token bridge instead, not this pair.

### No 5-tone semantic palette in CSS

The `blue`/`green`/`yellow`/`red` (4 pipeline stages) + `slate` (master data) palette is a **Blade/PHP** concern — `SearchService::THEME` defines the keys, reused by `workflow.blade.php`'s `$accent` array and `workspace.blade.php`'s per-module `'accent'` values. Not expressed in CSS at all; don't add it there.

### Font setup

```css
:root {
  --font-default: "Roboto", system-ui, …;
  --font-fa:      "IranYekan", …;
  --font-brand:   "Baloo 2", "Segoe UI", system-ui, …;
}
html[lang="fa"] { --font-default: var(--font-fa); }
body     { font-family: var(--font-default); }
.fi-body { font-family: var(--font-fa); }
```

`--font-brand` (declared in `landing-page.css :root`, not `fi-custom.css`) is opt-in, used only where the app name renders as literal text (`.ldr-letter`, `.fi-login-brand`) — Roboto/IranYekan ship a single `normal`-weight `@font-face` and can't produce a genuine heavy glyph. Baloo 2 is self-hosted at exactly `font-weight: 800`; requesting a different weight against it triggers synthetic (fake) bolding.

---

## 6. Loader system (`landing-page.css`, `.ldr-*` block)

Rules are scoped `.light .ldr-*` / `.dark .ldr-*`, not bare — same ancestor-class theming convention used throughout the file (distinct from Tailwind `dark:`, which `header.blade.php` uses instead).

| Class | Notes |
|---|---|
| `.loader-overlay` | `fixed inset:0; z-index:9999`, flex-centered, `perspective:1000px`. |
| `.ldr-grid` / `.ldr-scan` / `.ldr-glow` | Dot-grid, horizontal scan line, central glow — `lGrid`/`lScan`/`lGlow`. |
| `.ldr-c` + `.ldr-c-tl/tr/bl/br` | Corner brackets, staggered entrance via `lCorner`. |
| `.ldr-eyebrow` | Mono label, wide letter-spacing, `lUp`. |
| `.ldr-mark` + `.ldr-mark-img` | Brand mark lockup between eyebrow and wordmark. Two `<img>`s (`.ldr-mark-light`/`.ldr-mark-dark`, `config('app.branding.logo.*')`), one hidden per theme (`.light .ldr-mark-dark{display:none}` etc.) — the same `.light .x`/`.dark .x` convention, not Tailwind `dark:`. |
| `.ldr-letter` | `font-family: var(--font-brand)`, `font-weight: 800` in **both** themes (correctly matches the 800-only `@font-face` — contrast with `.fi-login-brand`'s mismatch, §3). Staggered via `--i` CSS var, `lIn`. |
| `.ldr-subtitle` | CSS exists but the element isn't rendered in `loader.blade.php` (removed in the 2026-07-18 redesign) — dead but harmless. |
| `.ldr-divider` / `.ldr-track` / `.ldr-fill` | Progress bar; `.ldr-fill` scales `0 → 1` via `lFill`; `::after` is a 5px glowing dot. |
| `.ldr-status` | Status text line. |
| `.ldr-slogan-hard` + `::after` | Strikethrough diagonal (the "~~hard~~" wordplay). |
| `.ldr-slogan-smart` | Complementary accent color. |
| `.ldr-cm` | Leftover pre-rebrand "CM" mark class — not rendered by current `loader.blade.php`; don't assume it exists as a live element. |

**2900ms auto-hide is not CSS** — it's Alpine `x-init="setTimeout(...)"` in `landing-page.blade.php` and `loader.blade.php` (writes `sessionStorage['bms_loaded']`).

**Theme colors are not hardcoded indigo/cyan** — every loader color routes through `color-mix(in oklab, var(--primary-600|400) X%, transparent)` (or `var(--primary-900)`/`var(--primary-50)` for the letters), tracking Filament's panel `--primary` color (`Color::Slate` in `DashboardPanelProvider.php`) — so the loader currently renders in slate tones, not indigo/cyan.

**Keyframes**: `lIn`, `lUp`, `lSub`, `lFill`, `lScan`, `lCorner`, `lGlow`, `lGrid` — plus `bulbPulse` (used by `.lp-insight-flag`, §5), which is easy to miss since it's declared outside the loader block. Reduced-motion disables `.ldr-scan`/`.ldr-glow`/`bulbPulse`.

---

## Developer Decision Matrix

| When you need to… | Do this… | Why… |
|---|---|---|
| Change a panel color | Edit the relevant `--custom-*`/`--google-*` token in `fi-custom.css :root`. | Re-themes both systems in one move. |
| Add/switch a runtime palette | `themes.css` block + `config/palettes.php` entry + 3 locale labels (see §1b). Never add per-theme literals inside `:root`. | The palette layer is the only sanctioned override; `ConfigTest` trips on drift. |
| Add a new panel surface override | Target the Filament `.fi-*` class in `fi-custom.css`; consume tokens, never hex. | Keeps the token bridge intact. |
| Add a landing-page-only utility class | Add it to `landing-page.css`; reuse `--custom-*`/`--google-*` for color. | Prevents a third CSS system forking the token layer. |
| Add a motion | Put `@keyframes` in the file it belongs to (panel → `fi-custom.css`, landing/loader → `landing-page.css`). | Matches the existing split. |
| Add a form-control accent on the landing page | Use `#4f46e5` (light) / `#06b6d4` (dark), matching `.range`. | The one place indigo/cyan is genuinely hardcoded — the loader tracks `var(--primary-*)` instead, don't conflate the two. |
| Color a workflow/workspace stage | Use the Blade/PHP palette (`SearchService::THEME`) — don't express it in CSS. | Avoids a second source of truth. |
| Change the login background | Don't, if avoidable. If forced: edit `.fi-body .fi-simple-layout::before`, keep the `url("../video/N.webp")` path, then rebuild + hard-refresh. | The WebP `::before` is load-bearing; the path only resolves after `npm run build`. |
| Add a JS-driven visual effect on the landing page | Don't — CSS-only. | The Three.js background was removed intentionally to cut GPU/JS cost. |

---

## Absolute Anti-Patterns

- ❌ Hardcode a hex that already has a `--custom-*`/`--google-*` token — breaks the token bridge for future rethemes.
- ❌ Declare a third color-token layer in `landing-page.css` — it must consume `fi-custom.css :root` tokens, never invent its own.
- ❌ Reintroduce any of the 16 removed landing-page classes (§5) — reimports the dead 3D/glass aesthetic.
- ❌ Reference `float`/`glow`/`shimmer`/`pulse-ring`/`fadeSlide`/`slide`/`draw-thread`/`pulse-amber` as if they exist in `landing-page.css` — they don't; only the loader keyframes + `bulbPulse` do (§6).
- ❌ Put `pulse`/`slide-*` keyframes in `landing-page.css` — they live in `fi-custom.css`.
- ❌ Reintroduce `resources/js/3d.min.js` / `resources/js/landing-page.js` or add them back to `vite.config.js`.
- ❌ Restructure `.fi-body .fi-simple-layout`/`.fi-simple-main` or remove their `::before`/`::after` — the login background lives entirely there; the blade view has no `<video>`.
- ❌ Use a scale/rotate/spring hover flourish on the landing page — the enterprise redesign is 150–200ms transitions only.
- ❌ Put `will-change` on elements not using GPU-accelerated properties (`transform`/`opacity`) — project coding-philosophy rule.
- ❌ Duplicate a utility class that already exists in `landing-page.css` or `fi-custom.css`.

---

## Load-bearing warnings

1. **`fi-custom.css` is load-bearing. Any edit → `npm run build` + hard-refresh (`Ctrl+Shift+R`).** Dev-mode CSS can silently appear broken otherwise (especially the login WebP background).
2. **`.fi-body .fi-simple-layout::before` is the login background** (§3). Never restructure this pseudo-element.
3. **`.fi-body .fi-simple-layout::after` is the fractal-noise grain overlay.** Do not remove.
4. **The token bridge is the only bridge.** `fi-custom.css :root` is the single point of theme truth for both CSS systems — do not fork or shadow it.
5. **The loader is not frozen** — it has been edited since (brand mark, wordmark font) and will be again. Verify against the live file before assuming any specific value here.

## Calendar grid tokens (added 2026-10-08)

`fi-custom.css` carries a `.cal-*` block (raised tile cells, today/selected/past/overdue states, dots, count badge, agenda, mobile switch below `md`) built only from `--custom-*` / `--google-*` tokens via `color-mix()`, plus ONE sanctioned token family, `--cal-color-{key}` for the 8 rule colours (`sky, emerald, amber, rose, violet, teal, orange, slate`), each with a light and a dark value checked against the five themes in `themes.css`. This is the single exception to "no third system": it exists because a rule colour is user data (the enum `CalendarColor` is the single source of the keys; `ConfigTest`/the widget tests pin keys against the CSS). After editing it run `php artisan filament:assets` (the file is registered through `FilamentAssets`).
