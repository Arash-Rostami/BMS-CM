# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

> **Usage:** Start every session with "Read CLAUDE.md first." to load full context before touching anything.  
> **Maintainance:** After each session append only the changed items under [Latest Changes](#latest-changes). Do not re-document unchanged things.

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
- **Three.js (`3d.min.js`)** — 3D torus canvas background on landing page; loaded as static copy (not Vite)
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
resources/js/landing-page.js   → Alpine.js components for landing page
```

**Static copies (not processed, served verbatim):**
```
resources/img/*    → public/img/
resources/js/3d.min.js → public/js/3d.min.js   (Three.js torus — do NOT Vite-ify)
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

**Key utility classes:**
```css
.card-3d          /* transform-style: preserve-3d, hover translateY(-4px) + shimmer ::after */
.btn-wrapper      /* relative container; reveals .badge-float on hover */
.badge-float      /* absolute positioned badge; spring-in animation on parent:hover */
.btn-inline       /* standard inline button utility */
.btn-gradient     /* gradient button utility */
.icon-container   /* scale(1.03) rotate(3deg) on group hover */
.glass            /* glassmorphism: backdrop-blur + bg-white/5 + border with opacity */
.widget           /* direction: ltr — always LTR even on RTL locale */
.tri-widget-panel /* 320–420px width, glass background */
.shimmer-effect   /* will-change: opacity; shimmer overlay */
.floating         /* gentle float animation — stagger with animation-delay */
.glow-orb         /* radial glow, visible in dark via dark:opacity-100 */
.pulse-ring       /* expanding ring pulse */
.shadow-elegant   /* 3-layer box-shadow (light + dark variants) */
.workflow-connector /* SVG thread connecting nodes; hidden on mobile */
.thread-path      /* SVG stroke-dasharray draw-thread animation */
.workflow-node    /* pulse-amber keyframe */
```

**`.glass` dark mode:** `rgba(22,22,26,0.82)` + `border rgba(255,255,255,0.08)`  
**`.glass` light mode:** `--gradient-primary` bg + hover overlay

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
`float`, `glow`, `shimmer`, `pulse-ring`, `pulse`, `fadeSlide`, `slide`, `draw-thread`, `pulse-amber`, `lIn`, `lUp`, `lSub`, `lFill`, `lScan`, `lCorner`, `lGlow`, `lGrid`

---

## JavaScript / Alpine.js Architecture

### Entry Point (`resources/js/alpine-loader.js`)

```js
// Lazy-registers components only if their root element exists in DOM
if (document.querySelector('[x-data="landingPage()"]')) Alpine.data('landingPage', landingPage)
if (document.querySelector('[x-data^="triWidget"]'))    Alpine.data('triWidget', triWidget)
Alpine.data('workspace', workspace)  // always registered
window.__alpine_running = true       // guard against double-init
```

### `landingPage()` (`resources/js/landing-page-alpine.js`)

```js
{
  darkMode: localStorage.theme === 'dark',
  activeTab: localStorage.lp_tab || 'customize',
  // Listens for 'dark-mode-toggled' window event
  // $watch darkMode: persists to localStorage, toggles html.dark class,
  //                  adjusts Three.js material opacity
}
```

### `triWidget()` (`resources/js/tri-widget-alpine.js`)

Three-tab panel: Clock / Timer / Music.

**Clock tab:**
- `setInterval(tick, 1000)` updates `timeStr` + `dateStr`
- Jalali date: `toLocaleDateString('fa-IR', {calendar: 'persian', …})`
- `shamsiDateString` used for Persian display

**Timer tab:**
- Countdown from `seconds` (default 300s)
- SVG ring: `stroke-dasharray: (seconds/300) * 351.86`
- Preset buttons: 5/10/15/25 min; custom minutes input
- Alarm: `Audio.loop = true`, auto-stops after 60s

**Music tab:**
- 3 tracks from pCloud CDN (Ambient Pop / LoFi / Pomodoro Focus)
- Lazy `Audio` instantiation (created on first play)
- `onloadedmetadata` for duration, `ontimeupdate` for progress
- `onended` → `next()` auto-advance
- Seek via range input, volume control
- `currentTrack` index persists navigation between tabs

### `workspace()` (`resources/js/workspace.js`)

```js
// localStorage key: 'user_shortcuts'
// Format: { modules: [...ids], records: [...{key,resourceId,recordId,label,subtitle,url}] }

searchRecords()    // GET /workspace/records/__RES__?q=…; request-ID guard for race conditions
decorateRecord(p)  // enriches pin with parent module's icon + theme color
initials(value)    // 2-char initials from label; splits on /[\s\-_/.]+/
```

Fresh users: both accordions open. Returning users: accordions open based on what has pins.  
Debounced search: 300ms.

### Custom Events

| Event | Direction | Purpose |
|---|---|---|
| `dark-mode-toggled` | `window` → Alpine | Theme changes from Filament panel → landing page |
| `calendar-toggled` | Livewire dispatch | CalendarToggle signals state change |
| `tab-search-focus` | `window` → blade | Ctrl/Cmd+K focuses search input |

### Three.js Integration

`window.torusMaterial` / `window.ringMaterial` — exposed globals from `3d.min.js` for opacity control. `landingPage` Alpine component adjusts `.opacity` on dark mode toggle.

---

## View / Blade Component Tree

### Landing Page Architecture

```
views/components/filament/landing-page.blade.php          ← root (Filament Page view)
    → x-filament::landing-page/loader                     ← 2900ms full-screen loader
    → x-filament::landing-page/switchers                  ← fixed top-right: lang + dark + logout + widget toggle
    → x-filament::landing-page/widget                     ← floating triWidget panel (clock/timer/music)
    → x-filament::landing-page/header                     ← tab pill buttons (Customize/Workflow/Search)
    → x-filament::landing-page/custom-workspace           ← Customize tab content
    → x-filament::landing-page/workflow                   ← Workflow tab content (4 cards)
    → x-filament::landing-page/search-tab                 ← Search tab content
```

**Root component** (`landing-page.blade.php`):
- Props: `$counts` (8 module counts), `$stats`, `$isRtl`
- 2900ms loader then fade-in transition
- `#canvas-bg` for Three.js
- Pushes `3d.min.js` (non-Vite) + `landing-page.js` (Vite)
- Ctrl+K / Cmd+K → dispatches `tab-search-focus` window event + switches to search tab

### Key Blade Props Pattern

`$isRtl` — `bool` passed to all sub-components; used for `{{ $isRtl ? 'right' : 'left' }}` anchor decisions, chevron rotation classes, and `slide-left/right` animation direction.

### Workflow Tab (`workflow.blade.php`)

4 workflow step cards arranged in 2×2 grid (desktop) / vertical stack (mobile):
1. **Request & Approval** (blue) — links: Purchase Requests + Proforma Invoices
2. **Order Processing** (green) — links: Registered Orders + Bank Profiles
3. **Procurement & Payment** (amber) — links: Purchase Orders + Payments
4. **Logistics** (purple) — links: Shipments + Customs

Each card: `.card-3d.workflow-step`, `.glass` inner panel, `.glow-orb` accent, `.shimmer-effect` on hover, `.floating` icon, `.badge-float` with live stat count.

Animated connectors between cards: desktop = horizontal pill (slide-right/left), mobile = vertical pill (slide-down). RTL-aware direction.

### Search Tab (`search-tab.blade.php`)

Inline Alpine `x-data` component (not a separate JS file):
- `searchQuery`, `isSearching`, `results[]`, `breadcrumb{proforma/order/logistics}`
- `performSearch()` → `GET /api/search/spotlight?q=…` via axios; debounced 500ms
- Breadcrumb bar (shows when `searchQuery.length >= 2`): pipeline stage indicators
- Results grid: 2-column, color-coded cards with SVG progress ring or binary check icon
- Loading skeleton: 2 placeholder cards during search
- Focus trap: dispatched `tab-search-focus` event → `$refs.searchInput.focus()`

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
- `.glass` is the glassmorphism utility — do not re-implement it inline.
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
