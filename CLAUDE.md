# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **Usage:** Start every session with "Read CLAUDE.md first." to load full context before touching anything.
> **Maintenance:** This file stays a flat architecture + gotchas reference, not a chronological diary. When you learn something new and durable, add it to the relevant section above (or to **Standing Gotchas** at the bottom) — don't append a dated narrative. If a new entry supersedes or duplicates an existing one, edit it in place. Prune anything whose lesson is now obvious from the code itself.

---

## Pattern Documentation (authoritative — read these before editing their domain)

Co-located pattern files are the canonical, verified reference for their domains. **Where a pattern file conflicts with CLAUDE.md, the pattern file wins** — CLAUDE.md's own reference sections are a convenience summary, not the source of truth.

| Domain | File | Covers |
|---|---|---|
| Filament resources | `app/Filament/filamentPattern.md` | Trait-based schema composition, two-tab form/infolist, EAV dual-entry-points, `HasResourcePermissions` (no Policies), `SmartCacheManager` badges, `Status`/`StatusFinder`, `getEloquentQuery` |
| Dashboard analytics widgets | `app/Filament/Widgets/widgetsPattern.md` | Tabbed `Dashboard` page, `AnalyticsService` caching contract, per-widget data lineage, in-app metric legends |
| Services layer | `app/Services/servicesPattern.md` | Full inventory of all 15 `app/Services/*.php` classes — public APIs, consumers, caching/locale gotchas |
| Model layer / migrations | `app/Models/modelsPattern.md` | Model-trait composition, EAV model side, `Status`/`StatusFinder`, migration conventions |
| Global helpers / locale | `app/Utils/helpersPattern.md` | `app/Utils/helpers.php` signatures, `calendar_type` contract, RTL conventions |
| Localization | `lang/localizationPattern.md` | Locale/key structure, validation-message wiring, wording conventions, RTL/emoji placement, filter localization |
| CSS | `resources/css/stylesPattern.md` | The `--custom-*`/`--google-*`/`--gradient-*` token system, `.fi-*` morphing, load-bearing login CSS, the flat enterprise landing-page system, the loader, keyframes, Vite pipeline |
| JS / Alpine | `resources/js/scriptPattern.md` | `alpine/loader.js` lazy registration, Alpine factories, localStorage keys, lazy Audio, custom events |
| Views organization + Landing page | `resources/views/viewsPattern.md` | `components/`/`filament/`/`livewire/` folder convention, shared component library, landing-page component inventory, Filament-sync mechanism, boot sequence |

---

## Commands

```bash
# Start full dev stack (server + queue + Vite HMR) concurrently
composer run dev

# Run all tests (clears config first, uses SQLite in-memory)
composer run test

# Run a single test class or method
php artisan test --filter ExampleTest

# Lint / auto-fix code style (Laravel Pint)
./vendor/bin/pint

# Build frontend assets
npm run build

# Clear all caches (also available via GET /clear, admin_junior role only)
php artisan optimize:clear && php artisan filament:clear-cached-components

# Rebuild caches (also available via GET /cache, admin_junior role only)
php artisan config:cache && php artisan route:cache && php artisan filament:cache-components

# Clear + rebuild in one go (also available via GET /reset, admin_junior or admin_senior; also a "Reset Cache" item in the user menu)
```

`/clear`, `/cache`, `/reset` live in `routes/cache.blade.php` — plain PHP route-registration code despite the `.blade.php` extension (not a real Blade template), loaded explicitly via `bootstrap/app.php`'s `withRouting()` `then` callback rather than Laravel's automatic `web.php`/`api.php`/`console.php` discovery. Don't rename the extension. All three call the shared `clearApplicationCaches()` / `cacheApplicationConfig()` / `resetApplicationCache()` helpers (see Global Helpers).

---

## Stack

- **Laravel 12** · **PHP ≥ 8.2** · **Filament v4** (unified `Filament\Schemas\*` API) · **Livewire v3**
- **Alpine.js v3** — all frontend interactivity; components registered via `alpine:init`
- **Vite 6 + TailwindCSS v4** — asset pipeline
- **Spatie Laravel Permission v6** for roles/permissions
- **mokhosh/filament-jalali** for Persian (Jalali) calendar date-pickers
- **bezhansalleh/filament-language-switch** for EN / FA / FR locale switching
- Single Filament panel: `dashboard` (path `/dashboard`), SPA mode, dark-mode default

---

## Project Domain

BMS-CM is a B2B procurement management system covering the full purchase lifecycle:

```
Purchase Request → Proforma Invoice → Registered Order → Purchase Order → Payment → Shipment → Customs
```

Navigation groups (defined in `lang/en/resources/dashboard/strings.php`):
| Group key | Label |
|---|---|
| `operational_first` | 【1】 Purchase Requests Management |
| `operational_second` | 【2】 Order Registration Files |
| `operational_third` | 【3】 Files Financial Management |
| `operational_fourth` | 【4】 Logistics & Clearance |
| `base` | 【#】 Master Data |

---

## Resource Architecture

### File layout

```
app/Filament/Resources/
    XxxResource.php                          ← root class  (namespace App\Filament\Resources)
    Operational/XxxResource/
        Traits/Form.php
        Traits/Table.php
        Traits/Infolist.php
        Traits/Filters.php
        Traits/TotalXxxCalculation.php       ← optional live calculation helpers
        Enums/Status.php
        Exports/XxxExporter.php
        Pages/ListXxx.php / CreateXxx.php / EditXxx.php
        RelationManagers/…
    Master/XxxResource/
        Traits/Table.php / Infolist.php / Filters.php
        Pages/ManageXxx.php                  ← single-page (no create/edit routes)
    General/
        FormComponents.php                   ← getAttachmentsField() + others
        InfoComponents.php                   ← cross-resource relation badges
        TableComponents.php                  ← matching table columns
```

`DashboardPanelProvider` uses `discoverResources(…)` — only root-level classes register as actual resources.

### Operational vs Master

| | Operational | Master |
|---|---|---|
| Resources | PurchaseRequest, ProformaInvoice, RegisteredOrder, BankProfile, PurchaseOrder, Payment, Shipment, Custom, Correspondence | Bank, Category, Company, Currency, EntityAttribute, NotificationSetting, Permission, Product, Role, Status, User, Target |
| Pages | List + Create + Edit + (View via modal) | Single `ManageXxx` page |
| Header actions | Create button | `getHeaderActions()` returns `[]` |
| Form | Full editable form inside Tabs | No form — view-only via infolist |

> `TargetResource` is Master-shaped (single `ManageTargets` page, no create/edit routes) but its folder physically lives under `app/Filament/Resources/Operational/TargetResource/`, not `Master/` — a naming/location mismatch, not a bug. Don't "fix" the folder location without checking for hardcoded path references first.

### Root resource class (canonical structure)

```php
class PurchaseOrderResource extends Resource
{
    use TotalCalculation, PurchaseOrderForm, PurchaseOrderTable,
        PurchaseOrderFilters, PurchaseOrderInfolist,
        HasResourcePermissions, HasExtraAttributesManagement;

    public static function form(Schema $schema): Schema { … }
    public static function infolist(Schema $schema): Schema { … }
    public static function table(Table $table): Table { … }
    public static function getEloquentQuery(): Builder { … }  // eager-loads + withoutGlobalScopes
    public static function getPages(): array { … }
    public static function getRelations(): array { … }
    public static function getNavigationGroup(): ?string { … }
    public static function getNavigationBadge(): ?string { … }  // via SmartCacheManager
}
```

---

## Form & Infolist Conventions

### Naming conventions

| Component | Method prefix | Return type |
|---|---|---|
| Form field | `getXxxField()` | `TextInput`, `Select`, `DatePicker`, etc. |
| Table column | `showXxx()` | `TextColumn` |
| Infolist entry | `viewXxx()` | `TextEntry`, `RepeatableEntry` |
| Filter | `getXxxFilter()` | `SelectFilter`, `Filter`, `TrashedFilter` |

### Uniform form tab structure (ALL 8 operational resources)

```php
Tabs::make('ResourceName')
    ->tabs([
        Tab::make(__('resources/xxx/strings.form.tab_general'))
            ->icon('heroicon-o-…')
            ->schema([
                \Filament\Schemas\Components\Group::make()->schema([…])->columnSpan(['lg' => 2]),
                \Filament\Schemas\Components\Group::make()->schema([…])->columnSpan(['lg' => 1]),
            ])->columns(3),
        static::getExtraAttributesFormTab(),   // always last
    ])->columnSpanFull()
```

`->columns(3)` is on the **Tab**, not the root Schema. The root Schema has no column setting.

### Infolist tab structure

```php
Tabs::make('Details')->tabs([
    Tab::make(__('…infolist.tab_general'))->icon(…)->schema([Section::make()->schema([…])->columns(3)]),
    Tab::make(__('…infolist.tab_items'))->icon(…)->badge(…)->schema([…]),
    Tab::make(fn($record) => tabBadge(__('…tab_documents'), $record?->attachments->count(), 'info'))->schema([…]),
    static::getExtraAttributesInfolistTab(),   // always last
])->columnSpanFull()
```

### Shared General components

- `FormComponents::getAttachmentsField()` — standard multi-attachment FileUpload; handles `storeTemporary` → `processTemporaryFiles` pipeline; hydrates from `$record->attachments->pluck('path')`
- `InfoComponents::viewProformaInvoices()` / etc. — cross-resource relation badges (visible only when non-empty)
- `TableComponents::showProformaInvoices()` / etc. — matching table columns

---

## Model Traits (`app/Models/Traits/`)

| Trait | Effect |
|---|---|
| `General\Relationships` | `creator()` / `updater()` → `BelongsTo User` via `user_id` / `updated_by_id` |
| `General\UserStamps` | Boot: auto-sets `user_id` on creating, `updated_by_id` on updating |
| `General\HasCustomAttributes` | `customAttributes()` and `extraAttributes()` — both `morphMany(EntityAttribute, 'entity')` |
| `General\Localization` | `getLocalizedNameAttribute()` → `name` (FA) or `english_name` (other locales) |
| `General\HasScope` | `scopeActive()` — `where('is_active', true)` |
| `General\SellerEntity` | Scoped BelongsTo Company variants for seller / supplier / manufacturer |

Models with `SoftDeletes` require `withoutGlobalScopes([SoftDeletingScope::class])` in the resource's `getEloquentQuery()`.

---

## Filament Traits (`app/Filament/Traits/`)

**`HasResourcePermissions`** — Maps all Filament permission checks to Spatie Permission. Derives prefix automatically: `Str::snake(class_basename($model))`. Permissions follow `{prefix}.{action}` (view / create / edit / delete).

**`HasExtraAttributesManagement`** — Provides:
- `getExtraAttributesFormTab()` — Tab with `Repeater::make('extraAttributes')->relationship()`
- `getExtraAttributesInfolistTab()` — Tab with `RepeatableEntry` + count badge
- `getExtraAttributesFormSection()` — legacy collapsed Section (back-compat only)
- `buildExtraAttributesRepeater()` — shared builder; `formatStateUsing` required on value field because `EntityAttribute.value` is JSON-cast

**`HandleActivation`** — `getActivateBulkAction()` / `getDeactivateBulkAction()` for `is_active` toggling.

**`ExportDefaults`** — standardised filename (`APP-MODEL-HHmmss`), 1 000-row query limit.

---

## EAV / Custom Attributes System

`EntityAttribute` polymorphic EAV (`entity_type` + `entity_id`); `value` column is JSON-cast.

Two coexisting entry points (intentional):
1. **`ManageCustomAttributesAction`** — `KeyValue` modal; reads/writes via `$record->customAttributes()`
2. **`HasExtraAttributesManagement` Repeater** — inline form tab; reads/writes via `$record->extraAttributes()`

Same underlying relation, different alias to prevent closure conflicts.

`EntityAttributeResource` is view-only: no create/edit, `getHeaderActions()` returns `[]`.

---

## Permissions & Roles

Spatie Permission. No `app/Policies/` — all gates handled by `HasResourcePermissions`. Managed via `PermissionResource` / `RoleResource` Master Data resources.

**Role hierarchy is inverted from what the enum names imply**: `admin_junior` (⭐, one star) is the actual highest-trust/most-permissioned tier project-wide; `admin_senior` (⭐⭐⭐) is lower. Always verify seniority via the Spatie `roles` relation's real assigned-permission counts — never via the `*_JUNIOR`/`*_SENIOR` case name, and never via the legacy `users.role` column (holds unrelated free-text values like `'admin'`/`'agent'`/`'manager'` that never match a `UserRole` enum case).

The legacy `users.role` column is intentionally kept in `User::$fillable` and used only as the avatar display fallback in `UserImage::getFilamentAvatarUrl()` (Spatie `roles` is the real source for auth/seniority). Its presence there is deliberate, not an illegal pattern.

---

## Localization

Three locales: `en`, `fa` (Farsi/RTL), `fr`. See `lang/localizationPattern.md` for the full key-structure, validation-message, wording, and RTL/filter-localization convention reference — do not maintain a second copy here.

---

## Caching

`SmartCacheManager` (`app/Services/`) — per-model key registry around `Cache::remember`, enabling bulk invalidation. Use this for any new model-scoped cache:

```php
// Read
SmartCacheManager::remember('PurchaseOrder', ['user_id' => auth()->id(), 'type' => 'total_count'], 150, fn() => …);
// Bust all keys for a model
SmartCacheManager::invalidate('PurchaseOrder');
```

See `app/Services/servicesPattern.md` for the full service inventory — including `DashboardStats` and `AnalyticsService`, which each use a different caching strategy than `SmartCacheManager`.

---

## Services Layer

See `app/Services/servicesPattern.md` for the full inventory of all 15 services — public APIs, consumers, and caching/locale gotchas — do not maintain a second copy here.

---

## Global Helpers (`app/Utils/helpers.php`)

| Helper | Purpose |
|---|---|
| `tabBadge($label, $count, $color)` | `HtmlString` — label + inline `.tb-badge` span for Filament tabs |
| `maybeJalali($component)` | Wraps date component with `.jalali(true)` if session is Jalali |
| `delimiter($value, $currency, $decimals)` | Number format with optional currency prefix |
| `getLocalizedName($record, $relation)` | `name` (FA) or `english_name` based on locale |
| `toPersianDate($date, $withTime = false)` | Persian (Jalali) string; `$withTime = true` appends `- H:i:s` for audit timestamp columns |
| `toGregorianDate($date, $withTime = false)` | Gregorian string (`Y F d`); same `$withTime` option pairs with `toPersianDate` |
| `toYmdDate($record, $date)` | Formats as `Y-m-d` |
| `clearApplicationCaches()` | `cache:clear` + `config:clear` + `route:clear` + `view:clear` + `optimize:clear` + `filament:clear-cached-components` |
| `cacheApplicationConfig()` | `config:cache` + `route:cache` + `view:cache` + `filament:cache-components` |
| `resetApplicationCache()` | `clearApplicationCaches()` → `sleep(1)` → `cacheApplicationConfig()` |

**Never run the three cache helpers synchronously inside a Livewire action** — clearing compiled views/Filament's component registry mid-render breaks the component that's currently rendering (this broke the dashboard's own "Reset Cache" user-menu button once). Defer with `dispatch(fn () => resetApplicationCache())->afterResponse()`; a `Notification::make()->send()` called before the `dispatch()` still shows immediately since only the Artisan calls are deferred.

---

## Observers & Side Effects

Registered in `AppServiceProvider::boot()`:
- `PurchaseRequestObserver` — when `status_id` changes to `Authorized`/`Declined`, cascades matching status to all child purchase items
- `CategoryObserver` — category hierarchy logic

---

## Status Model

`Status` is a shared polymorphic lookup (`type` / `english_type` + `english_name` / `name`). Use `Status::findBy($type, $englishName)` (from `StatusFinder` trait). Each model defines `TYPE_*` constants to scope queries.

---

## HTTP Controllers

### `SearchController` (`/api/search/spotlight?q=`)

Spotlight search across all 8 operational models. For each model hit, returns:
- `title`, `subtitle` (record number), `progress` (% of non-null fields), `icon`, `color`, `id`
- `breadcrumb` object: `{proforma, order, logistics}` — each `upcoming | active | completed` for the pipeline status bar

### `SearchController::chain` (`/api/search/chain?type=&id=`)

Auth-guarded. Given an operational record (`type` = one of the 8 pipeline keys, `id` = record id), returns the **attached pipeline** — all 8 operational models, each with a green/red `attached` flag and a `records[]` list, plus a top-level `breadcrumb` (one `{state,label}` per model, `completed` when attached else `missing`). `SearchService::chain()` resolves the anchor to its `RegisteredOrder` hub(s) (via pivot for PR/PI/PO, `self` for RO, `belongsTo` for BP/Shipment/Custom, morph `targetable` for Payment), then gathers each model's records attached to those RO ids (Custom also via `shipment_id`, Payment via RO + PO `targetable`). Each record carries `identifier` (primary `*_number`/`*_no`), `identifiers[]` (all such columns), `progress` (completion %), `url` (edit route), `extras[]` (2–3 typed ultra-important trade fields per model — `text`/`date`/`money`/`company`/`bank`/`currency`; money pairs an amount column with a currency column and formats via `delimiter()`, company/bank/currency resolve to localized names, dates are Jalali when locale is `fa`; an extra may override its label via an explicit `'label'` translation when the column-derived key doesn't match the form's label), and `statuses[]` — **every** status column of that model (Shipment has 5, Custom has 3, others 1, ProformaInvoice 0), label from `__("resources/{key}/strings.form.{col_without_id}")`. All FK/Status label lookups are resolved in single batched `whereIn` passes over `$refs['status'|'company'|'bank'|'currency']` (N+1-free). The anchor is always included in its own model even when it has no RO links (shows only itself). PIPELINE order is the single source of truth (`SearchService::PIPELINE`), reused by `buildBreadcrumb`.

### `InvoiceController` (`/shipments/{shipment}/invoice/pdf`)

Auth-guarded. Reads `commercial_invoice` EntityAttribute from the Shipment, delegates to `InvoicePdfService::download()`. Returns 404 if no saved invoice exists.

Route name: `shipments.invoice.pdf` (defined in `routes/web.php`, middleware `auth`).

---

### `WorkspaceController` (`/workspace/records/{resource}?q=`)

Powers record-pinning in the landing-page workspace. Thin wrapper over `App\Services\WorkspaceSearchService`, which is config-driven via `config/workspace.php`:
- Validates against whitelist of 8 resources
- Searches across all table columns (or a restricted `search` list if defined) — `Schema::getColumnListing()` results are cached (`Cache::remember("workspace_columns:{connection}:{table}", 1 day, ...)`)
- Returns `{data: [{key, resourceId, recordId, label, subtitle, url}]}` — max 25 results
- `compose()` helper handles DateTimeInterface, BackedEnum, scalars

---

## Livewire Components

### `CalendarToggle` (`app/Livewire/CalendarToggle.php`)

Filament global-search area render hook (injected via `FilamentRenderHooks::configure()`). Toggles between Gregorian and Jalali. State stored in `session('calendar_type')`. Dispatches `calendar-toggled` Livewire event on toggle.

### `App\Livewire\LandingPage\{Workflow,Workspace,Search}`

The landing page's 3 tab bodies, each an eager render-only component (no `wire:model`/`wire:click`/`#[Lazy]` — interactivity stays in the pre-existing Alpine factories, unchanged). See `resources/views/viewsPattern.md`'s "Livewire components" section for the full mount-param/caching/ownership breakdown, and why lazy-loading and reactive `wire:model` transport were deliberately deferred (Livewire's `#[Lazy]` defaults to viewport-based `x-intersect` triggering, unsuitable for the `x-show`-hidden tab panels; a dual-Alpine-instance footgun in `alpine/loader.js` needs resolving first if either is ever added).

**Don't embed a Livewire component via a Filament topbar render hook** (`GLOBAL_SEARCH_AFTER`/`BODY_START`) expecting it to behave like a page component — one such attempt (a topbar Desk Reference dropdown) had correct server-side logic at every layer but never rendered client-side, and the root cause was never isolated. The working equivalent for topbar-triggered content is a plain List-page header Action + modal (see `HasDeskReferenceAction`).

---

## Configurators (`app/Configurators/`)

| Configurator | Purpose |
|---|---|
| `LanguageSwitcher` | Configures `bezhansalleh/filament-language-switch`; 3 locales, flag-only display, bottom-right outside panel |
| `FilamentRenderHooks` | Injects `CalendarToggle`, nav-dock toggle, topbar auto-hide toggle, and `<meta>` author/last-updated tags at various panel render hooks |
| `FilamentAssets` | Registers panel-wide JS (`nav-dock.js`, `topbar-autohide.js`) via `Js::make()` + `Vite::asset()` |
| `FilamentCustomLogin` | Custom login page configuration |

**Client-side JS meant to run inside the Filament panel itself (not the landing page) must be registered via `FilamentAssets.php`'s `Js::make()`** — `resources/js/app.js`'s plain `@vite()` only loads on the landing-page route, not panel pages. This has shipped silently broken once already; don't assume `app.js` covers panel-wide behavior.

---

## Vite / Asset Pipeline

**`vite.config.js` entry points:**
```
resources/css/app.css                       → Tailwind base (scans resources/views)
resources/css/fi-custom.css                 → Filament panel overrides
resources/css/layout/fonts.css              → Roboto + IranYekan @font-face
resources/css/landing-page.css              → Landing page design system
resources/js/app.js                         → Filament/Livewire JS
resources/js/filament/nav-dock.js           → Bottom-dock nav mode (panel-wide, not landing-page-only)
resources/js/filament/topbar-autohide.js    → Auto-hide topbar pin-state persistence (panel-wide)
```

**Static copies (not processed, served verbatim):**
```
resources/img/*    → public/img/
resources/audio/*  → public/audio/
resources/video/*  → public/video/
```

**`resources/js/app.js` has `import.meta.glob(['../fonts/**', '../img/**'])`** — a deliberate, return-value-discarded catch-all so Vite's manifest includes every file under those folders, needed because some assets are referenced only via dynamic PHP string interpolation (e.g. `UserImage.php`'s role-based avatar path) that Vite's static analysis can't see. Consequence: **a "zero grep hits" check is not proof an asset file is dead** — it can't see a path assembled at runtime from a variable. Before deleting any file under `resources/img/`, `resources/fonts/`, clear `public/build/` + `node_modules/.vite` and run a fresh `npm run build` to confirm nothing breaks, not just a literal-string grep.

---

## CSS Design System

See `resources/css/stylesPattern.md` for the full token/class reference (verified current) — do not maintain a second copy here.

---

## JavaScript / Alpine.js Architecture

See `resources/js/scriptPattern.md` for the full Alpine factory/localStorage/event reference (verified current) — do not maintain a second copy here.

---

## View / Blade Component Tree

### Landing Page Architecture

```
views/filament/landing-page.blade.php                      ← root (Filament Page view)
    → @include('filament.landing-page.loader')             ← 2900ms full-screen loader
    → @include('filament.landing-page.switchers')          ← fixed top-right: lang + dark + logout + widget toggle
    → @include('filament.landing-page.widget')              ← floating triWidget panel (clock/timer/music)
    → @include('filament.landing-page.header')              ← underline tab switcher (Customize/Workflow/Search)
    → @livewire('landing-page.workspace')                  ← Customize tab, App\Livewire\LandingPage\Workspace
    → @livewire('landing-page.workflow')                   ← Workflow tab, App\Livewire\LandingPage\Workflow
    → @livewire('landing-page.search')                     ← Search tab, App\Livewire\LandingPage\Search
```

The three tab bodies are dedicated Livewire components (`app/Livewire/LandingPage/*.php` + `resources/views/livewire/landing-page/*.blade.php`); the shell chrome (loader/switchers/widget/header) is plain Blade + Alpine under `resources/views/filament/landing-page/`, matching this project's `resources/views/filament/{feature}/` convention (siblings: `desk-reference/`, `partials/`, `widgets/`). See `viewsPattern.md` for the full component inventory.

### Key Blade Props Pattern

`$isRtl` — `bool` passed to all sub-components; used for `{{ $isRtl ? 'right' : 'left' }}` anchor decisions, chevron rotation classes, and `slide-left/right` animation direction.

### Search Tab (`App\Livewire\LandingPage\Search`, view `resources/views/livewire/landing-page/search.blade.php`)

Uses the registered `search` Alpine factory (`x-data="search"`): `searchQuery`, `isSearching`, `results[]`, `selectedResult`, `byUser`, `chain[]`, `chainLoading`, `chainError`, `breadcrumb` (8 keys, one per operational model).
- `performSearch()` → `GET /api/search/spotlight?q=…`, debounced 500ms. Results carry `type`+`id`.
- `selectResult(result)` → sets `selectedResult`, lazily loads `GET /api/search/chain?type=…&id=…`, swaps `breadcrumb` from the chain response on success. `clearSelected()` resets both.
- Breadcrumb bar stays off (`x-show`) until a record is selected and the chain has loaded — driven only by the chain response, never by spotlight search-match states.
- Results grid, detail panel, **Open & Edit Record** button, then the attached-pipeline chain (see `SearchController::chain` above).

### Workspace Tab (`App\Livewire\LandingPage\Workspace`)

Module accordion (15 modules, 8 operational searchable + 7 master non-searchable) + records accordion (resource-chip picker → debounced 300ms search → `GET /workspace/records/{resource}?q=…`). Pins persist in `localStorage['user_shortcuts']` (`{modules:[...ids], records:[...{key,resourceId,recordId,label,subtitle,url}]}`).

---

## Workspace Config (`config/workspace.php`)

Whitelist for record-pinning; one entry per pinnable resource:

```php
'purchaseRequests' => [
    'model'    => PurchaseRequest::class,
    'route'    => 'filament.dashboard.resources.purchase-requests.edit',
    'title'    => ['pr_number'],
    'subtitle' => ['urgency_level', 'required_by_date'],
    // 'search' => ['pr_number', 'notes'],  // optional column restriction
],
```

Key must match the `id` in the `$modules` array built by `App\Livewire\LandingPage\Workspace`.

---

## Filament Panel Pages

### `LandingPage`

```php
class LandingPage extends Page
{
    protected static string $layout = 'layout';   // custom layout (not panel layout)
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'filament.landing-page';
    // mount(): handles ?locale= query param, sets session + app locale
    // getViewData(): merges DashboardStats::get() as $counts
}
```

### `Dashboard`

Overrides the vendor `Dashboard` page's `content()` entirely (not a flat widget grid): `AccountWidget` always visible at top, then 3 `Tab`s of 2 analytics widgets each, driven by a `TABS` const array. See `widgetsPattern.md` for the full widget/data-lineage breakdown. Adding a widget is a one-line addition to `TABS`; adding a category is a new `Tab::make()` entry.

---

## Coding Philosophy

These rules apply to **every file** in this project. Non-negotiable.

### Zero Noise Comments

- **No obvious comments.** Never write `// Get the user`, `// Return the result`, or `// Loop through items`.
- **No block-comment headers** (`/**** SECTION ****/`) unless the file has 200+ lines of unrelated concerns.
- Allowed comments: non-obvious algorithm explanations, intentional `//->method()` commented-out lines being preserved, and PHPDoc on public API methods only.

### PHP / Laravel

- Methods stay **single-responsibility and short**. If a method exceeds ~20 lines, extract.
- **No `dd()`, `dump()`, `var_dump()`** left in code.
- All DB queries go through Eloquent; raw `DB::` only when Eloquent cannot express it.
- `->when()` and `->unless()` for conditional query building — no `if ($x) $query->where(…)` outside query chains.
- Eager-load relations in `getEloquentQuery()`, not in individual field/column definitions.
- Cache expensive queries. Use `SmartCacheManager` for model-scoped caches.

### Filament / PHP Forms

- **Tabs over nested Sections** for forms with 5+ fields. All 8 operational resources follow the two-tab pattern.
- **`->columnSpanFull()`** on the `Tabs` component always.
- **`->columns(3)`** on the Tab, not the Schema root.
- Translate every user-facing string — no hardcoded English strings in `form()` / `table()` / `infolist()`.
- Add `tab_*` keys to all 3 locale files when adding a new tab.

### CSS

- Use existing design tokens — never hardcode colors that have a `--custom-*` or `--google-*` variable.
- New animations go in the same keyframes block at the bottom of the file they belong to.
- `.glass` and the other glassmorphism/3D utilities (`.card-3d`, `.shimmer-effect`, `.floating`, `.glow-orb`, `.badge-float`, etc.) are **removed** — do not re-implement or reference them. Use the flat `--custom-*`/`--google-*` token surfaces instead (see `resources/css/stylesPattern.md`).
- Never duplicate utility classes that already exist in `landing-page.css` or `fi-custom.css`.
- `will-change: transform/opacity` on animated elements that use GPU-accelerated properties only.
- **Never restructure `.fi-simple-layout` / `.fi-simple-main`** — these are Filament's auth wrappers. The login background effect lives in `::before` pseudo-elements, not in the blade view.

### JavaScript / Alpine.js

- All Alpine components are **pure functions** returning a plain object — no class syntax.
- **No `document.querySelector` inside Alpine data functions** — use `$refs` or `$el`.
- Audio/heavy objects are **lazy-initialized** (instantiated on first use, not in `init()`).
- `window.__alpine_running` guard is checked before starting Alpine — maintain this pattern.
- Custom events use `window.dispatchEvent(new CustomEvent(…))` for cross-component communication.
- `localStorage` keys: `theme` (dark/light), `lp_tab` (active landing tab), `user_shortcuts` (workspace pins) — see `scriptPattern.md` for the full key inventory.

### Blade / Views

- `$isRtl` is the single source of truth for RTL layout decisions in Blade.
- Sub-components receive only the props they need — no prop drilling of the entire `$counts` array if only one count is needed.
- Loader overlay always `dir="ltr"` regardless of locale.

---

## Standing Gotchas & Non-Obvious Learnings

Durable lessons that would otherwise cost a future session real time to rediscover. Not a changelog — if something here becomes obvious from reading the code, or gets promoted into a reference section above, remove it from this list.

- **Tooltip reliability**: for elements with no server round-trip (pure Alpine store + `localStorage`, e.g. nav-dock/topbar-pin toggles), render two static `.raw`-string button variants (one per state) switched via `x-show`, rather than a reactive `x-tooltip="{content: <js-expr>}"` object — the latter races/renders empty on first paint. `calendar-toggle`'s tooltip works because it's a literal PHP string baked in by Livewire's server render, not because of special client-side handling — don't copy its markup shape onto a client-only toggle expecting the same reliability for the wrong reason.
- **Sidebar-collapse chevron next to the topbar logo is Filament's own shipped default** when a panel has a topbar (`vendor/filament/filament/resources/views/livewire/sidebar.blade.php` only renders its own copy of that button when `! $hasTopbar`) — not a layout bug. Check the vendor source before "fixing" this again.
- **Dead-code sweeps on assets need a real build, not just grep** — see the Vite section above (`import.meta.glob` catch-all). A prior sweep deleted avatar SVGs based on zero grep hits and broke the build; they were dynamically referenced.
- A Filament `DatePicker` gets an **implicit `date` validation rule** injected internally — not visible as a `->date()` call in this codebase's own resource code. See Localization.
