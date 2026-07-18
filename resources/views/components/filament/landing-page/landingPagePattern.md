# Landing Page Design

Verified against source on branch `feature/landing-page-enterprise-redesign` (2026-07-18). Covers the landing page specifically — for the general two-system CSS architecture (`fi-custom.css` + `landing-page.css` sharing one token layer) see `resources/css/stylesPattern.md`; for the Alpine.js factories see `resources/js/scriptPattern.md`.

## What this page is

The Filament panel's landing page (`components.filament.landing-page`, rendered by `App\Filament\Pages\LandingPage` on its own custom `layout` view, not the standard panel layout) — three tabs (Customize / Workflow / Search) over the 8 operational + 7 master-data modules, plus a floating clock/timer/music widget and a locale/theme/logout switcher cluster.

It was rebuilt in the 2026-07-18 enterprise redesign from a glassmorphism/3D aesthetic (blurred panels, gradient buttons, floating badges, a rotating Three.js particle/torus background) into a flat, data-dense, **Filament-native** design — meaning it does not invent its own visual language, it reuses the real one the rest of the admin panel already runs on.

## The sync mechanism — not a claim, a mapping

"Sync with Filament" here means concretely: every surface, active state, and accent color on the landing page is drawn from the same CSS custom properties and the same live `--primary-*` color that `fi-custom.css` and Filament's own components use. There is no second, parallel color system.

| Landing page | Mirrors | Real Filament rule (`fi-custom.css` / Filament core) |
|---|---|---|
| `.lp-surface` | `.fi-section` | `background-color: var(--custom-third-light)` light / `var(--filament-dark-mid)` dark, `box-shadow: var(--md-elevation-1)` |
| `.lp-surface-hover` | `.fi-section:hover` | `background-color: var(--custom-neutral)` light / `var(--google-first-dark)` dark, `box-shadow: var(--md-elevation-2)` |
| `.lp-bar` | `.fi-topbar` | `background-color: var(--custom-third-mid)` light / `var(--filament-dark-mid)` dark, `box-shadow: var(--md-elevation-2)` |
| `.lp-tab` / `.lp-tab-active` | `.fi-tabs-item` / `.fi-tabs-item.fi-active` | inactive: `color: var(--custom-fourth)` light / `var(--google-third-light)` dark. Active: `background-image: var(--gradient-secondary)` light / `var(--gradient-google-deep)` dark, `box-shadow: var(--md-elevation-1)` |
| `.light` / `.dark` (root background) | `.fi-body` | `radial-gradient(ellipse at 50% 0%, var(--google-second-light), transparent 70%)` light / `radial-gradient(ellipse at 50% 120%, var(--custom-third-mid), transparent 60%)` dark |
| `text-primary-600` / `text-primary-400` (accent) | Filament's `--primary` panel color | Resolves through the `@theme inline` block in `vendor/filament/support/resources/css/index.css` (`--color-primary-600: var(--primary-600)`) to the panel's actual configured color — `Color::Slate` in `DashboardPanelProvider.php`. **Not a hardcoded hex.** If the panel's primary color is ever changed, the landing page's accent changes with it automatically. |

One deliberate deviation: `.lp-tab-active`'s light-mode text is solid `var(--filament-dark)` rather than `fi-custom.css`'s literal `var(--custom-fourth-light)` (a 40%-opacity gray). The literal token reads too pale against the gradient pill in practice; dark mode already used opaque `--google-first-light` and needed no change.

`landing-page.css` declares its own tokens only for what `fi-custom.css` doesn't provide: `--font-default` / `--font-fa` / `--font-mono` (font stacks) and the loader's own private variables. It does **not** declare its own color tokens — colors always come from the shared `:root` layer in `fi-custom.css`, which loads first (`@filamentStyles` in `resources/views/layout.blade.php`, before the `@stack('headCSS')` that pulls in `landing-page.css`).

## Semantic palette

Five tones, reused everywhere a module/step/result needs a color, not fifteen:

| Tone | Used for | Matches Filament's literal `.tb-badge` family |
|---|---|---|
| `blue` | Request & Approval stage (Purchase Requests, Proforma Invoices) | `.tb-info` (`blue-50` / `blue-700`) |
| `green` | Order Processing stage (Registered Orders, Bank Profiles) | `.tb-success` (`green-50` / `green-700`) |
| `yellow` | Procurement & Payment stage (Purchase Orders, Payments) | `.tb-warning` (`yellow-50` / `yellow-800`) |
| `red` | Logistics stage (Shipments, Customs) | `.tb-danger` (`red-50` / `red-700`) |
| `slate` | All master-data modules (Categories, Products, Companies, Banks, Currencies, Statuses, Notifications) — non-operational, no pipeline stage | matches the panel's `primary: Color::Slate` |

The same 4-color mapping (operational stage → tone) is used identically in three independent places — the Workflow stepper, the Workspace module tiles, and Spotlight search results — so a Purchase Order always reads "yellow" everywhere on the page. This mapping lives in three PHP `@php` blocks (`workflow.blade.php`, `workspace.blade.php`) and `SearchService::THEME` / `registry()` — there is no shared PHP source for it (see "Known duplication" below).

## Component inventory

| File | Role |
|---|---|
| `landing-page.blade.php` | Root. Locale/dark-mode/tab state (`landingPage()` Alpine factory), loader include, tab-panel switching. |
| `loader.blade.php` | Full-screen boot animation. **Explicitly out of scope for this design system** — untouched by the 2026-07-18 redesign and must stay that way; it has its own private `--custom-*`-independent color scheme (hardcoded indigo/cyan) and its own `.ldr-*` classes. |
| `switchers.blade.php` | Floating locale / dark-mode / logout / widget-toggle buttons, top-right (RTL-aware). |
| `widget.blade.php` | Floating clock/timer/music panel (`triWidget()` factory). |
| `header.blade.php` | "Return to dashboard" bar + the Customize/Workflow/Search tab switcher (`.fi-tabs-item` pattern). |
| `workflow.blade.php` | 4-step horizontal stepper over the operational pipeline, data-driven off a `$steps` array. |
| `workspace.blade.php` | Module-pinning + record-pinning accordions (`workspace()` factory, `localStorage`-persisted). |
| `search.blade.php` | Spotlight search (`search` factory, hits `/api/search/spotlight`), breadcrumb pipeline-stage bar, result list/detail. |
| `footer.blade.php` | Flat master-data quick-link row (Categories/Products/.../Notifications). |

## Extending this page

- New card/panel → `class="lp-surface"` (+ `lp-surface-hover` if it's clickable), never hardcode `bg-white dark:bg-zinc-900` or similar.
- New accent color (buttons, active icons) → `text-primary-600 dark:text-primary-400` (or the `bg-`/`border-` equivalents), never `indigo`/`cyan`/any hardcoded hue — those utilities only resolve to a real color because `fi-custom.css`'s Tailwind compilation (which imports Filament's `@theme inline` primary registration) scans `resources/views/**/*.blade.php` via `@source`; `landing-page.css` itself is plain CSS with no Tailwind processing.
- New category needing a color → pick from the 5-tone semantic palette above, matching by literal Tailwind family (`blue`/`green`/`yellow`/`red`/`slate`), not an arbitrary hue.
- New internal divider line → `border lp-divider`, not a hardcoded `border-zinc-200 dark:border-white/10`.
