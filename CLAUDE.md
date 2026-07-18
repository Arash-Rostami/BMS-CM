# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **Usage:** Start every session with "Read CLAUDE.md first." to load full context before touching anything.  
> **Maintainance:** After each session append only the changed items under [Latest Changes](#latest-changes). Do not re-document unchanged things.

---

## Pattern Documentation (authoritative — read these before editing their domain)

Three co-located pattern files are the canonical, verified reference for their domains. They were verified against source on `feature/landing-page-enterprise-redesign` (2026-07-18). **Where a pattern file conflicts with CLAUDE.md, the pattern file wins.**

| Domain | File | Covers |
|---|---|---|
| Filament resources | `app/Filament/filamentPattern.md` | Trait-based schema composition, two-tab form/infolist, EAV dual-entry-points, `HasResourcePermissions` (no Policies), `SmartCacheManager` badges, `Status`/`StatusFinder`, `getEloquentQuery`, localization |
| CSS | `resources/css/stylesPattern.md` | The `--custom-*`/`--google-*`/`--gradient-*` token system, `.fi-*` morphing, load-bearing login CSS, the flat enterprise landing-page system, the loader, keyframes, Vite pipeline |
| JS / Alpine | `resources/js/scriptPattern.md` | `alpine-loader.js` lazy registration, `landingPage`/`triWidget`/`search`/`workspace` factories, localStorage keys, lazy Audio, custom events, Ctrl+K wiring |

> ⚠️ **Known stale sections in CLAUDE.md (post-2026-07-18 enterprise redesign):** the *Landing Page Design System* utility-class catalog and the *Animation Keyframes* list below still name classes/keyframes that were removed (`.card-3d`, `.glass`, `.shimmer-effect`, `.floating`, `.glow-orb`, `.badge-float`, `.workflow-connector`, `.thread-path`, `.workflow-node`, `.tri-widget-panel`, `.btn-wrapper`/`.badge-float`/`.btn-gradient`, `.icon-container`, `.btn-inline`, `.pulse-ring`, `.shadow-elegant`; keyframes `float`/`glow`/`shimmer`/`pulse-ring`/`fadeSlide`/`slide`/`draw-thread`/`pulse-amber`). The JS section also has stale file names and defaults (`workspace.js`→`workspace-alpine.js`, `search-tab.blade.php`→`search.blade.php`, `activeTab:'customize'`→`'workflow'`, `darkMode` default, timer presets 5/10/15/25→5/10/15/30/60, 3-key breadcrumb→8-key). Trust `stylesPattern.md` / `scriptPattern.md` for these, not the sections below.

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

# Clear all caches (also available via GET /clear when authenticated)
php artisan optimize:clear && php artisan filament:clear-cached-components

# Rebuild caches (also available via GET /cache when authenticated)
php artisan config:cache && php artisan route:cache && php artisan filament:cache-components
```

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
| Resources | PurchaseRequest, ProformaInvoice, RegisteredOrder, BankProfile, PurchaseOrder, Payment, Shipment, Custom | Bank, Category, Company, Currency, EntityAttribute, NotificationSetting, Permission, Product, Role, Status, User |
| Pages | List + Create + Edit + (View via modal) | Single `ManageXxx` page |
| Header actions | Create button | `getHeaderActions()` returns `[]` |
| Form | Full editable form inside Tabs | No form — view-only via infolist |

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

---

## Localization

Three locales: `en`, `fa` (Farsi/RTL), `fr`.

Translation paths:
- Resources: `lang/{locale}/resources/{camelCaseResource}/strings.php`
- Actions: `lang/{locale}/actions/{camelCaseAction}.php`

Key structure: `general` → `form` → `table` → `filters` → `infolist`. Tab labels use `tab_` prefix in both `form` and `infolist` groups.

**Rule:** When adding a new form tab, add the translation key to **all three locale files** simultaneously.

`maybeJalali($component)` — wraps any date-picker to switch it to Jalali mode based on `session('calendar_type')`.

---

## Caching

`SmartCacheManager` (`app/Services/`) — per-model key registry around `Cache::remember`, enabling bulk invalidation:

```php
// Read
SmartCacheManager::remember('PurchaseOrder', ['user_id' => auth()->id(), 'type' => 'total_count'], 150, fn() => …);
// Bust all keys for a model
SmartCacheManager::invalidate('PurchaseOrder');
```

`DashboardStats::get()` — per-user 120s cache of 8 module counts (used by LandingPage). Keyed `dashboard_counts:{userId}`.

---

## Services Layer (`app/Services/`)

| Service | Purpose |
|---|---|
| `CodeGenerator` | Auto-generates sequential codes (`PR-250612`, `PO-250612-2`, etc.). URL-segment-based routing to model/prefix map. |
| `DashboardStats` | Counts all 8 operational models; user-scoped 120s cache |
| `FileUploadManager` | `storeTemporary()` → `processTemporaryFiles()` pipeline for attachments; moves temp files to permanent location, persists Attachment records |
| `InvoicePdfService` | mPDF commercial invoice PDF — Persian uses IranYekan + RTL, others use DejaVu + LTR. `generate(array $invoice, string $locale)` → `Mpdf`; `download()` → `Response`. fontDir merged with `resource_path('fonts')`. |
| `NotificationEvaluator` | Evaluates notification rules against records |
| `PermissionLabeler` | Human-readable labels for Spatie permission strings |
| `PersianCalendar` | Persian calendar utilities |
| `SmartCacheManager` | Model-keyed cache registry with bulk invalidation |

---

## Global Helpers (`app/Utils/helpers.php`)

| Helper | Purpose |
|---|---|
| `tabBadge($label, $count, $color)` | `HtmlString` — label + inline `.tb-badge` span for Filament tabs |
| `maybeJalali($component)` | Wraps date component with `.jalali(true)` if session is Jalali |
| `delimiter($value, $currency, $decimals)` | Number format with optional currency prefix |
| `getLocalizedName($record, $relation)` | `name` (FA) or `english_name` based on locale |
| `toPersianDate($date)` | Formats as Persian (Jalali) string |
| `toYmdDate($record, $date)` | Formats as `Y-m-d` |

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
- `title`, `subtitle` (record number), `progress` (% of non-null fields), `is_binary`, `icon`, `color`
- `breadcrumb` object: `{proforma, order, logistics}` — each `upcoming | active | completed` for the pipeline status bar

### `InvoiceController` (`/shipments/{shipment}/invoice/pdf`)

Auth-guarded. Reads `commercial_invoice` EntityAttribute from the Shipment, delegates to `InvoicePdfService::download()`. Returns 404 if no saved invoice exists.

Route name: `shipments.invoice.pdf` (defined in `routes/web.php`, middleware `auth`).

---

### `WorkspaceController` (`/workspace/records/{resource}?q=`)

Powers record-pinning in the landing-page workspace. Config-driven via `config/workspace.php`:
- Validates against whitelist of 8 resources
- Searches across all table columns (or a restricted `search` list if defined)
- Returns `{data: [{key, resourceId, recordId, label, subtitle, url}]}` — max 25 results
- `compose()` helper handles DateTimeInterface, BackedEnum, scalars

---

## Livewire Components

### `CalendarToggle` (`app/Livewire/CalendarToggle.php`)

Filament global-search area render hook (injected via `FilamentRenderHooks::configure()`). Toggles between Gregorian and Jalali. State stored in `session('calendar_type')`. Dispatches `calendar-toggled` Livewire event on toggle.

---

## Configurators (`app/Configurators/`)

| Configurator | Purpose |
|---|---|
| `LanguageSwitcher` | Configures `bezhansalleh/filament-language-switch`; 3 locales, flag-only display, bottom-right outside panel |
| `FilamentRenderHooks` | Injects `CalendarToggle` view after global search bar via `PanelsRenderHook::GLOBAL_SEARCH_AFTER` |
| `FilamentCustomLogin` | Custom login page configuration |

---

## Vite / Asset Pipeline

**`vite.config.js` entry points:**
```
resources/css/app.css          → Tailwind base (scans resources/views)
resources/css/fi-custom.css    → Filament panel overrides
resources/css/layout/fonts.css → Roboto + IranYekan @font-face
resources/css/landing-page.css → Landing page design system
resources/js/app.js            → Filament/Livewire JS
```

**Static copies (not processed, served verbatim):**
```
resources/img/*    → public/img/
resources/audio/*  → public/audio/
resources/video/*  → public/video/
```

---

## CSS Design System

### Token System (`resources/css/fi-custom.css`)

**Color palette:**
```css
--custom-first:       #F8FAFC   /* lightest surface */
--custom-second:      #BAC8D3
--custom-second-mid:  rgba(186,200,211, 0.7)
--custom-second-light:rgba(186,200,211, 0.4)
--custom-third:       #8899A6
--custom-third-light: rgba(136,153,166, 0.2)
--custom-fourth:      #9AA6B2   /* muted text */
--filament-dark:      #09090B   /* dark bg */
--custom-neutral:     /* sidebar hover bg */
--custom-neutral-light: /* tabs hover bg */
```

**Google Material palette** (used for dark gradients and active states):
```css
--google-first-light/dark   /* purple  #6750A4 / #5C6AC4 */
--google-second-light/dark
--google-third-light/dark
--google-fourth-light/dark  /* hover accent */
```

**Named gradient variables (15 total):**
```css
--gradient-primary        /* main brand */
--gradient-secondary      /* active tab */
--gradient-neutral        /* sidebar items */
--gradient-neutral-light
--gradient-google-deep    /* dark mode active tab */
--gradient-hero           /* landing page hero */
/* …plus surface/shimmer/glow variants */
```

**Material Design motion & elevation:**
```css
--md-motion:       cubic-bezier(0.2, 0, 0, 1)    /* enter/standard */
--md-motion-exit:  cubic-bezier(0.4, 0, 1, 1)    /* exit */
--md-elevation-1/2/3        /* light mode box-shadows */
--md-elevation-1/2/3-dark   /* dark mode variants */
```

### Key Component Styles

**`.fi-body`** — Body background:
- Light: `#F8FAFC` + radial gradient + `gradient-shift` animation
- Dark: `#09090B` + lower ellipse gradient

**`.fi-topbar`** — `--custom-third-mid` bg, `box-shadow` elevation, `max-height: 20px`, `slide-in-top` animation.

**`.fi-sidebar-item > a`** — `--gradient-neutral` bg; hover: `translateX(3px)` + `--custom-neutral` bg + elevation  
**`.fi-sidebar-item.fi-active > a::before`** — pulsing 🔹 emoji indicator

**`.fi-section`** — `--custom-third-light` bg + elevation-1; hover → elevation-2 (suppressed in modals and widgets).

**`.fi-ta` (tables)** — border-none + elevation; `thead th` uses `--custom-third`; pagination uses `--custom-second-light`.

**`.fi-modal-window`** — `--custom-neutral` bg, `border-radius: 16px`, elevation-3; backdrop: `rgba(217,234,253,0.85)` light / `rgba(24,24,27,0.9)` dark. When modal opens: sidebar + topbar get `opacity: 0; pointer-events: none`, scrollbars hidden.

**`.fi-tabs`** — `overflow-x: auto` + thin custom scrollbar + `padding: 4px`  
**`.fi-tabs-item`** — `--custom-fourth` color; hover: `--custom-neutral-light` + elevation-1; active: `--gradient-secondary` + elevation-1; dark active: `--gradient-google-deep`

**`.fi-simple-layout`** (login page) — gradient background + webp image overlay (`video/2.webp` light, `video/1.webp` dark) with mask-image fade + grain noise texture (SVG data URI with `grain-drift` animation)  
**`.fi-simple-main`** (login card) — absolute positioned, `border-radius: 16px`, elevation-2 → elevation-3 on hover

> ⚠️ **Login CSS is load-bearing.** `resources/video/1.webp` and `2.webp` are static/animated WebP images used as CSS `background-image` on the `::before` pseudo-element — they are **not** HTML `<video>` elements. The CSS path `url("../../video/2.webp")` resolves correctly after Vite build (output in `public/build/assets/`, two `../` hops reach `public/video/`). Do **not** restructure `.fi-simple-layout` or `.fi-simple-main` without restoring this pseudo-element. After any `fi-custom.css` edit: `npm run build` + hard-refresh (`Ctrl+Shift+R`).

**Custom scrollbar** — 8px wide, `--custom-third` thumb, SVG arrow buttons, `--google-fourth-light` hover

**Badge classes** (via `tabBadge()` helper):
```css
.tb-badge                     /* base inline-flex badge */
.tb-badge.tb-info             /* blue border/bg/color */
.tb-badge.tb-success          /* green */
.tb-badge.tb-warning          /* amber */
.tb-badge.tb-danger           /* red */
```

**`.fi-sc-text`** (helper text) — gradient left-border `::before` indicator; expands on hover.

### Animation Keyframes (`fi-custom.css`)
| Name | Effect |
|---|---|
| `gradient-shift` | Body bg animated gradient shift |
| `jello-horizontal` | Jelly bounce on active sidebar |
| `fade-in` | Opacity 0→1 |
| `pulse` | Scale pulse |
| `slide-in-top` | Top bar enter |
| `slide-in-down` | Dropdown enter |
| `slide-right/left/down` | Connector arrows in workflow |
| `grain-drift` | Noise texture drift on login page |

### Landing Page Design System (`resources/css/landing-page.css`)

**Font setup:**
- Default: `Roboto` (woff2/woff from `/resources/fonts/`)
- Persian override: `IranYekan` (woff/ttf) — triggered by `html[lang="fa"]` and `.fi-body`

**Key utility classes (current, post-enterprise-redesign):**

| Class group | Purpose |
|---|---|
| `.widget` | 2-declaration utility (`direction: ltr; position: relative`) — NOT a glass panel |
| `.lp-surface` `.lp-bar` `.lp-divider` `.lp-tab` | flat landing-page surface primitives |
| `.chip` `.tab` `.tab-active` | chip + tab switcher (`.tab-active` uses the indigo/cyan accent) |
| `.range` `.input-inline` `.truncate-2` | range input, inline input, 2-line clamp |
| `.stepper-connector` `.custom-scrollbar` | workflow stepper connector, scrollbar |

> ❌ **Removed in the 2026-07-18 enterprise redesign — do NOT reintroduce:** `.card-3d`, `.glass`, `.tri-widget-panel`, `.shimmer-effect`, `.floating`, `.glow-orb`, `.pulse-ring`, `.shadow-elegant`, `.workflow-connector`, `.thread-path`, `.workflow-node`, `.btn-wrapper`, `.badge-float`, `.btn-gradient`, `.icon-container`, `.btn-inline`. The landing page is now flat, bordered, data-dense, Filament-native. See `resources/css/stylesPattern.md` for the full removed-class list and the rationale.

**Accent convention:** indigo-600 (`#4f46e5`) light / cyan-400 (`#00d4ff`) dark is the accent for the **loader, `.range`, and `.tab-active` only** — not the whole landing page. Wider landing surfaces (`.lp-surface`, `.lp-bar`, `.lp-tab`, `.light`/`.dark`) use the shared `--custom-*`/`--google-*` tokens. `.light`/`.dark` use a soft ellipse radial-gradient glow — the hairline dot-grid lives only on `.ldr-grid` (loader).

### Loader System (`landing-page.css` + `loader.blade.php`)

Full-screen overlay; auto-hides after **2900ms** via Alpine `x-init="setTimeout(() => showing = false, 2900)"`.

| Class | Element |
|---|---|
| `.loader-overlay` | Full-screen container |
| `.ldr-grid` | Animated grid background |
| `.ldr-scan` | Horizontal scan line |
| `.ldr-glow` | Central glow effect |
| `.ldr-c` + `.ldr-c-tl/tr/bl/br` | Corner bracket decorations |
| `.ldr-eyebrow` | "Trade App" label |
| `.ldr-logo` + `.ldr-letter` | "BMS" letters — staggered via `--i` CSS variable |
| `.ldr-subtitle` | "Business Management System" |
| `.ldr-fill` | Progress bar with glowing dot |
| `.ldr-status` | "Loading Resources" text |

Light theme: indigo (`#4f46e5`); dark theme: cyan (`#00d4ff`).

### Animation Keyframes (`landing-page.css`)
Only the 8 loader keyframes remain in `landing-page.css`: `lIn`, `lUp`, `lSub`, `lFill`, `lScan`, `lCorner`, `lGlow`, `lGrid`. (`float`, `glow`, `shimmer`, `pulse-ring`, `pulse`, `fadeSlide`, `slide`, `draw-thread`, `pulse-amber` were removed in the 2026-07-18 redesign — `pulse`/`slide-*` live in `fi-custom.css`, not here.)

---

## JavaScript / Alpine.js Architecture

### Entry Point (`resources/js/alpine-loader.js`)

```js
if (document.querySelector('[x-data="landingPage()"]')) Alpine.data('landingPage', landingPage)
if (document.querySelector('[x-data="triWidget()"]'))   Alpine.data('triWidget', triWidget)
Alpine.data('search', search)
Alpine.data('workspace', workspace)
if (!window.__alpine_running) { Alpine.start(); window.__alpine_running = true }
```
`landingPage` + `triWidget` are lazy-registered (DOM-presence guard); `search` + `workspace` are **both** always registered. `window.__alpine_running` guards against double-init.

### `landingPage()` (`resources/js/landing-page-alpine.js`)

```js
{
  darkMode: false,
  activeTab: 'workflow',
  widgetOpen: false,
}
```
`init()` reads `theme` from localStorage (truthy set `{'1','true','dark','on'}`) and `lp_tab` (default `'workflow'`, only overrides when non-null). `$watch('darkMode')` persists `theme` + toggles `html.dark`; `$watch('activeTab')` persists `lp_tab`. Listens for `dark-mode-toggled` (see Custom Events). The Three.js torus background + `window.torusMaterial` watcher were removed in the 2026-07-18 redesign.

### `triWidget()` (`resources/js/tri-widget-alpine.js`)

Three-tab panel: Clock / Timer / Music.

**Clock tab:**
- `setInterval(tick, 1000)` updates `clockString` + `dateString`
- Jalali date: `toLocaleDateString('fa-IR', {year,month,day,weekday, numberingSystem:'latn'})` (no `calendar` key — `fa-IR` resolves to Persian implicitly)
- `shamsiDateString` used for Persian display

**Timer tab:**
- Countdown from `seconds` (default 300s)
- SVG ring: `stroke-dasharray: (seconds/300) * 351.86`
- Preset buttons: 5/10/15/30/60 min; custom minutes input
- Alarm: `Audio.loop = true`, auto-stops after 60s (lazy, created in `startAlarmLoop()`)

**Music tab:**
- 3 tracks from pCloud CDN (Ambient Pop / LoFi / Pomodoro)
- Lazy `Audio` instantiation (created on first play in `loadCurrentTrack()`); reused across `next()`/`prev()` (only `src` swaps)
- `onloadedmetadata` for duration, `ontimeupdate` for progress
- `onended` → `next()` auto-advance
- Seek via range input, volume control
- `music.idx` persists **in-memory only** across tab switches (no localStorage) — the component instance is not destroyed while mounted

### `workspace()` (`resources/js/workspace-alpine.js`)

| Method | Behavior |
|---|---|
| `searchRecords()` | `GET /workspace/records/__RES__?q=…`; request-ID guard (`recordReqId`) prevents an older slow response overwriting newer results |
| `decorateRecord(p)` | enriches a pin with its parent module's icon + theme color (fallback `from-slate-500 to-slate-600`) |
| `initials(value)` | 2-char initials from the label; splits on `/[\s\-_/.]+/` |

localStorage key: `'user_shortcuts'`; format `{ modules: [...ids], records: [...{key,resourceId,recordId,label,subtitle,url}] }` (with a legacy array→`{modules,records:[]}` migration). The `__RES__` placeholder in `recordsUrl` is replaced at call time — coordinate with `workspace.blade.php`.

Fresh users: both accordions open. Returning users: accordions open based on what has pins.  
Debounced search: 300ms (wired in the blade via `@input.debounce.300ms`, not in the JS).

### Custom Events

| Event | Direction | Purpose |
|---|---|---|
| `dark-mode-toggled` | `window` → Alpine | Listener only — **no producer in the codebase**; `switchers.blade.php` mutates `darkMode` directly. Reserved for a future Filament-panel bridge. |
| `calendar-toggled` | Livewire dispatch → Filament pages `#[On('calendar-toggled')]` | CalendarToggle signals Gregorian/Jalali change (pairs with `maybeJalali()` reading `session('calendar_type')`) |
| `tab-search-focus` | Alpine `$dispatch` → `.window` listener | Ctrl/Cmd+K (and tab-switch buttons) focus the search input via `$nextTick(() => $refs.searchInput?.focus())` |

---

## View / Blade Component Tree

### Landing Page Architecture

```
views/components/filament/landing-page.blade.php          ← root (Filament Page view)
    → x-filament::landing-page/loader                     ← 2900ms full-screen loader
    → x-filament::landing-page/switchers                  ← fixed top-right: lang + dark + logout + widget toggle
    → x-filament::landing-page/widget                     ← floating triWidget panel (clock/timer/music)
    → x-filament::landing-page/header                     ← underline tab switcher (Customize/Workflow/Search)
    → x-filament::landing-page/custom-workspace           ← Customize tab content
    → x-filament::landing-page/workflow                   ← Workflow tab content (4-step stepper)
    → x-filament::landing-page/search                   ← Search tab content
```

**Root component** (`landing-page.blade.php`):
- Props: `$counts` (8 module counts), `$stats`, `$isRtl`
- 2900ms loader then fade-in transition
- Ctrl+K / Cmd+K → dispatches `tab-search-focus` window event + switches to search tab

### Key Blade Props Pattern

`$isRtl` — `bool` passed to all sub-components; used for `{{ $isRtl ? 'right' : 'left' }}` anchor decisions, chevron rotation classes, and `slide-left/right` animation direction.

### Workflow Tab (`workflow.blade.php`)

Rewritten in the 2026-07-18 enterprise redesign from a 2×2 grid of glossy 3D-tilt cards into a compact horizontal stepper (numbered flat circle badges, plain connector line + chevron, link rows with trailing tabular-nums count pills). Data-driven via a `$steps` array. The old `.card-3d`/`.glass`/`.glow-orb`/`.shimmer-effect`/`.floating`/`.badge-float` classes are removed — do not reference them. The 4 pipeline stages retain their semantic colors: Request & Approval (blue), Order Processing (green), Procurement & Payment (amber), Logistics (violet).

### Search Tab (`search.blade.php`)

Uses the registered `search` Alpine factory from `resources/js/search-alpine.js` (`x-data="search"`, not inline):
- State: `searchQuery`, `isSearching`, `results[]`, `selectedResult`, `byUser`, and a `breadcrumb` object with **8 keys** (one per operational model: `purchaseRequest`, `proformaInvoice`, `purchaseOrder`, `registeredOrder`, `bankProfile`, `payment`, `shipment`, `custom`), replaced wholesale by the server response
- `performSearch()` → `GET /api/search/spotlight?q=…` via axios; debounced 500ms (in the blade via `@input.debounce.500ms`)
- Breadcrumb bar (shows when `searchQuery.length >= 2`): pipeline stage indicators
- Results grid: 2-column, color-coded cards with SVG progress ring or binary check icon
- Loading skeleton: 2 placeholder cards during search
- Focus trap: `tab-search-focus` `.window` listener → `$nextTick(() => $refs.searchInput?.focus())`

### Switchers (`switchers.blade.php`)

Fixed top-right float (`z-50`), stacked vertically. Four glassmorphic buttons:
1. Language picker — globe icon, dropdown with flag images; `?locale=` query param navigation
2. Dark/Light toggle — moon/sun icon; updates `darkMode` Alpine state
3. Logout — red chevron; POST to `filament()->getLogoutUrl()`
4. Widget toggle — clock icon; toggles `widgetOpen` Alpine state

### Workspace Tab (`custom-workspace.blade.php`)

**Module accordion:**
- 15 modules defined in PHP (8 operational searchable, 7 master non-searchable)
- Pinned modules grid; chip picker to toggle; count badges
- Module data: `id / label / route / theme / badge / icon (SVG html)`

**Records accordion:**
- Resource selector chips → triggers search input
- Debounced search (300ms) → `GET /workspace/records/{resource}?q=…`
- Results: initials avatar + label + subtitle
- Pin stored in `localStorage['user_shortcuts'].records`
- Loading skeleton + error state with retry

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

Key must match the `id` in the `$modules` array inside `custom-workspace.blade.php`.

---

## Filament Panel Page: `LandingPage`

```php
class LandingPage extends Page
{
    protected static string $layout = 'layout';   // custom layout (not panel layout)
    protected static bool $shouldRegisterNavigation = false;
    protected string $view = 'components.filament.landing-page';
    // mount(): handles ?locale= query param, sets session + app locale
    // getViewData(): merges DashboardStats::get() as $counts
}
```

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
- `.glass` and the other glassmorphism/3D utilities (`.card-3d`, `.shimmer-effect`, `.floating`, `.glow-orb`, `.badge-float`, etc.) were **removed** in the 2026-07-18 redesign — do not re-implement them or reference them. Use the flat `--custom-*`/`--google-*` token surfaces instead (see `resources/css/stylesPattern.md`).
- Never duplicate utility classes that already exist in `landing-page.css` or `fi-custom.css`.
- `will-change: transform/opacity` on animated elements that use GPU-accelerated properties only.
- **Never restructure `.fi-simple-layout` / `.fi-simple-main`** — these are Filament's auth wrappers. The login background effect lives in `::before` pseudo-elements, not in the blade view.

### JavaScript / Alpine.js

- All Alpine components are **pure functions** returning a plain object — no class syntax.
- **No `document.querySelector` inside Alpine data functions** — use `$refs` or `$el`.
- Audio/heavy objects are **lazy-initialized** (instantiated on first use, not in `init()`).
- `window.__alpine_running` guard is checked before starting Alpine — maintain this pattern.
- Custom events use `window.dispatchEvent(new CustomEvent(…))` for cross-component communication.
- `localStorage` keys: `theme` (dark/light), `lp_tab` (active landing tab), `user_shortcuts` (workspace pins).

### Blade / Views

- `$isRtl` is the single source of truth for RTL layout decisions in Blade.
- Sub-components receive only the props they need — no prop drilling of the entire `$counts` array if only one count is needed.
- Loader overlay always `dir="ltr"` regardless of locale.

---

## Latest Changes

### 2026-07-19

- **Two new pattern docs (model layer + global helpers)** — Added the two pattern docs the user authorized, each co-located in its domain folder and opening with an authority banner (authoritative over CLAUDE.md where they conflict):
  - **`app/Models/modelsPattern.md`** — the model-layer + migration guide (model traits AND migrations in one doc, per the user's directive, not a separate `database/migrationsPattern.md`). Covers: the canonical trait-composition skeleton (verified `PurchaseRequest.php`); the load-bearing aliasing rule (`General\Relationships` unaliased + per-domain `Relationships as ExclusiveRelationships` — without it the class fatals on load); the full 12-trait `General` inventory with verified behavior; the per-domain trait-folder convention (`Relationships` / `HasSearchableRelations::scopeSearchAll` / `HasFormattedName`); EAV model side (`HasCustomAttributes` double-declaration, `EntityAttribute` shape); `Status` + `StatusFinder::findBy` + `TYPE_*`-on-owning-model rule; `SCANNABLE_TABLE`/`SCANNABLE_IDENTIFIER` auto-observer constants; the boot-only lifecycle rule (only `UserStamps` + `HasSlug` boot, everything else is Observers); the operational migration skeleton (incl. the deliberate `unsignedBigInteger` `user_id`/`updated_by_id` with NO FK constraint); the pivot conventions with **two confirmed live bugs documented as "fix, don't replicate"** — the `table->unique(...)` missing-`$` typo on `purchase_order_purchase_request:19` and the duplicate `unique` on `registered_order_purchase_order:23-25`. Includes a Decision Matrix, Anti-Patterns, and Naming conventions.
  - **`app/Utils/helpersPattern.md`** — the global-helpers AND locale/RTL guide (helpers AND isRtl/locale in one doc, per the user's directive, not a separate `viewsPattern.md`). Documents all 7 autoloaded helpers with verified signatures/behavior (`toPersianDate`/`toGregorianDate`/`getLocalizedName`/`toYmdDate`/`delimiter`/`maybeJalali`/`tabBadge`); the **`calendar_type` literal contract** — the byte-identical `session('calendar_type', app()->isLocale('fa') ? 'jalali' : 'gregorian') === 'jalali'` expression that must stay identical in exactly two places (`maybeJalali()` + `CalendarToggle::mount()`); the `fa`-only-RTL locale conventions; the `tabBadge` ↔ `.tb-badge` CSS coupling (4 colors, only PHP producer); and the `composer.json` `files` autoload. Includes a Decision Matrix, Anti-Patterns, and Naming conventions.
  - Both docs use prose-cited code blocks (filenames in lead-in prose, no `//` annotations inside fenced code) to satisfy the absolute no-code-comments rule. Verified against source on this branch (2026-07-18/19).
  - Updated the `filamentPattern.md` §1.19-1.26 fold changelog entry (2026-07-18) note: of the 6 candidate standalone docs it listed as uncreated, 2 are now created (`modelsPattern`, `helpersPattern`); 4 remain uncreated (`servicesPattern`, `apiPattern`, `viewsPattern`, `migrationsPattern` — the last is now folded into `modelsPattern.md` and should not be created standalone).

### 2026-07-18

- **`filamentPattern.md` gap fold** — Folded the Filament non-resource architecture into the existing `app/Filament/filamentPattern.md` (no new doc, per the single-source recommendation): added §1.19 custom Page base classes (`App\Filament\Pages\*` + `#[On('calendar-toggled')]` + `PrefillsTableSearch`), §1.20 RelationManager conventions (the 14-RM template + read-only variant), §1.21 cross-resource prefill (`PrepareXxxFromYyy` / `UpdatesFromXxx`), §1.22 total/calculation traits, §1.23 exporter skeleton, §1.24 page mutator & lifecycle hooks, §1.25 `DashboardPanelProvider` panel config, §1.26 bootstrap (`AppServiceProvider` + 4 `Configurators/` + `FilamentMacroServiceProvider` macros + the dual manual / `SCANNABLE_TABLE`-auto observer registration). Amended the stale "ALL 8 operational resources" claim → canonical 8 + 2 variants (`Correspondence`, `Target` — `Target` uses the master-style single `Manage` page), and added 3 anti-patterns (extending Filament's page base directly; `mutateFormDataBeforeFill` class-vs-trait collision on `EditShipment`; `getFileName` override colliding with `ExportDefaults`) plus matching naming-convention entries. Findings surfaced by 5 parallel Explore subagents. The 6 candidate standalone docs (`servicesPattern`, `apiPattern`, `viewsPattern`, `ModelsPattern`, `migrationsPattern`, `helpersPattern`) remain uncreated pending explicit go-ahead.

- **Pattern documentation + `.claude` setup** — Added three co-located, verified pattern docs as canonical references: `app/Filament/filamentPattern.md` (trait-based schema composition, two-tab forms, EAV dual-entry-points, `HasResourcePermissions`, `SmartCacheManager`, `Status`/`StatusFinder`), `resources/css/stylesPattern.md` (token system, `.fi-*` morphing, load-bearing login CSS, flat enterprise landing system, loader, keyframes, Vite pipeline), `resources/js/scriptPattern.md` (`alpine-loader` lazy registration, the four Alpine factories, localStorage, lazy Audio, custom events, Ctrl+K). Each opens with an authority banner declaring it authoritative over CLAUDE.md where they conflict. Verified against source on this branch (2026-07-18).
- **`.claude` setup** — Adapted the Fateh project's `.claude` machinery to BMS-CM's stack (Laravel 12, Filament v4, Livewire v3, Spatie Permission v6, Vite 6, TailwindCSS v4, Alpine v3 — per `composer.json`/`package.json`): 6 `SessionStart` hooks (`sessionstart_01_skills` … `sessionstart_06_selfintro`), the `PostToolUse` `post_tool_review.php` reviewer gate (Anthropic-native `claude-sonnet-4-6`, or dual `glm-5.2:cloud` at a 93% gate when `ANTHROPIC_BASE_URL` routes through Ollama `:11434`), 3 skill files (`code-reviewer`, `laravel-performance`, `ollama`), `settings.json`, and `openai-models-pricing.md`. BMS-CM's existing `settings.local.json` was preserved (not overwritten). Fateh-specific strings were rewritten for this project: the reviewer preamble and skill files now say Filament v4 (not v5); pattern-file pointers reference the 3 real BMS-CM docs (`filamentPattern.md`/`stylesPattern.md`/`scriptPattern.md`), not Fateh's `livewirePattern.md`/`userStylesPattern.md`/`assetPattern.md`/`viewPattern.md`; the trait/pattern lens names BMS-CM's actual traits (`HasResourcePermissions`, `HasExtraAttributesManagement`, `HandleActivation`, `ExportDefaults`) and Service classes, not Fateh's Action/Validator/Presenter/Repository pattern. Hooks activate on next session start.
- **CLAUDE.md corrections** — Added a "Pattern Documentation (authoritative)" pointer block near the top + a "known stale sections" warning. Fixed the stale Landing-Page utility-class catalog (removed classes), the keyframes list (only 8 loader keyframes remain), the JS Entry Point (`search` + `workspace` both always-registered; `__alpine_running` guard), `landingPage()` defaults (`darkMode:false`, `activeTab:'workflow'`, Three.js removed), `triWidget()` (clock field names, no `calendar` key, `numberingSystem:'latn'`, presets 5/10/15/30/60, in-memory `music.idx`), `workspace()` file rename to `workspace-alpine.js`, the Custom Events table (`dark-mode-toggled` listener-only; `calendar-toggled` → Filament pages; `tab-search-focus` `$nextTick`), the View tree (`search` not `search-tab`), and the Workflow/Search Tab descriptions (rewritten to flat stepper; `search.blade.php` + registered `search` factory + 8-key breadcrumb). The historical changelog entries below were left intact as record.

- **Landing page enterprise redesign** — on branch `feature/landing-page-enterprise-redesign` (master untouched). Replaced the landing page's glassmorphism/3D aesthetic (`.glass`, `.card-3d`, `.shimmer-effect`, `.floating`, `.glow-orb`, `.badge-float`, `.workflow-connector`, `.thread-path`, `.workflow-node`, `.tri-widget-panel`, `.btn-wrapper`/`.btn-gradient`, `.icon-container`, `.btn-inline` — all removed from `resources/css/landing-page.css` as dead code once their only consumers were rewritten) with a flat, data-dense, Filament-native language: bordered `bg-white dark:bg-zinc-900` surfaces, `rounded-lg`/`shadow-sm`, 150-200ms transitions, no scale/rotate/spring hover flourishes. Scope is the landing page only — `resources/views/components/filament/landing-page.blade.php` and its 8 sub-components (`switchers`, `widget`, `header`, `workflow`, `workspace`, `search`, `footer`, plus the untouched `loader`), `resources/css/landing-page.css`, `resources/js/landing-page-alpine.js`, and `app/Services/SearchService.php`. **The loader (`loader.blade.php` + its `.ldr-*` CSS/keyframes) was left byte-for-byte untouched by explicit constraint.**
- **Removed the full-screen Three.js torus background** — `<canvas id="canvas-bg">`, the `window.torusMaterial`/`window.ringMaterial` opacity-toggle watcher in `landing-page-alpine.js`, the dedicated `resources/js/landing-page.js` Vite entry (was 100% Three.js particle/torus/ring scene code, now deleted), and `resources/js/3d.min.js` (deleted; also dropped from `vite.config.js`'s `viteStaticCopy` targets and build `input` list) are all gone. Replaced with a cheap CSS-only hairline dot-grid on `.light`/`.dark` (same visual family as the loader's `.ldr-grid`, no JS/GPU cost).
- **New accent convention**: indigo-600 (light) / cyan-400 (dark) is the single primary accent across the landing page — reuses the loader's existing brand color instead of introducing a new one.
- **New semantic palette**: the old per-model rainbow (10-15 distinct hues across `SearchService::registry()`'s `color` field, `workspace.blade.php`'s `$modules` list, and the workflow step colors) is now 5 restrained tones — `blue`/`emerald`/`amber`/`violet` for the 4 operational pipeline stages (Request&Approval / Order Processing / Procurement&Payment / Logistics, consistent across the Workflow stepper, Workspace module tiles, and Spotlight search results) and `zinc` for all master-data modules. `SearchService` now derives the Tailwind icon-chip class bundle from a `private const THEME` map keyed by these 5 tones instead of building a `from-{color}-500 to-{color}-600` gradient string.
- **`workflow.blade.php`** — rewritten from a 2×2 grid of glossy 3D-tilt cards with animated connector pills into a compact horizontal stepper (numbered flat circle badges, plain connector line + chevron, link rows with trailing tabular-nums count pills instead of large gradient buttons + floating badges). Data-driven via a `$steps` array instead of 4 near-duplicated blocks.
- **`widget.blade.php`** — emoji tab icons (🕙⏱️🎵) replaced with heroicons; dropped the ping ring, spring album-art entrance, and glow-pulse animation; panel is now a flat bordered card instead of a blurred glass panel.
- **`header.blade.php`** — the glass "return to dashboard" bar + glass pill tab switcher became a dense flat top bar with a Filament-style underline tab switcher.
- **Follow-up sync pass (same day)** — the redesign above used bespoke Tailwind colors (`zinc-*`, `indigo-600`/`cyan-400`, `emerald`/`amber`/`violet`) instead of this project's real design tokens. Corrected: added `.lp-surface`/`.lp-surface-hover`/`.lp-bar`/`.lp-divider`/`.lp-tab`/`.lp-tab-active` to `landing-page.css`, mirroring `fi-custom.css`'s actual `.fi-section`/`.fi-tabs-item` rules (`var(--custom-third-light)`/`var(--filament-dark-mid)` + `var(--md-elevation-*)`, `var(--gradient-secondary)`/`var(--gradient-google-deep)` for the active tab pill) instead of inventing flat white/zinc cards. `zinc-*` renamed to `slate-*` throughout (matches the panel's actual `primary: Color::Slate`). The 5-tone semantic palette is now `blue`/`green`/`yellow`/`red` (matching `fi-custom.css`'s literal `.tb-info`/`.tb-success`/`.tb-warning`/`.tb-danger` Tailwind families) plus `slate` for master data — not `emerald`/`amber`/`violet`. All `indigo-600`/`cyan-400` accent usage (except the untouched loader) now reads `primary-600`/`primary-400`, which resolves to Filament's live `--primary-*` custom properties via the `@theme inline` block in `vendor/filament/support/resources/css/index.css` — this is the project's actual configured primary color, not an invented brand color. `.lp-tab-active`'s light-mode text was `var(--custom-fourth-light)` (translucent, matches `fi-custom.css` literally but read as too pale in practice) — changed to `var(--filament-dark)` for real contrast; dark mode already used opaque `--google-first-light` and was left as-is. Root `.light`/`.dark` background now mirrors `.fi-body`'s exact radial-gradient treatment instead of a custom dot-grid. Also fixed a self-inflicted bug where a `replace_all` edit stripped the closing `>` off every icon chip in `footer.blade.php`, rendering it as an empty bar. `resources/js/landing-page.js` and `resources/js/3d.min.js` (both dead after the Three.js removal, confirmed via grep — the former was 100% Three.js scene code with no Alpine content despite its old doc label) were deleted and dropped from `vite.config.js`.

### 2026-06-10

- **`app/Filament/Resources/Operational/ShipmentResource/Traits/InvoiceForm.php`** — New trait. Provides `getInvoiceFormTab()` (full commercial invoice form tab, all fields `->dehydrated(false)`) and `persistInvoiceToEav(Get $get, $record)` (upserts `commercial_invoice` JSON into EntityAttribute). Tab layout: 3-column, left group = parties/items, right group = shipment details/totals/remarks. Footer actions: **Save Invoice** + **Download PDF** (opens `shipments.invoice.pdf` route).
- **`app/Services/InvoicePdfService.php`** — New service. mPDF wrapper; Persian locale → IranYekan font + RTL; others → DejaVu + LTR. `generate(array, locale)` → `Mpdf`; `download()` → `Response`.
- **`resources/views/pdf/commercial-invoice.blade.php`** — New table-based mPDF HTML template. Sections: header, parties, info-bar, items, totals, terms, remarks. `$isRtl` drives `dir`, font-family, text-align.
- **`app/Http/Controllers/InvoiceController.php`** — New controller. Reads `commercial_invoice` EAV from Shipment, delegates to `InvoicePdfService::download()`.
- **`routes/web.php`** — Added `GET /shipments/{shipment}/invoice/pdf` → `InvoiceController@shipmentPdf`, middleware `auth`, name `shipments.invoice.pdf`.
- **`app/Filament/Resources/ShipmentResource.php`** — Added `InvoiceForm` trait; `getInvoiceFormTab()` inserted between Logistics tab and Extra Attributes tab.
- **`app/Filament/Resources/Operational/ShipmentResource/Pages/EditShipment.php`** — Extended `mutateFormDataBeforeFill`: loads `commercial_invoice` EAV into `_inv_*` keys on page open; falls back to `bl_number`/`etd`/`eta` from Shipment if no saved invoice.
- **`lang/en|fa|fr/resources/shipment/strings.php`** — Added complete `invoice` array (tab label, all field labels, transport/incoterms options, action labels, notifications).
- **mPDF virtual-tab pattern** — All EAV-backed form tabs use `->dehydrated(false)` on every field so nothing touches the Eloquent model. Save is explicit via `Section::footerActions()`. Hydration lives in `mutateFormDataBeforeFill`.
- **CLAUDE.md** — Added `InvoicePdfService` to Services table; added `InvoiceController` to HTTP Controllers; added login CSS architecture warning to design system and coding philosophy.

### 2026-06-09

- **PurchaseRequestResource** — Converted form from flat Group/Section layout to two-tab structure (General + Extra Attributes). `->columns(3)` moved from root Schema to Tab. `getExtraAttributesFormSection()` removed.
- **PurchaseOrderResource** — Same conversion. Commented-out `//->afterStateHydrated(…)` line preserved verbatim.
- **lang/en|fa|fr/resources/purchaseRequest/strings.php** — Added `form.tab_general` key (`General` / `اطلاعات عمومی` / `Général`).
- **lang/en|fa|fr/resources/purchaseOrder/strings.php** — Added `form.tab_general` key (`General` / `عمومی` / `Général`).
- **CLAUDE.md** — Created (initial `/init`) then fully expanded with CSS design system, JS Alpine component API, view/blade tree, Livewire, HTTP controllers, services, Vite pipeline, workspace config, coding philosophy, and this changelog.
