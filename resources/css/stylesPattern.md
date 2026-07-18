# BMS-CM CSS Design Pattern

Verified against source on branch `feature/landing-page-enterprise-redesign` (2026-07-18). Where this doc conflicts with CLAUDE.md's CSS sections, this doc is authoritative — CLAUDE.md's landing-page utility-class catalog and keyframes list are stale (several listed classes/keyframes were removed in the 2026-07-18 enterprise redesign).

This document captures the two coexisting CSS systems that theme BMS-CM and the single shared token layer that binds them. Future AI agents must replicate this exact structure when extending or retheming the application.

## Core idea

BMS-CM has **TWO coexisting CSS systems with ONE shared token layer**:

- **(a) `fi-custom.css`** — the Filament panel morphing layer. It does not invent new components; it overrides Filament's internal `.fi-*` classes via a token bridge (`--custom-*`, `--google-*`, and named `--gradient-*` variables) declared in `:root`.
- **(b) `landing-page.css`** — a flat, data-dense, enterprise landing-page design system that was JUST rewritten away from glassmorphism/3D in the 2026-07-18 enterprise redesign.

The shared tokens mean a theme change in `fi-custom.css :root` re-themes **both** systems. This dual-system-with-shared-tokens is THE hallmark of BMS-CM's CSS. Respect it: do not fork the token layer, do not introduce a third system, and do not hardcode colors that already have a `--custom-*` / `--google-*` variable.

## Directory & pipeline

```
resources/css/
├── app.css                  ← Tailwind base (scans resources/views)
├── fi-custom.css            ← Filament panel morph layer + :root tokens (load-bearing)
├── layout/
│   └── fonts.css            ← @font-face: Roboto (woff2/woff) + IranYekan (woff/ttf)
└── landing-page.css         ← Landing page design system (flat, post-2026-07-18)
resources/js/
└── app.js                   ← ONLY JS entry (Filament/Livewire). No other JS entries.
resources/img/*   → public/img/      (static copy)
resources/audio/* → public/audio/    (static copy)
resources/video/* → public/video/    (static copy; includes 1.webp / 2.webp for login bg)
```

### Vite entry order (`vite.config.js` lines 8–14)

1. `resources/css/app.css`
2. `resources/css/fi-custom.css`
3. `resources/css/layout/fonts.css`
4. `resources/css/landing-page.css`
5. `resources/js/app.js`  ← the ONLY JS entry

### Static copies (`vite.config.js` lines 17–23, `vite-plugin-static-copy`, `refresh: true`)

- `resources/img/*`   → `../img/`
- `resources/audio/*` → `../audio/`
- `resources/video/*` → `../video/`

### Removed in the enterprise redesign — do NOT reintroduce

- `resources/js/3d.min.js` — deleted. Not an entry, not a static-copy target, zero references.
- `resources/js/landing-page.js` — deleted. Was 100% Three.js particle/torus/ring scene code. No references remain.

Do not reintroduce either file, and do not add them back to `vite.config.js` input or `viteStaticCopy` targets. The full-screen Three.js torus background (`<canvas id="canvas-bg">`) and its `window.torusMaterial` / `window.ringMaterial` opacity-toggle watcher in `landing-page-alpine.js` are also gone.

---

## 1. `fi-custom.css :root` token layer (lines 11–66)

This is the single source of truth for color, motion, and elevation. Both CSS systems consume these tokens; the landing page does **not** declare its own color tokens (it only declares `--font-default` / `--font-fa`).

### Core surface palette

```css
--custom-first:  #F8FAFC;   /* lightest surface */
--custom-second: #D9EAFD;
--custom-third:  #BCCCDC;
--custom-fourth: #9AA6B2;   /* muted text */
--custom-neutral:#E8E8E8;
--filament-dark: #09090B;   /* dark bg */
```

### Mid-alpha (0.7)

```css
--custom-second-mid: rgb(217,234,253,0.7);
--custom-third-mid:  rgb(188,204,220,0.7);
--custom-fourth-mid: rgb(154,166,178,0.7);
--filament-dark-mid: #18181B;
```

### Light-alpha (0.4)

```css
--custom-second-light:  rgb(217,234,253,0.4);
--custom-third-light:   rgb(188,204,220,0.4);
--custom-fourth-light:  rgb(154,166,178,0.4);
--custom-neutral-light:rgb(232,232,232,0.5);
```

### Google Material palette — light

```css
--google-first-light:  #FFFFFF;
--google-second-light: #E1E4E8;
--google-third-light:  #C1C6CC;
--google-fourth-light: #5C6AC4;
```

### Google Material palette — dark

```css
--google-first-dark:  #1E1E1E;
--google-second-dark: #2C2B2F;
--google-third-dark:  #49454F;
--google-fourth-dark: #6750A4;
```

### Named gradients (15, all `linear-gradient(135deg, …)`)

```css
--gradient-primary:        linear-gradient(135deg, var(--custom-first),  var(--custom-second));
--gradient-secondary:      linear-gradient(135deg, var(--custom-second), var(--custom-third));
--gradient-accent:         linear-gradient(135deg, var(--custom-third),  var(--custom-fourth));
--gradient-dark:           linear-gradient(135deg, var(--custom-fourth), var(--filament-dark));
--gradient-neutral:        linear-gradient(135deg, var(--custom-neutral),var(--custom-second-mid));
--gradient-deep:           linear-gradient(135deg, var(--filament-dark), var(--filament-dark-mid));
--gradient-light:          linear-gradient(135deg, var(--custom-second-light), var(--custom-third-light));
--gradient-soft:           linear-gradient(135deg, var(--custom-fourth-light), var(--custom-neutral-light));
--gradient-google-light:   linear-gradient(135deg, var(--google-first-light), var(--google-second-light));
--gradient-google-accent:  linear-gradient(135deg, var(--google-third-light), var(--google-fourth-light));
--gradient-google-dark:    linear-gradient(135deg, var(--google-first-dark), var(--google-second-dark));
--gradient-google-deep:    linear-gradient(135deg, var(--google-third-dark), var(--google-fourth-dark));
--gradient-contrast:       linear-gradient(135deg, var(--custom-first),  var(--custom-fourth));
--gradient-brand:          linear-gradient(135deg, var(--google-fourth-light), var(--google-fourth-dark));
--gradient-hero:           linear-gradient(135deg, var(--custom-second), var(--filament-dark));
```

### Motion & elevation

```css
--md-motion:      cubic-bezier(0.2, 0, 0, 1);   /* enter / standard */
--md-motion-exit: cubic-bezier(0.4, 0, 1, 1);   /* exit */

--md-elevation-1:      0 1px 3px  rgba(0,0,0,0.3);   /* light */
--md-elevation-2:      0 3px 6px  rgba(0,0,0,0.15);
--md-elevation-3:      0 10px 20px rgba(0,0,0,0.15);
--md-elevation-1-dark: 0 1px 3px  rgba(0,0,0,0.5);
--md-elevation-2-dark: 0 3px 6px  rgba(0,0,0,0.3);
--md-elevation-3-dark: 0 10px 20px rgba(0,0,0,0.3);
```

---

## 2. `.fi-*` morphing declarations (the load-bearing overrides)

These are the Filament panel overrides that give BMS-CM its identity. Every override goes through a `--custom-*` / `--google-*` / `--gradient-*` token — never a hardcoded hex.

### `.fi-body` (L79) — body background

```css
.fi-body {
  background-color: var(--custom-first) !important;
  background-image: radial-gradient(ellipse at 50% 0%, var(--google-second-light), transparent 70%) !important;
  background-attachment: fixed !important;
  animation: gradient-shift 15s var(--md-motion) infinite alternate;
}
.dark .fi-body {
  background-color: var(--filament-dark) !important;
  background-image: radial-gradient(ellipse at 50% 120%, var(--google-second-light), transparent 70%) !important;
}
```

### `.fi-topbar` (L97)

```css
.fi-topbar {
  box-shadow: var(--md-elevation-2) !important;
  max-height: 20px !important;
  border: none;
  background-color: var(--custom-third-mid) !important;
  animation: slide-in-top 0.5s var(--md-motion) both;
}
.dark .fi-topbar {
  background-color: var(--filament-dark-mid) !important;
  box-shadow: var(--md-elevation-2-dark) !important;
}
```

### `.fi-sidebar-item > a` (L245)

```css
.fi-sidebar-item > a {
  background-image: var(--gradient-neutral);
  transition: all 0.25s var(--md-motion);
}
.dark .fi-sidebar-item > a {
  background-color: var(--filament-dark-mid) !important;
  background-image: none;
}
.fi-sidebar-item > a:hover {
  border-radius: 8px;
  background-color: var(--custom-neutral);
  transform: translateX(3px);
  box-shadow: var(--md-elevation-1);
}
```

### `.fi-sidebar-item.fi-active > a::before` (L280)

```css
.fi-sidebar-item.fi-active > a::before {
  content: '\1F539';           /* 🔵 BLUE DIAMOND */
  position: absolute;
  width: 3px;
  border-radius: 50%;
  animation: pulse 2.5s infinite var(--md-motion);
}
```

### `.fi-section` (L306)

```css
.fi-section {
  background-color: var(--custom-third-light) !important;
  box-shadow: var(--md-elevation-1) !important;
  transition: … 0.3s var(--md-motion);
  border-radius: 12px;
}
.dark .fi-section {
  background-color: var(--filament-dark-mid) !important;
  box-shadow: var(--md-elevation-1-dark) !important;
}
.fi-section:hover { box-shadow: var(--md-elevation-2) !important; }
/* hover elevation is suppressed inside modals and widgets */
```

### `.fi-ta` tables (L335)

```css
.fi-ta { border: none; box-shadow: var(--md-elevation-1) !important; }
.fi-ta thead th {
  background-color: var(--custom-third-light);
  border-radius: 12px 12px 0 0;
}
.fi-ta .fi-ta-pag { background-color: var(--custom-second-light); }
```

### `.fi-modal-window` (L379) + modal-open side-effects (L421–431)

```css
.fi-modal-window {
  background-color: var(--custom-neutral);
  border-radius: 16px;
  box-shadow: var(--md-elevation-3) !important;
}
.dark .fi-modal-window {
  background-color: var(--filament-dark-mid);
  box-shadow: var(--md-elevation-3-dark) !important;
}
/* Container backdrop */
.fi-modal-overlay { background: rgba(217,234,253,0.85); }   /* light */
.dark .fi-modal-overlay { background: rgba(24,24,27,0.9); } /* dark */

/* L431: when a modal is open, hide topbar + sidebar */
body:has(.fi-modal-open) :is(nav.fi-topbar,
                              .fi-sidebar-nav,
                              aside.fi-sidebar.fi-main-sidebar.fi-sidebar-open) {
  opacity: 0 !important;
  pointer-events: none !important;
  transition: opacity 0.2s ease-out;
}
/* L421-429: badge + scrollbar hidden while modal open */
```

### `.fi-tabs` / `.fi-tabs-item` (L474 / L496)

```css
.fi-tabs {
  display: flex;
  overflow-x: auto;
  border-radius: 12px;
  scrollbar-width: thin;
  padding: 4px;
}
.fi-tabs-item {
  color: var(--custom-fourth) !important;
  transition: all 0.3s var(--md-motion);
  border-radius: 8px;
}
.fi-tabs-item:hover {
  background-color: var(--custom-neutral-light);
  box-shadow: var(--md-elevation-1);
}
.fi-tabs-item.fi-active {
  background-image: var(--gradient-secondary);
  box-shadow: var(--md-elevation-1);
}
.dark .fi-tabs-item.fi-active {
  background-image: var(--gradient-google-deep);
  color: var(--google-first-light);
}
```

### `.tb-badge` (L437) — backs the `tabBadge()` PHP helper

```css
.tb-badge {
  display: inline-flex;
  margin-left: 0.5rem;
  padding: 0.25rem 0.6rem;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 6px;
  border: 1px solid;
}
.tb-info    { /* blue   */ border-color: rgb(29 78 216);  background: rgb(29 78 216 / .1);  color: rgb(29 78 216); }
.tb-success { /* green  */ border-color: rgb(21 128 61);  background: rgb(21 128 61 / .1);  color: rgb(21 128 61); }
.tb-warning { /* amber  */ border-color: rgb(133 77 14); background: rgb(133 77 14 / .1); color: rgb(133 77 14); }
.tb-danger  { /* red    */ border-color: rgb(185 28 28); background: rgb(185 28 28 / .1); color: rgb(185 28 28); }
```

### Custom scrollbar (L748–818)

- 8px wide; track `var(--custom-second)` (dark `var(--google-first-dark)`); thumb `var(--custom-third)` (dark `var(--google-third-dark)`).
- Inline SVG arrow buttons (`fill='%23888'`); corner transparent.
- Reduced-motion disables the thumb transition.

### `.fi-sc-text` (L606)

```css
.fi-sc-text::before {
  content: '';
  width: 2px;
  height: …;
  background: var(--gradient-accent);
}
.dark .fi-sc-text::before { background: var(--gradient-brand); }
.fi-sc-text:hover::before { width: 3px; height: 100%; }
```

---

## 3. LOAD-BEARING — login CSS (DO NOT RESTRUCTURE)

The login page background effect lives in `::before` / `::after` pseudo-elements on Filament's auth wrappers — **not** in the blade view. Never restructure `.fi-simple-layout` or `.fi-simple-main`.

### `.fi-simple-layout` (L550)

```css
.fi-simple-layout {
  position: relative;
  z-index: 0;
  min-height: 100vh;
  overflow: hidden;
  /* light */
  background: linear-gradient(135deg,
    #2c313c 0%, #3a4452 30%, #f5f7f9 55%, #3a4452 80%, #2c313c 100%);
  box-shadow: inset 0 0 220px 80px rgba(0,0,0,.28);
}
/* dark variant replaces the gradient stops with #1B1B1D… */
```

### `.fi-simple-layout::before` (L564) — THE WEBP LOGIN BACKGROUND

```css
.fi-simple-layout::before {
  position: fixed;
  right: 0; top: 0;
  width: 40%; height: 100vh;
  /* light */
  background: url("../video/2.webp") center center / cover no-repeat !important;
  mix-blend-mode: soft-light;
  opacity: 0.38;
  -webkit-mask-image: linear-gradient(to right, transparent, #000 30%, #000);
  z-index: -1;
}
.dark .fi-simple-layout::before {
  background: url("../video/1.webp") center center / cover no-repeat !important;
  mix-blend-mode: lighten;
  opacity: 0.28;
}
```

`1.webp` and `2.webp` are **static/animated WebP images** served as CSS `background-image` on `::before` — they are **NOT** HTML `<video>` elements. The path `url("../video/2.webp")` resolves after Vite build: CSS output lives in `public/build/assets/`, and two `../` hops reach `public/video/`.

### `.fi-simple-layout::after` (L719) — fractal-noise grain overlay

```css
.fi-simple-layout::after {
  content: '';
  /* SVG data URI: feTurbulence baseFrequency='.85' numOctaves='2' stitchTiles='stitch' */
  background-size: 180px;
  mix-blend-mode: soft-light;
  animation: grain-drift 14s linear infinite;
}
```

### `.fi-simple-main` (L697) — the login card

```css
.fi-simple-main {
  position: absolute;
  padding: 2rem;
  border-radius: 16px;
  box-shadow: var(--md-elevation-2) !important;
}
.fi-simple-main:hover { box-shadow: var(--md-elevation-3) !important; }
```

**Rule:** After ANY `fi-custom.css` edit, run `npm run build` and hard-refresh (`Ctrl+Shift+R`). The login background will silently appear broken in dev otherwise.

---

## 4. `fi-custom.css` @keyframes

```css
@keyframes gradient-shift   { /* background-position 0% ↔ 100% */ }
@keyframes jello-horizontal { /* scale3d wobble — note: defined redundantly 4× in the file */ }
@keyframes fade-in          { /* opacity 0 → 1 */ }
@keyframes pulse            { /* box-shadow ring expand; used by active-sidebar ::before */ }
@keyframes slide-in-top     { /* translateY(-50px) → 0 */ }
@keyframes slide-in-down     { /* translateY(-20px) scale(.98) → 0 */ }
@keyframes slide-right      { /* +300% translate */ }
@keyframes slide-left       { /* −300% translate */ }
@keyframes slide-down       { /* vertical translate */ }
@keyframes grain-drift      { /* background-position → 180px 180px */ }
```

`pulse` and the `slide-*` keyframes live in `fi-custom.css`, NOT in `landing-page.css`.

---

## 5. `landing-page.css` — CURRENT STATE (post-2026-07-18 enterprise redesign)

This is where CLAUDE.md is badly stale. The 2026-07-18 enterprise redesign replaced the landing page's glassmorphism/3D aesthetic with a flat, data-dense, Filament-native language: bordered `bg-white dark:bg-zinc-900` surfaces, `rounded-lg` / `shadow-sm`, 150–200ms transitions, no scale/rotate/spring hover flourishes. Scope was the landing page only.

### Removed in the enterprise redesign — do NOT reintroduce (16 classes)

The following classes are listed in CLAUDE.md's landing-page utility catalog but **DO NOT EXIST** anywhere in `landing-page.css` — they were removed in the 2026-07-18 redesign:

- `.card-3d`
- `.glass`
- `.tri-widget-panel`
- `.shimmer-effect`
- `.floating`
- `.glow-orb`
- `.pulse-ring`
- `.shadow-elegant`
- `.workflow-connector`
- `.thread-path`
- `.workflow-node`
- `.btn-wrapper`
- `.badge-float`
- `.btn-gradient`
- `.icon-container`
- `.btn-inline`

Future agents must NOT reintroduce or reference these as if they exist.

### Nonexistent keyframes (CLAUDE.md is stale here too)

CLAUDE.md's landing-page keyframes list (`float, glow, shimmer, pulse-ring, pulse, fadeSlide, slide, draw-thread, pulse-amber`) is **WRONG** — none of those exist in `landing-page.css`. The only keyframes in `landing-page.css` are the 8 loader keyframes (see §6). (`pulse` / `slide-*` exist in `fi-custom.css`.)

### Classes that DO exist in `landing-page.css`

| Class | Notes |
|---|---|
| `.widget` (L160) | Just `direction: ltr; position: relative;` — a 2-declaration utility, NOT a glass panel. |
| `.loader-overlay` + all `.ldr-*` | Loader system. See §6. Byte-for-byte untouched by constraint. |
| `.light` (L38) / `.dark` (L43) | Soft top/bottom ellipse radial-gradient glow — **NOT a dot-grid**. The dot-grid is ONLY on `.ldr-grid` (loader). |
| `.lp-surface` / `.lp-bar` / `.lp-divider` / `.lp-tab` (L48–126) | Flat surface tokens for the enterprise landing UI. |
| `.truncate-2` | 2-line clamp. |
| `.input-inline` | Inline form input. |
| `.chip` | Resource selector chip (workspace). |
| `.tab` / `.tab-active` | Underline tab switcher. |
| `.range` | Custom range input. |
| `.custom-scrollbar` | Local scrollbar utility. |
| `.stepper-connector` | Workflow stepper connector line. |
| `.ldr-slogan-hard` / `.ldr-slogan-smart` / `.ldr-cm` / `.ldr-status-icon` | Loader text/mark decorations. |

### Accent truth (CLAUDE.md overstates this)

The CLAUDE.md claim "indigo-600 / cyan-400 is the single primary accent across the landing page" is **OVERSTATED**. Indigo/cyan is the **loader + form-controls accent** only:

- `.range` bg `#4f46e5` light / `#06b6d4` dark (thumbs same).
- `.tab-active` bg `rgba(79,70,229,0.12)` light / `rgba(0,212,255,0.1)` dark.
- Loader light `#4f46e5` / dark `#00d4ff`.

Wider landing surfaces (`.lp-surface`, `.lp-bar`, `.lp-tab`, `.light` / `.dark`) use `--custom-*` / `--google-*` tokens (slate-blue + purple), NOT indigo/cyan.

### There is NO 5-tone semantic palette in CSS

The blue/emerald/amber/violet/zinc 5-tone semantic palette (for Request&Approval / Order Processing / Procurement&Payment / Logistics + master-data `zinc`) is a **Blade/PHP** concern (workflow stepper, workspace module tiles, spotlight search results via `SearchService::THEME`). It is not expressed in `landing-page.css` at all.

### Font setup

```css
:root {
  --font-default: "Roboto", system-ui, …;
  --font-fa:      "IranYekan", …;
}
html[lang="fa"] { --font-default: var(--font-fa); }
body     { font-family: var(--font-default); }
.fi-body { font-family: var(--font-fa); }
```

`resources/css/layout/fonts.css` declares `@font-face` for Roboto (woff2/woff) and IranYekan (woff/ttf), both sourced from `/resources/fonts/`.

---

## 6. Loader system (`landing-page.css` L293–356) — BYTE-FOR-BYTE UNTOUCHED

The loader is frozen by explicit constraint. Do not modify it.

| Class | Definition |
|---|---|
| `.loader-overlay` | fixed inset:0; z-index:9999; flex center; perspective:1000px |
| `.ldr-grid` | dot-grid; `lGrid 2s ease-out forwards` |
| `.ldr-scan` | 1px horizontal scan line; `lScan 2.6s ease-in-out .5s infinite` |
| `.ldr-glow` | 380×140 radial ellipse; `lGlow 2.4s ease-in-out .8s infinite` |
| `.ldr-c` + `.ldr-c-tl/tr/bl/br` | 30×30 corner brackets; staggered .05/.1/.15/.2s; `lCorner .7s cubic-bezier(.23,1,.32,1) forwards` |
| `.ldr-eyebrow` | mono `.48rem`; letter-spacing `.44em`; `lUp` |
| `.ldr-letter` | inline-block; `clamp(4.5rem, 12vw, 7.5rem)`; `lIn .65s cubic-bezier(.23,1,.32,1) both`; delay `calc(.28s + var(--i) * .07s)` (stagger via `--i`) |
| `.ldr-subtitle` | mono `.5rem`; letter-spacing `.35em`; `lSub .85s .52s` |
| `.ldr-divider` / `.ldr-track` / `.ldr-fill` | progress track + fill; `.ldr-fill` is `scaleX(0) → 1`; `lFill 2s .85s` |
| `.ldr-fill::after` | 5px glowing dot |
| `.ldr-status` | mono `.42rem`; letter-spacing `.26em` |
| `.ldr-slogan-hard` + `::after` | strikethrough red diagonal; rotate 8deg |
| `.ldr-slogan-smart` | amber |
| `.ldr-cm` | inline-block "CM" mark; `lIn` |
| `.ldr-status-icon` | status icon |

### 2900ms auto-hide is NOT in CSS

It lives in Alpine `x-init` in two blade files:

- `resources/views/components/filament/landing-page.blade.php:36` — `setTimeout(() => appReady = true, 2900)`
- `resources/views/components/filament/landing-page/loader.blade.php:4` — `setTimeout(() => { showing = false; sessionStorage.setItem('bms_loaded', '1') }, 2900)`

### Loader theme colors

- Light theme: indigo `#4f46e5` / `rgba(79,70,229,…)`
- Dark theme: cyan `#00d4ff` / `rgba(0,212,255,…)` / grid `rgba(0,190,255,.09)`

### Loader @keyframes (L349–356) — the ONLY keyframes in `landing-page.css`

`lIn`, `lUp`, `lSub`, `lFill`, `lScan`, `lCorner`, `lGlow`, `lGrid`.

Reduced-motion disables `.ldr-scan` and `.ldr-glow`.

---

## Developer Decision Matrix

| When you need to… | Do this… | Why… |
|---|---|---|
| Change a panel color | Edit the relevant `--custom-*` / `--google-*` token in `fi-custom.css :root`. | The token bridge re-themes both the Filament panel and the landing page in one move. |
| Add a new panel surface override | Target the Filament `.fi-*` class in `fi-custom.css`; consume tokens, never hex. | Keeps the token bridge intact; future rethemes stay single-point. |
| Add a landing-page-only utility class | Add it to `landing-page.css`; reuse `--custom-*` / `--google-*` for color. | Prevents a third CSS system from forking the token layer. |
| Add a motion | Put the `@keyframes` in the file it belongs to (`fi-custom.css` for panel, `landing-page.css` for landing/loader). Keeps the keyframes block at the bottom of that file. | Matches the existing split; avoids cross-file motion coupling. |
| Add a form-control accent on the landing page | Use indigo `#4f46e5` (light) / cyan `#06b6d4` (dark) — same family as the loader. | That is the established controls+loader accent; do not invent a new one. |
| Color a workflow/workspace stage | Use the 5-tone Blade/PHP palette: blue/emerald/amber/violet for the 4 operational stages, zinc for master data. Do NOT express this in CSS. | The semantic palette is a PHP concern (`SearchService::THEME`, `$modules`, `$steps`); CSS stays neutral. |
| Change the login background | Do not. If absolutely forced: edit `.fi-simple-layout::before` in `fi-custom.css`, keep the `url("../video/N.webp")` path, then `npm run build` + `Ctrl+Shift+R`. | The WebP `::before` is load-bearing; the path resolves only after Vite build. |
| Add a JS-driven visual effect on the landing page | Don't. Use CSS-only. The Three.js background was removed intentionally. | The enterprise redesign traded GPU/JS cost for a cheap CSS-only surface. |

---

## Absolute Anti-Patterns (Do Not Do This)

- ❌ **Hardcode a hex that already has a `--custom-*` / `--google-*` token.** Why: breaks the token bridge; a future retheme leaves your element behind.
- ❌ **Declare a third color-token layer in `landing-page.css`.** Why: `landing-page.css` must consume `fi-custom.css :root` tokens, not invent its own. The dual-system-with-shared-tokens hallmark depends on this.
- ❌ **Reintroduce `.glass`, `.card-3d`, `.shimmer-effect`, `.floating`, `.glow-orb`, `.badge-float`, `.workflow-connector`, `.thread-path`, `.workflow-node`, `.tri-widget-panel`, `.btn-wrapper`, `.btn-gradient`, `.icon-container`, `.btn-inline`, `.pulse-ring`, `.shadow-elegant`.** Why: all 16 were removed in the 2026-07-18 enterprise redesign; the landing page is now flat and data-dense. Reintroducing them re-imports the dead 3D/glass aesthetic.
- ❌ **Reference the nonexistent landing-page keyframes (`float`, `glow`, `shimmer`, `pulse-ring`, `pulse`, `fadeSlide`, `slide`, `draw-thread`, `pulse-amber`) as if they live in `landing-page.css`.** Why: CLAUDE.md lists them but they were removed/never existed there. Only the 8 `l*` loader keyframes exist in `landing-page.css`.
- ❌ **Put `pulse` / `slide-*` keyframes in `landing-page.css`.** Why: they live in `fi-custom.css`. Duplicating them forks motion.
- ❌ **Reintroduce `resources/js/3d.min.js` or `resources/js/landing-page.js`, or add them to `vite.config.js` input / `viteStaticCopy`.** Why: removed in the enterprise redesign; zero references. The full-screen Three.js torus is gone by design.
- ❌ **Touch the loader (`loader.blade.php` + all `.ldr-*` CSS/keyframes).** Why: frozen by explicit constraint.
- ❌ **Restructure `.fi-simple-layout` / `.fi-simple-main` or remove their `::before` / `::after` pseudo-elements.** Why: the login background effect lives entirely in those pseudo-elements; the blade view has no `<video>`. Restructuring breaks the WebP path resolution.
- ❌ **Use a scale/rotate/spring hover flourish on the landing page.** Why: the enterprise redesign established 150–200ms transitions only; no 3D tilt, no spring.
- ❌ **Put `will-change` on elements that are not GPU-accelerated (`transform` / `opacity`).** Why: project coding-philosophy rule; misuse causes layer explosions.
- ❌ **Duplicate a utility class that already exists in `landing-page.css` or `fi-custom.css`.** Why: project coding-philosophy rule.
- ❌ **Treat the blue/emerald/amber/violet/zinc palette as a CSS concern.** Why: it is Blade/PHP only; putting it in CSS would duplicate the source of truth (`SearchService::THEME`, `$modules`, `$steps`).

---

## Load-bearing warnings

1. **`fi-custom.css` is load-bearing.** Any edit → `npm run build` + hard-refresh (`Ctrl+Shift+R`). Dev-mode CSS will silently appear broken otherwise (especially the login WebP background).
2. **`.fi-simple-layout::before` is the login background.** The WebP images (`resources/video/1.webp` dark, `2.webp` light) are CSS `background-image`, not `<video>` elements. The `url("../video/N.webp")` path resolves only after Vite build (CSS in `public/build/assets/`, two `../` hops reach `public/video/`). Never restructure this pseudo-element.
3. **`.fi-simple-layout::after` is the fractal-noise grain overlay** (`feTurbulence baseFrequency='.85' numOctaves='2' stitchTiles='stitch'`, `background-size:180px`, `grain-drift 14s linear infinite`). Do not remove.
4. **The token bridge is the only bridge.** `fi-custom.css :root` is the single point of theme truth for both CSS systems. Do not fork it, do not shadow it in `landing-page.css`.
5. **The loader (`.ldr-*` CSS + `loader.blade.php`) is frozen byte-for-byte by constraint.** The 2900ms auto-hide lives in Alpine `x-init`, not CSS.
6. **The 2026-07-18 enterprise redesign removed 16 utility classes and the Three.js background.** CLAUDE.md's landing-page utility catalog and keyframes list are stale. This doc is authoritative for the current state.