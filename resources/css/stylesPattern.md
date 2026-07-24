# BMS-CM CSS Design Pattern

Verified against source on branch `master` (2026-07-25). Authoritative reference for the CSS token system, the `.fi-*` morphing overrides, and the landing-page design system.

## Core idea

BMS-CM has **two coexisting CSS systems sharing one token layer**:

- **(a) `fi-custom.css`** — the Filament panel morphing layer. Overrides Filament's internal `.fi-*` classes via a token bridge (`--custom-*`, `--google-*`, `--gradient-*`) declared in `:root`.
- **(b) `landing-page.css`** — a flat, data-dense, enterprise landing-page design system (rewritten away from glassmorphism/3D in the 2026-07-18 redesign).

A theme change in `fi-custom.css :root` re-themes both systems. Do not fork the token layer, do not introduce a third system, and do not hardcode a color that already has a `--custom-*` / `--google-*` variable.

## Directory & pipeline

```
resources/css/
├── app.css                  ← Tailwind base (scans resources/views)
├── fi-custom.css            ← Filament panel morph layer + :root tokens (load-bearing, 1478 lines)
├── layout/
│   └── fonts.css            ← @font-face: Roboto (woff2/woff), IranYekan (woff/ttf), Baloo 2 ExtraBold 800-only (woff2, brand wordmark)
└── landing-page.css         ← Landing page design system (flat, 928 lines)
```

See CLAUDE.md's "Vite / Asset Pipeline" section for entry order and static-copy targets.

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
--custom-second-mid / --custom-third-mid / --custom-fourth-mid / --filament-dark-mid   /* 0.7 alpha */
--custom-second-light / --custom-third-light / --custom-fourth-light / --custom-neutral-light   /* 0.4 alpha */
```

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
| `.fi-sidebar-item > a` | `--gradient-neutral` bg; hover → `translateX(3px)` + `--custom-neutral` + elevation-1. | Dark mode drops the gradient for a flat `--filament-dark-mid`. |
| `.fi-sidebar-item.fi-active > a::before` | 🔹 (U+1F539, not 🔵) pulsing indicator dot. | — |
| `.fi-section` | `--custom-third-light` bg + elevation-1; hover → elevation-2. | Hover elevation is suppressed inside modals and widgets (separate override rule). |
| `.fi-ta` (tables) | Border-none + elevation-1. Header row bg lives on `.fi-ta-header-ctn` (`--custom-third-light`), the `<th>` text row is `thead > tr th` using the *solid* `--custom-third` (not `-light`), pagination is `nav.fi-ta-pagination` (`--custom-second-light`). | There is no `.fi-ta thead th` selector — it's the unscoped `thead > tr th`. |
| `.fi-modal-window` | `--custom-neutral` bg, `border-radius: 16px`, elevation-3. Backdrop is `.fi-modal-window-ctn` (NOT `.fi-modal-overlay`): `rgba(217,234,253,.85)` light / `rgba(24,24,27,.9)` dark. | When `.fi-modal-open`, sidebar + topbar get `opacity:0; pointer-events:none` via `body:has(.fi-modal-open) :is(nav.fi-topbar, .fi-sidebar-nav, aside.fi-sidebar...)`; scrollbars hidden too. |
| `.fi-tabs` / `.fi-tabs-item` | Flex row, `overflow-x:auto`, thin custom scrollbar. Item color `--custom-fourth`; active → `--gradient-secondary` light / `--gradient-google-deep` dark. | — |
| `.tb-badge` (backs `tabBadge()` helper) | `.tb-info/-success/-warning/-danger` — solid pale-tint background (not an alpha of the accent), `border-color` carries the alpha channel. | `success`/`warning` use a *different* RGB triplet for border vs. text, not a reused one — check source before assuming symmetry. |
| Custom scrollbar | 8px wide; track `--custom-second` (dark `--google-first-dark`); thumb `--custom-third` (dark `--google-third-dark`); inline SVG arrow buttons. | Reduced-motion disables the thumb transition. |
| `.fi-sc-text` (helper text) | Gradient left-border `::before` indicator (`--gradient-accent` light / `--gradient-brand` dark); expands on hover. | — |

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
| `.lp-float` | Opaque surface for floating/overlay elements (tri-widget popover). `--custom-neutral` light / `--filament-dark-mid` dark, elevation-3 — solid (unlike `.lp-surface`'s translucent bg) so page content doesn't bleed through a `fixed`-position popover. |
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
