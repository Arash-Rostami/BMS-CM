# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **Usage:** Start every session with "Read CLAUDE.md first." to load full context before touching anything.
> **Maintenance:** This file is a flat index + policy reference — never a diary, never a second copy of a pattern doc. Domain detail lives in the `*Pattern.md` files below; durable gotchas live in `gotchasPattern.md`. Edit entries in place; prune anything a pattern doc already covers.

## Pattern Documentation (authoritative — read before editing their domain)

Co-located pattern files are the canonical, verified reference for their domains. **Where a pattern file conflicts with CLAUDE.md, the pattern file wins.**

| Domain | File | Covers |
|---|---|---|
| Filament resources | `app/Filament/filamentPattern.md` | Trait-based schema composition, two-tab forms/infolists, EAV dual entry points, `HasResourcePermissions`, import/export, cross-resource creation |
| Dashboard analytics widgets | `app/Filament/Widgets/widgetsPattern.md` | Tabbed `Dashboard` page, `AnalyticsService` caching contract, widget data lineage |
| Services layer | `app/Services/servicesPattern.md` | All 15 services — public APIs, consumers, caching/locale gotchas; `SearchService` spotlight + chain contract |
| Shared import pipeline | `app/Services/Imports/importsPattern.md` | The Pipeline-driven bulk-import architecture shared by every module |
| Calendar rules subsystem | `app/Services/Calendar/calendarPattern.md` | Rule → hits engine (`Sync/`), model-save routing, alerts, activity log, month presenter (`Display/`), permission rules, tracing guide |
| Model layer / migrations | `app/Models/modelsPattern.md` | Model-trait composition, EAV model side, `Status`/`StatusFinder`, migration conventions |
| Global helpers / locale | `app/Utils/helpersPattern.md` | `app/Utils/helpers.php` signatures, `calendar_type` contract, cache-helper internals |
| Localization | `lang/localizationPattern.md` | Locale/key structure, validation-message wiring, implicit DatePicker `date` rule |
| CSS | `resources/css/stylesPattern.md` | `--custom-*`/`--google-*` token system, `.fi-*` morphing, login CSS, landing-page system, Vite pipeline |
| JS / Alpine | `resources/js/scriptPattern.md` | Alpine factories, localStorage keys, custom events, tooltip lesson |
| Views + Landing page | `resources/views/viewsPattern.md` | `components/`/`filament/`/`livewire/` conventions, shared component library, landing-page inventory |
| Livewire topbar components | `app/Livewire/livewirePattern.md` | Session-backed topbar-toggle pattern, render-hook registration |
| Jobs | `app/Jobs/jobsPattern.md` | Import/export job classes, queue-worker code/config-caching gotcha |
| Observers | `app/Observers/observersPattern.md` | Observer registration and side effects |
| Tests | `tests/testPattern.md` | Three-layer mirrored convention, real dev-MySQL `useMysql()` + transaction rollback (no `RefreshDatabase` — no active migrations), `qa-checklist.html` + `qa-findings.json` |
| Cross-cutting gotchas | `gotchasPattern.md` | Non-obvious lessons spanning domains + index of gotchas living in domain docs |

## Agent pipeline policy

### MANDATORY: Read skills before any other work

On the first turn of every session — before replying, before any other action — read and internalize `.claude/skills/code-reviewer/SKILL.md`, `.claude/skills/laravel-performance/SKILL.md`, and `.claude/skills/ollama/SKILL.md`, then summarize their key rules in your own words. `vanilla mode` bypasses this and all other project policies; resume on `resume project mode` or a new session.

### Review model (subagent mode)

`FATEH_REVIEW_MODE='subagent'` is the pipeline default: `.claude/hooks/post_tool_review.php` is INERT — it never gates, it only tracks edit state for the Stop hook. Review ownership lives with the Lead's harness subagents: after each coherent unit of work, spawn a FRESH `claude-reviewer` subagent via the Agent tool (no model override — inherits the driving model), applying two lenses (correctness/security, then performance/pattern-consistency); safe fixes are applied by the Lead or a coder subagent, never by the reviewer. No API call is made for coding, delivery, unit review, or fixes. API calls survive only in plan enrichment and the end-stage dual review (`FATEH_REVIEWER_MODEL_A` + `_B`, one round per stage). In Ollama-native sessions the lean subagent-lanes engine in the ollama skill governs non-trivial work.

### Delegation lanes — the session is the Lead

The session owns all planning/architecture/review decisions; they are never delegated. Three lanes at intake: **Trivial** (single file, few lines) — code directly, no subagent review. **Standard** — optional plan enrichment via `claude-planner` (`FATEH_CC_PLAN_MODEL`), only if it genuinely adds value, then coder slices to `claude-coder` (parallel only across file-disjoint slices with worktree isolation), closed by a mandatory `claude-reviewer` pass. **Complex** (schema/auth/destructive/multi-module) — Fable-5.1 enrichment REQUIRED (`FATEH_PLAN_MODEL`), then one explicit question: refine via the OpenAI refiner (`FATEH_MAX_MODEL`)? (auto-satisfied if the user already said "max") — then as standard. **Exception**: skip the Fable-5.1 pass when the work already has (a) a live, structurally-identical reference implementation in the codebase AND (b) a settled-policy doc covering every field/column/edge-case decision the new work needs — verify both yourself first; if either is missing or the reference→new-module mapping isn't 1:1, the pass is still required. Safeguards: secrets in context → no delegation, work directly; subagent failure → absorb that slice in-harness the same turn.

### End-stage documentation sweep (Stop-hook enforced)

Documentation happens ONCE, in one consolidated sweep right before declaring done — never per-edit, never a dated changelog. During work only two doc rules apply: read the governing pattern doc before editing (a PreToolUse gate enforces it), and erase temp/probe files the same turn they're created. At stage end, one pass over the surfaces this session touched: governing `*Pattern.md` docs verified/updated in their existing style, tests left meaningful and conventional. A serious directory without a `*Pattern.md` walking up its tree must get one concise doc or a one-line reason the existing docs cover it.

## Commands

```bash
composer run dev        # full dev stack (server + queue + Vite HMR) — plain `php artisan serve`, port 8000
composer run test       # all tests (SQLite in-memory)
php artisan test --filter PurchaseRequestResourceTest   # single test class/method
./vendor/bin/pint       # lint / auto-fix code style
npm run build           # build frontend assets
npm run test            # JS test suite (vitest, co-located `__tests__/` — see scriptPattern.md §12)
php artisan filament:assets   # REQUIRED after editing any FilamentAssets.php-registered file (Css::make()/Js::make() entries — Vite HMR does NOT cover them; Filament serves the public/ copy, so an edit without a re-publish is invisible in the browser)
php artisan optimize:clear && php artisan filament:clear-cached-components   # clear all caches
php artisan config:cache && php artisan route:cache && php artisan filament:cache-components   # rebuild caches
```

`/clear`, `/cache`, `/reset` (clear / rebuild / both) also exist as GET routes in `routes/cache.blade.php` — plain PHP route-registration code despite the `.blade.php` extension, loaded via `bootstrap/app.php`'s `withRouting()` `then` callback. Don't rename the extension. Role-gated: `/clear`+`/cache` are `admin_junior`-only, `/reset` also allows `admin_senior` (hierarchy is inverted — rule 1). They call the shared `clearApplicationCaches()` / `cacheApplicationConfig()` / `resetApplicationCache()` helpers (Global Helpers).

## Stack

**Laravel 12** · **PHP ≥ 8.2** · **Filament v4** (unified `Filament\Schemas\*` API) · **Livewire v3** · **Alpine.js v3** (all frontend interactivity, registered via `alpine:init`) · **Vite 6 + TailwindCSS v4** · **Spatie Laravel Permission v6** · **mokhosh/filament-jalali** (Persian/Jalali date-pickers) · **bezhansalleh/filament-language-switch** (EN/FA/FR). Single Filament panel: `dashboard` (path `/dashboard`), SPA mode, dark-mode default.

## Project Domain

B2B procurement management covering the full purchase lifecycle:

```
Purchase Request → Proforma Invoice → Registered Order → Purchase Order → Payment → Shipment → Customs
```

Navigation groups (defined in `lang/en/resources/dashboard/strings.php`): `operational_first` 【1】 Purchase Requests Management · `operational_second` 【2】 Order Registration Files · `operational_third` 【3】 Files Financial Management · `operational_fourth` 【4】 Logistics & Clearance · `base` 【#】 Master Data.

## Resource Architecture

```
app/Filament/Resources/
    XxxResource.php                          ← root class (namespace App\Filament\Resources)
    Operational/XxxResource/
        Traits/Form.php / Table.php / Infolist.php / Filters.php
        Enums/Status.php, Exports/XxxExporter.php,
        Pages/ListXxx.php / CreateXxx.php / EditXxx.php, RelationManagers/…
    Master/XxxResource/
        Traits/Table.php / Infolist.php / Filters.php
        Pages/ManageXxx.php                   ← single page, no create/edit routes
    General/
        FormComponents.php / InfoComponents.php / TableComponents.php   ← shared cross-resource components
```

- **Operational** resources (PurchaseRequest, ProformaInvoice, RegisteredOrder, BankProfile, PurchaseOrder, Payment, Shipment, Custom, Correspondence): List + Create + Edit pages (view via modal), Create header action.
- **Master** resources (Bank, Category, Company, Currency, Department, EntityAttribute, NotificationSetting, Permission, Product, Role, Status, User, Target): single `ManageXxx` page, no form — view-only infolist, `getHeaderActions()` returns `[]`.
- `DashboardPanelProvider` uses `discoverResources()` — only root-level classes register as resources.
- `TargetResource` is Master-shaped but its folder lives under `Operational/` — a naming/location mismatch, not a bug. Check for hardcoded path references before "fixing" it.
- Canonical root class: composes `Form`/`Table`/`Infolist`/`Filters` (+ optional `TotalXxxCalculation`) traits plus `HasResourcePermissions` + `HasExtraAttributesManagement`; defines `form()`/`infolist()`/`table()`/`getEloquentQuery()` (eager-loads + `withoutGlobalScopes`)/`getPages()`/`getRelations()`/`getNavigationGroup()`/`getNavigationBadge()` (via `SmartCacheManager`).

## Conventions index

- **Naming**: form field `getXxxField()`, table column `showXxx()`, infolist entry `viewXxx()`, filter `getXxxFilter()`.
- **Forms/infolists**: uniform two-tab structure across all 8 operational resources; `getExtraAttributesFormTab()`/`getExtraAttributesInfolistTab()` always last; `->columns(3)` on the Tab, never the Schema root; `->columnSpanFull()` on Tabs; translate every user-facing string; new tabs get `tab_*` keys in all 3 locale files. Full structure: `filamentPattern.md`.
- **Model traits** (`app/Models/Traits/General/`): `Relationships` (`creator()`/`updater()`), `UserStamps` (auto user_id/updated_by_id), `HasCustomAttributes` (EAV morphMany), `Localization` (localized name accessor), `HasScope` (`scopeActive`) — `modelsPattern.md`.
- **Filament traits**: `HasResourcePermissions` (maps all Filament permission checks to Spatie; prefix `Str::snake(class_basename($model))`, actions view/create/edit/delete; **no `app/Policies/`**), `HasExtraAttributesManagement`, `HandleActivation` (bulk activate/deactivate), `ExportDefaults` (filename + row limit) — `filamentPattern.md`.
- **EAV**: `EntityAttribute` polymorphic (`entity_type` + `entity_id`), `value` JSON-cast. Two intentional entry points: `ManageCustomAttributesAction` KeyValue modal (`customAttributes()`) and the Repeater form tab (`extraAttributes()`) — same relation, different alias. `EntityAttributeResource` is view-only.
- **SoftDeletes models** require `withoutGlobalScopes([SoftDeletingScope::class])` in the resource's `getEloquentQuery()`.
- **Status**: shared polymorphic lookup (`type`/`english_type` + names). `Status::findBy($type, $englishName)` via the `StatusFinder` trait; each model scopes queries with `TYPE_*` constants.
- **Caching**: `SmartCacheManager::remember($model, $filters, $minutes, $cb)` / `::invalidate($model)` for any new model-scoped cache — `servicesPattern.md` for the full inventory (incl. `DashboardStats`/`AnalyticsService`, which use different strategies).
- **Observers** (registered in `AppServiceProvider::boot()`): `PurchaseRequestObserver` (cascades Authorized/Declined status to child items), `CategoryObserver`, `StatusObserver` (invalidates `StatusWorkflow` cache) — `observersPattern.md`.
- **Global helpers** (`app/Utils/helpers.php`): `tabBadge`, `maybeJalali`/`isJalaliCalendar`/`adaptiveDate` (the `calendar_type` contract), `delimiter`/`preciseNumber`, `getLocalizedName`, `toPersianDate`/`toGregorianDate`/`toYmdDate`, `clearApplicationCaches`/`cacheApplicationConfig`/`resetApplicationCache` — signatures in `helpersPattern.md`.

## Critical non-obvious rules

1. **Role hierarchy is inverted from the enum names**: `admin_junior` (⭐, one star) is the actual highest-trust tier project-wide; `admin_senior` (⭐⭐⭐) is lower. Verify seniority via the Spatie `roles` relation's real assigned-permission counts — never via `*_JUNIOR`/`*_SENIOR` case names, never via the legacy `users.role` column (unrelated free-text values; intentionally kept in `User::$fillable` as the avatar display fallback only — `UserImage::getFilamentAvatarUrl()`).
2. **RelationManager child models** (Attachment, the *Item models, Specification, CorrespondenceRecipient) are not resources — they inherit their parent's permission; the seeder must never create `{child}.{action}` rows.
3. **Panel-wide JS/CSS must be registered in `FilamentAssets.php`** via `Css::make()`/`Js::make()` + `Vite::asset()` — `resources/js/app.js`'s plain `@vite()` loads only on the landing-page route, not panel pages.
4. **Assets are referenced dynamically**: `resources/js/app.js` has a deliberate `import.meta.glob` catch-all so the Vite manifest includes every file under `resources/fonts/`+`resources/img/` (some are referenced only via runtime PHP string interpolation). Zero grep hits is NOT proof an asset is dead — clear `public/build/` + `node_modules/.vite` and run a fresh `npm run build` before deleting anything there.
5. **Never run the cache helpers synchronously inside a Livewire action** — clearing compiled views/Filament's component registry mid-render breaks the rendering component. Defer with `dispatch(fn () => resetApplicationCache())->afterResponse()`; a `Notification::make()->send()` before the dispatch still shows immediately.
6. **Don't embed a Livewire component via a Filament topbar render hook** (`GLOBAL_SEARCH_AFTER`/`BODY_START`) expecting page-component behavior — one such attempt never rendered client-side, root cause never isolated. The working equivalent: a List-page header Action + modal (`HasDeskReferenceAction`).
7. **No `ForceDeleteAction`/`ForceDeleteBulkAction` anywhere** — permanent delete is banned app-wide; soft-delete + `RestoreAction` covers recovery (enforced by an integrity test).
8. **CSS**: use existing `--custom-*`/`--google-*` tokens, never hardcode colors that have a variable; `.glass` and the 3D/glassmorphism utilities are removed — don't re-implement; never restructure `.fi-simple-layout`/`.fi-simple-main` (login background lives in `::before` pseudo-elements, not the blade view). Full reference: `stylesPattern.md`.
9. **Alpine**: pure-function factories (no class syntax); no `document.querySelector` inside data functions (use `$refs`/`$el`); lazy-init Audio/heavy objects; `window.__alpine_running` guard before starting Alpine; `window.dispatchEvent(new CustomEvent(...))` for cross-component communication. Full reference: `scriptPattern.md`.
10. **Blade**: `$isRtl` (bool prop, computed once at page root) is the single source of RTL decisions; sub-components receive only the props they need; the loader overlay is always `dir="ltr"`. Full reference: `viewsPattern.md`.
11. **`.env`'s `APP_URL` must match the port `php artisan serve` actually binds** — every signed/absolute URL is built from it. See `gotchasPattern.md`.

## HTTP surface

- **`SearchController`** (`/api/search/spotlight?q=`, `/api/search/chain?type=&id=`) — auth-guarded; `SearchService::PIPELINE` order is the single source of truth for the 8-model pipeline. Spotlight returns per-hit title/progress/breadcrumb; chain returns the attached pipeline around a record's `RegisteredOrder` hub(s) with batched `whereIn` label lookups (N+1-free). Full contract: `servicesPattern.md` (`SearchService`).
- **`InvoiceController`** (`/shipments/{shipment}/invoice/pdf`, route `shipments.invoice.pdf`, middleware `auth`) — reads the `commercial_invoice` EntityAttribute, delegates to `InvoicePdfService::download()`; 404 when none saved.
- **`WorkspaceController`** (`/workspace/records/{resource}?q=`) — record-pinning search for the landing-page workspace; config-driven whitelist of 9 resources in `config/workspace.php` (key must match the `$modules` array id in `App\Livewire\LandingPage\Workspace`); column lists cached 1 day; max 25 results. Pins persist in `localStorage['user_shortcuts']`.

## Landing page

Root view `resources/views/filament/landing-page.blade.php`: plain-Blade chrome (loader/switchers/widget/header includes, `resources/views/filament/landing-page/`) + three eager render-only Livewire tab bodies (`App\Livewire\LandingPage\{Workflow,Workspace,Search}` — no `wire:model`/`#[Lazy]`; interactivity stays in the pre-existing Alpine factories). Full inventory and ownership rules: `viewsPattern.md`.

The `Dashboard` panel page overrides the vendor content entirely: `AccountWidget` on top, then 3 tabs of 2 analytics widgets each via a `TABS` const — adding a widget is a one-line `TABS` entry (`widgetsPattern.md`).

## Configurators (`app/Configurators/`)

`LanguageSwitcher` (3-locale text-mode switcher), `FilamentRenderHooks` (topbar toggles — calendar, table-state, nav-dock, auto-hide, density, stacking, fullscreen, theme picker, work-actions; meta tags; theme-apply script), `FilamentAssets` (panel CSS/JS registration — see rule 3 above), `FilamentCustomLogin` (custom login page).

## Coding philosophy (every file, non-negotiable)

- **Zero noise comments** — no obvious comments, no block-comment headers; PHPDoc on public API methods only. No `dd()`/`dump()`/`var_dump()` left in code.
- Methods single-responsibility and short (~20 lines max — extract beyond).
- All DB queries through Eloquent; `->when()`/`->unless()` for conditional query building; eager-load in `getEloquentQuery()`, not per-field; cache expensive queries via `SmartCacheManager`.
- Tabs over nested Sections for forms with 5+ fields; translate every user-facing string in `form()`/`table()`/`infolist()`.
- CSS: new animations go in the file's existing keyframes block; never duplicate utilities that exist in `landing-page.css`/`fi-custom.css`; `will-change` only on GPU-accelerated animated elements.

## Standing Gotchas

→ `gotchasPattern.md` — cross-cutting entries (APP_URL port drift, midnight-boundary overdue comparisons, live-closure prefill helpers) plus an index pointing at the domain docs covering the rest. Add new durable lessons there, or in the governing domain doc if domain-specific — never back into this file.