# Services Layer Pattern (`app/Services/`)

## Overview

15 services, all stateless (static methods) or trivially container-resolvable (plain constructor, no dependencies). None extend a shared base class or implement a shared interface — each is a standalone class grouped here only by directory convention. Two caching strategies coexist and must not be conflated:

| Strategy | Used by | Shape |
|---|---|---|
| `SmartCacheManager::remember()` | Per-model counts/lookups (nav badges, status lookups) | Registry-tracked keys, bulk-invalidatable per model |
| Plain `Cache::remember('analytics:{key}', 300, ...)` | `AnalyticsService`'s 6 cross-model aggregate methods | No registry, no bulk invalidation — 5-minute TTL is the only eviction |
| Plain `Cache::remember()` with a bespoke key | `DashboardStats` (`dashboard_counts:{userId}`), `WorkspaceSearchService` (`workspace_columns:{connection}:{table}`), `Workflow::insightGroups()` (`desk_reference_insight_groups:{locale}`, lives in the Livewire component, not a Service) | One-off keys, no shared registry |

Static-only services (`CodeGenerator`, `DashboardStats`, `DeskReferenceGroups`, `PermissionLabeler`, `SmartCacheManager`, `AnalyticsService`) are called directly by class name. Constructor-instantiable services (`Country`, `FileUploadManager`, `GreetingService`, `InvoicePdfService`, `NotificationEvaluator`, `PersianCalendar`, `SearchService`, `WorkspaceSearchService`) are resolved via `app(Xxx::class)` or constructor injection — none declare their own constructor dependencies, so Laravel's container resolves them with zero binding config, except `PersianCalendar`, which has an explicit (redundant) `singleton` binding in `CalendarServiceProvider`. `DocChecklistMatcher` is static-only despite living conceptually next to `FileUploadManager`.

---

## `SmartCacheManager`

Per-model cache-key registry around `Cache::remember`, enabling bulk invalidation by model name — the standard mechanism for Filament nav badges and other per-model cached lookups project-wide.

```php
public static function remember(string $model, array $filters, int $minutes, callable $callback)
public static function invalidate(string $model): void
```

- `remember()` hashes `$filters` (md5 of `json_encode`, first 16 chars) into the cache key (`smart_{model}_{hash}`), and appends that key to a per-model registry array (`smart_{model}_registry`, stored via `Cache::forever`).
- `invalidate($model)` walks the registry, forgets every registered key, forgets the registry itself, and additionally forgets `total_count_{strtolower($model)}` (a legacy/parallel key convention — `clearNavigationCache()` — not written by `remember()` itself, so this only matters if something elsewhere still writes that literal key).
- `$model` is a free-form string, not a class-checked FQCN — callers pass the model's class basename (e.g. `'Category'`, `'PurchaseOrder'`, `'Status'`), and must pass the *same* string consistently or invalidation silently misses.

Canonical nav-badge call site (repeated near-verbatim across ~13 resources — Category, Product, NotificationSetting, Bank, Currency, Status, User, Custom, Target, etc.):

```php
public static function getNavigationBadge(): ?string
{
    $count = SmartCacheManager::remember(
        'Category',
        ['user_id' => auth()->id(), 'type' => 'total_count'],
        3600,
        fn () => static::getModel()::count()
    );

    return $count > 0 ? (string) $count : null;
}
```

Also used inside `FileUploadManager::processTemporaryFiles()` to cache the `Attachment Status = 'Uploaded'` `Status` lookup (1440 min TTL) — a non-nav-badge use of the same mechanism.

Per CLAUDE.md's 2026-07-23 nav-badge trim, only **Product, Category, NotificationSetting** currently render a badge in the sidebar; the other resources' `getNavigationBadge()` methods (and their `SmartCacheManager::remember()` calls) still exist in code but aren't wired into visible navigation — re-enabling one is a one-line change, not new plumbing.

---

## `AnalyticsService`

Static service backing the 6 Dashboard analytics widgets (`ConcentrationRiskWidget`, `TradeCycleTimeWidget`, `ShipmentPunctualityWidget`, `ExposureAgingWidget`, `OpenCurrencyExposureWidget`, `PipelineStallWidget`). Each widget calls exactly one method and has no query logic of its own:

```php
AnalyticsService::concentrationRisk(): array
AnalyticsService::cycleTimeByStage(): array
AnalyticsService::exposureAging(): array
AnalyticsService::openCurrencyExposure(): array
AnalyticsService::pipelineStalls(): array
AnalyticsService::shipmentPunctuality(): array
```

Every method aggregates in raw SQL (`SUM`/`CASE WHEN`/`DATEDIFF`/HHI math via `DB::select`/`DB::table`), not PHP loops, and is cached individually: `Cache::remember('analytics:{key}', 300, ...)` — **not** `SmartCacheManager`, since these are cross-model aggregates rather than one model's own count/lookup, and there is no per-model invalidation story for them (5-minute TTL is the only eviction). `cycleTimeByStage()` branches on `supportsCte()` (detects MySQL 8+/MariaDB 10.2+ via `DB::getPdo()`'s server-version string, cached in a static local) to use a window-function CTE query where available and a PHP-side percentile fallback (`percentile()`, manual sort + index pick) otherwise — this project's dev DB is MySQL 5.7, so the fallback path is the one that actually runs there.

Full per-method SQL/data-lineage breakdown (what each metric measures, which columns, known scope limitations) lives in `app/Filament/Widgets/widgetsPattern.md` — not duplicated here.

---

## `CodeGenerator`

Auto-generates sequential, date-scoped record codes (`PR-250612`, `PO-250612-2`, etc.) for the 9 operational "number" columns.

```php
public static function generate(string $field): string
public static function fieldsForModel(string $modelClass): array
```

`$field` is a column name (`pr_number`, `invoice_no`, `ro_number`, `contract_no`, `po_number`, `bp_number`, `payment_no`, `shipment_no`, `custom_no`), looked up in a static `$map` of `field => [model, prefix]`. Format is `{PREFIX}-{ymd}` for the first code of the day, `{PREFIX}-{ymd}-{n}` for subsequent ones — the suffix comes from `lockForUpdate()`-guarded `MAX` over same-day codes (`explode('-', $code)[2]`), so two same-day codes never collide under concurrent writes. Unknown `$field` or a model class that doesn't exist returns an `ERROR-{ymd}-FIELD`/`ERROR-{ymd}-MODEL` sentinel string rather than throwing — callers (form `->default()` closures) never see an exception, only an obviously-wrong code if misconfigured.

**Two call patterns coexist**: `CodeGeneratingObserver::creating()` (registered per-model, calls `fieldsForModel()` to find which columns to auto-fill, then `generate()` for each) is the generic path; most resources *also* call `CodeGenerator::generate($field)` directly in a form field's `->default()` closure (so the code shows in the UI before save) and again in every `Prepare{Child}From{Parent}` cross-resource-creation trait (`PrepareShipmentFromRegisteredOrder`, `PrepareBankProfileFromRegisteredOrder`, `PreparesPurchaseOrderFrom*`, `PreparesProformaFrom*`, `PrepareRegisteredOrderFrom*`, `PrepareCustomFromShipment`, `PreparePaymentFromTargetable` — see `filamentPattern.md`'s cross-resource-creation section). Two independent `generate()` calls (form default + observer) for the same field on the same create-request would only produce a mismatch if the form's displayed default were dehydrated back into the payload the observer also writes to — in practice the observer only fills a still-null column, so the form-computed value (already present on the model) wins.

---

## `Country`

Static-content ISO-3166 country list (`en`/`fa` names), instantiated fresh per call site — not cached across requests, only within a single instance's lifetime (`nameIndexByLocale`/`sortedListByLocale` are instance properties keyed by locale, populated lazily on first access per locale).

```php
public function getCountryNameByCode(string $code): ?string
public function getCountriesList(): array   // [ISO code => localized name], asort()-sorted
```

Locale rule: `fa` → the Farsi `name` column, everything else (`en`, `fr`) → `name_english` — French gets no dedicated translation, it falls back to English country names.

Only consumer: `ProformaInvoiceResource` — 4 country `Select` fields (`beneficiary_country`, `destination_country`, `origin`, `origin_country`) call `(new Country)->getCountriesList()` for options, and the matching infolist `TextEntry`s call `app(Country::class)->getCountryNameByCode($state)` to render the stored code back to a name. Note the inconsistent resolution style (`new Country` in the form trait vs `app(Country::class)` in the infolist trait) — harmless since the class has no constructor dependencies, but not a pattern to intentionally replicate elsewhere.

---

## `DashboardStats`

```php
public static function get(bool $fresh = false, int $ttlSeconds = 120): array
```

Counts all 8 operational models (`Payment`, `PurchaseRequest`, `ProformaInvoice`, `BankProfile`, `PurchaseOrder`, `RegisteredOrder`, `Shipment`, `Custom`) via `Model::query()->count()`, cached per-user 120s under `dashboard_counts:{userId}` (falls back to the literal string `'guest'` if unauthenticated). `$fresh = true` bypasses the cache entirely (computes and returns without writing to cache). Only consumer: `LandingPage::getViewData()`, which merges the result in as `$counts` for the header/workflow tabs' module count badges.

---

## `DeskReferenceGroups`

```php
public static function all(): array   // [group_key => translated content array]
```

Reads 4 fixed lang groups (`request_approval`, `order_processing`, `procurement_payment`, `logistics`) from the `deskReference/{group}` namespace via `Lang::has()` + `__()`, skipping any group whose translation is entirely empty (`terms`/`process`/`dos`/`donts`/`tips` all empty). Not cached itself — its sole consumer, `Livewire\LandingPage\Workflow::insightGroups()`, wraps the whole result in `Cache::remember('desk_reference_insight_groups:{locale}', 1 hour, ...)` one layer up, since the resolved translation array has no closures (safe to cache) unlike `SearchService::registry()`/`chainMeta()`, which do carry closures and are deliberately never cached. This is the data source for the landing page's Workflow tab tip cards, and is unrelated to `HasDeskReferenceAction` (the header-action modal on each Resource's List page, which reads `config('desk-reference.php')` directly, not this service).

---

## `DocChecklistMatcher`

```php
public static function sync(HasDocumentChecklist $record): void
```

Auto-ticks a record's document checklist rows by matching each row's fa/en/fr **label text** (not its key) against uploaded attachment filenames. Given a `HasDocumentChecklist` record (currently only `Shipment`, via `SyncsDocumentChecklist::afterSave()`/`afterCreate()`), it:

1. No-ops if `$record->isDocumentTrackingEnabled()` is false, or the checklist has no rows.
2. Normalizes every attachment filename (`canonical()`: lowercase, folds Arabic/Persian glyph variants — `ي→ی`, `ك→ک`, `أ/إ/آ/ٱ→ا`, `ة→ه`, `ؤ→و`, `ئ→ی` — and strips diacritics/zero-width marks) into both a compact no-punctuation string and a whitespace/punctuation-split token list.
3. For each non-`'track'` row (the `'track'` row is the reserved Smart Tracer toggle and is never auto-matched), builds a set of "needles" from the row's label in all 3 locales plus any parenthetical abbreviation extracted via regex (`"Commercial Invoice (CI)"` → also matches `"ci"`).
4. A needle of ≤3 characters must match a **whole token** (`in_array` against the token list) rather than a substring — avoids `"do"` false-matching inside `"document"`. Longer needles use `str_contains` against the compact filename.
5. Flips `received` only when the computed presence differs from the stored value, and only writes back (`$record->setDocumentChecklist($rows)`) if anything actually changed.

Only consumer: `ShipmentResource\Traits\SyncsDocumentChecklist`.

---

## `FileUploadManager`

Temp→permanent attachment pipeline backing `FormComponents::getAttachmentsField()` — the shared attachments field every resource must use per `filamentPattern.md`.

```php
public function storeTemporary(UploadedFile $file): string                      // saveUploadedFileUsing
public function processTemporaryFiles(Model $record, array $paths): static      // saveRelationshipsUsing
public function refreshComponent($record, $set): static
```

- `storeTemporary()` stores to `temp/` with a filename shaped `{urlencoded-original}__{timestamp}-{uniqid}.{ext}` — the `__` separator is load-bearing, `makeNameAndPath()` splits on it later to recover both the human-readable original name and a collision-proof unique suffix.
- `processTemporaryFiles()` is the `saveRelationshipsUsing` hook: for each path still under `temp/`, moves it to `attachments/{camelClassBasename}/{slugged-original}-{unique}.{ext}`, creates an `Attachment` record (`name`, `path`, `type` from `Storage::mimeType()` truncated to 255 chars, `status_id` resolved via `SmartCacheManager::remember('Status', ['type' => 'Attachment Status', 'name' => 'Uploaded'], 1440, ...)`, `user_id` from `auth()->id()`), then calls `syncAttachments()` to delete any `Attachment` rows/files whose path is no longer in the submitted `$paths` (diff-based stale cleanup — removing a file from the FileUpload component and saving genuinely deletes it, both from storage and the DB row, via `forceDelete()`). On any exception, sends a persistent Filament danger notification (`resources/general/strings.attachments.error_title`/`validation.processing_failed`) before re-throwing — the caller (Filament's save pipeline) still sees the exception, the notification is just a user-facing echo of it.
- `refreshComponent()` is a small helper used after save to re-hydrate the form's `attachments` state from `$record->attachments->pluck('path')`.

Wired in `FormComponents.php` as `app(FileUploadManager::class)->storeTemporary($file)` / `->processTemporaryFiles($record, $state)->refreshComponent($record, $set)`.

---

## `GreetingService`

```php
public function getGreeting(string $name, ?string $locale = null): string
```

Picks a random greeting template from `resources/general/strings.greetings.{timeOfDay}_{dayOfWeek}` (e.g. `morning_monday`), interpolates `{name}`. `getTimeOfDay()` buckets the current hour into `morning` (4–11), `afternoon` (12–16), `evening` (17–20), `night` (else). `getDayOfWeek()` treats hours before 4am as still "yesterday" (subtracts a day before resolving the weekday) — so a 2am greeting on a Tuesday resolves to a Monday-night template, not a Tuesday one. Locale fallback chain: requested locale → `config('app.fallback_locale')` → `'en'`, first one with a non-empty translated array wins; if none resolve, returns the bare `$name` with no greeting text at all (silent degrade, not an exception). Only consumer: `resources/views/filament/widgets/account-widget.blade.php` (`(new GreetingService)->getGreeting(filament()->getUserName($user))`), instantiated inline rather than via the container.

---

## `InvoicePdfService`

mPDF-backed commercial invoice PDF generator/downloader; backs `InvoiceController`.

```php
public function generate(array $invoice, string $locale = 'en'): \Mpdf\Mpdf
public function download(array $invoice, string $locale = 'en'): \Illuminate\Http\Response
```

Locale branches on `$locale === 'fa'` for RTL: `directionality` `rtl`/`ltr`, `default_font` `iranyekan`/`dejavusans` (`Iranyekan.ttf` registered from `resource_path('fonts')`, merged into mPDF's own default `fontDir`/`fontdata` arrays rather than replacing them — DejaVu and mPDF's other bundled fonts stay available for `en`/`fr`). `generate()` renders `view('pdf.commercial-invoice', compact('invoice', 'locale', 'isRtl'))` into the PDF body and sets a fixed footer (seller name / "COMMERCIAL INVOICE" / page-number) via `SetFooter()`. `download()` wraps `generate()`, outputs via mPDF's `'S'` (string) mode into a Laravel `Response` with `Content-Disposition: inline` (opens in-browser, not a forced download despite the method name) and filename `Invoice-{invoice_no|now()->format('YmdHis')}.pdf`.

Sole consumer: `InvoiceController::shipmentPdf()` (route `shipments.invoice.pdf`) — reads the `commercial_invoice` EAV `EntityAttribute` off a `Shipment`, 404s if absent/not-array, resolves locale from `session('locale', app()->getLocale())`, calls `app(InvoicePdfService::class)->download($attr->value, $locale)`.

---

## `NotificationEvaluator`

Evaluates every active `NotificationSetting` rule against a changed model and dispatches matching notifications; backs the auto-registered `NotificationDispatcher` observer.

```php
public function evaluate(Model $model, string $action, array $dirty = []): void   // $action: create|update|delete
```

1. Queries `NotificationSetting` rows whose JSON `settings->tables` contains the model's table and `settings->actions` contains `$action`, additionally filtered to `settings->is_active = true` OR an empty `is_active` JSON array (the `orWhereJsonLength(...,0)` branch treats "never explicitly set" the same as "active" — a permissive default, not an opt-in one).
2. For each matching setting, `shouldNotify()` applies column/value filtering: no `columns` filter → always notify; on `update`, requires at least one *watched* column to be among the *actually changed* (`$dirty`) columns, then (if a `values` filter is also set) requires at least one watched column's **current** value to be in the allowed value pool (checks the model's current attribute values, not the dirty/changed values specifically); on `create`/`delete`, skips the changed-column check (nothing to diff) and goes straight to the same values-pool check if one is configured.
3. `dispatch()` resolves the target `User`s via `$setting->getUsers()`, then sends `ModelEventNotification` and/or `ModelEventEmail` per `$setting->notification_type` (`in_app`/`email`/`all`).
4. `buildChangeData()` (update-only) resolves each dirty column's old/new display value — for `*_id` columns, looks up the related model via a same-named camelCase relation method and prefers its `english_name`/`name` attribute over the raw FK integer; falls back to the raw value on any resolution failure (`try`/`catch (\Throwable)`).

Registered generically: `NotificationServiceProvider::boot()` scans `app/Models/` for classes declaring a `SCANNABLE_TABLE` constant and attaches the `NotificationDispatcher` observer automatically — no per-model `AppServiceProvider` wiring needed, just declare the constant.

---

## `PermissionLabeler`

Human-readable label resolution for raw Spatie permission strings (`{module}.{action}`, e.g. `purchase_request.view`) and EAV entity-type FQCNs, with a generic prettification fallback when no dedicated translation exists.

```php
public static function getLabel(string $permissionName): string       // "purchase_request.view" -> "View Purchase Request"
public static function getEntityLabel(string $fqcn): string            // EntityAttribute::entity_type -> module label
public static function getModuleOptions(): array                       // [module => label], sorted, request-cached
```

- `getLabel()` splits on the first `.`; if there's no action segment, returns just the prettified module name. Otherwise looks up `resources/general/strings.actions.{action}` for the action label and `resources/{camelModule}/strings.general.model_label` for the module label — if the module lookup **falls through untranslated** (i.e. `__()` returns the key string itself, meaning no lang file has that key), it falls back to `prettifyModuleName()` (`Str::title(str_replace('_', ' ', $module))`) instead of showing the raw translation-miss string.
- `getModuleOptions()` derives the full module list from `Permission::pluck('name')` (unique `Str::before($p, '.')` prefixes), memoized in a `static $cache` local (persists for the request/process lifetime only, not cross-request).
- Consumers: `PermissionResource` (filter options), `RoleResource` (permission grouping/labeling in the form, module toggle-all), `PermissionResource\Traits\{Table,Infolist,Filters}`, `EntityAttributeResource\Traits\{Table,Infolist,Filters}` (via `getEntityLabel()`, to show a human label for the polymorphic `entity_type` column).

---

## `PersianCalendar`

Small Jalali/Gregorian year-conversion helper, singleton-bound in `CalendarServiceProvider`.

```php
public function convertYear(int $gregorianYear): int                          // Gregorian -> Jalali, only when locale is 'fa' and year > 2000
public function yearOptions(int $past = 2, int $future = 5): array             // [gregorianYear => displayYear string]
public function jalaliToGregorian(int $jalaliYear): int
```

`convertYear()` is a no-op passthrough outside `fa` locale or for years ≤2000 (guards against converting placeholder/sentinel years) — otherwise anchors the conversion at March 21 of the given Gregorian year (Nowruz, the Jalali new year boundary) via `Morilog\Jalali\Jalalian::fromCarbon()`. Resolved via `app(PersianCalendar::class)`, not a global helper — distinct from `toPersianDate()`/`toGregorianDate()`/`maybeJalali()` in `app/Utils/helpers.php`, which handle full dates, not bare years. Consumers: `TargetResource\Traits\{Table,Form}` (year-select options) and `Models\Traits\Target\HasYearAttribute` (accessor casting a stored Gregorian year to its Jalali display form).

---

## `SearchService`

Backs `SearchController`'s two endpoints (`/api/search/spotlight`, `/api/search/chain`) — global record search and the "attached pipeline" chain view. The most complex service in this layer; see also CLAUDE.md's `SearchController` section for the HTTP-level contract.

```php
public function emptyResponse(): array
public function search(string $term): array                    // spotlight search
public function chain(string $type, int $id): array             // attached-pipeline chain for one anchor record
```

**`registry()`** (private, rebuilt per call — carries closures, deliberately never cached) is the single source of per-model search config: 8 operational + 7 master-data model entries, each with `model`, `icon`, `color` (mapped to a Tailwind `THEME` class string), `by_user` (whether an unmatched search term additionally tries `user_id = $byUser->id`), `url` (edit-route closure), `label`, `search` (columns), `with` (eager loads), `progress` (columns used for the completion-% ring), `title` (display-title closure), `details` (label/closure pairs for the result card, deliberately excluding whatever the chain view already shows for that model — see CLAUDE.md's no-overlap rule).

**`search($term)`**: for each registry entry, finds the latest record matching any `search` column via `LIKE` (term is `addcslashes`-escaped against `% _ \`), or — if nothing matched and `by_user` is enabled and the term also matched a `User` by name — the latest record by that user. Returns `results[]` (one per model with a hit) + a `breadcrumb` built from which pipeline stages were found (`buildBreadcrumb()`: stages before the last-found stage are `missing`, after are `upcoming`, found ones are `completed` — this is the *search-match* breadcrumb, distinct from the chain's *attachment* breadcrumb) + `by_user`.

**`chain($type, $id)`**: resolves the anchor record, then walks `SearchService::PIPELINE` (the 8 operational stages, single source of truth for pipeline order) using `chainMeta()` (private, per-model `identifier`/`status_columns`/`extra` fields + a `ros` resolution strategy + a `fetch` closure). Registered Order (RO) id resolution (`resolveRoIds()`) branches per anchor type: `self` (anchor IS the RO), `payment` (anchor is a `Payment`, resolves via its polymorphic `targetable` — RO directly, or PO → RO via `PurchaseOrder::registeredOrders()`), or a named `relation` (belongsTo/pivot, loaded via `loadMissing`). As it walks the pipeline it accumulates `poIds`/`shipmentIds` from the PO/Shipment stages' own resolved records, since `Custom` needs both RO ids and shipment ids, and `Payment` needs both RO and PO ids. All FK/lookup labels (`Status`, `Company`, `Bank`, `Currency`) are resolved in **one batched `whereIn` pass per type** across the entire chain (`refs` accumulator collected during the per-record build, resolved once via `resolveMap()` after the full walk) — N+1-free by construction, not by later optimization. Returns `anchor`, `chain[]` (8 entries, each `attached` bool + `records[]`), and `breadcrumb` (`completed`/`missing` per stage, derived purely from `attached`).

Date formatting inside chain `extra` fields (`type: 'date'`) branches on `app()->getLocale() === 'fa'` → `toPersianDate()`, else a private `self::d()` Gregorian `Y-m-d` formatter — distinct from the rest of the app's `maybeJalali()`/session-based convention, since this is read-only API output, not a form field.

---

## `WorkspaceSearchService`

Backs `WorkspaceController::records()` — the landing-page "pin a record" search, config-driven via `config/workspace.php`'s resource whitelist.

```php
public function search(string $resource, string $term): array
```

1. Looks up `config("workspace.resources.{$resource}")`; 404s if the resource isn't whitelisted or missing `model`/`route` keys.
2. Authorization: derives a Spatie permission prefix from the model's class basename (`Str::snake`) and `abort_unless`s the user can `{prefix}.view` — **403, not a silent empty result**, if unauthorized.
3. Determines searchable columns via `columns()` — `Schema::getColumnListing($table)`, cached 1 day per `workspace_columns:{connection}:{table}` — intersected with the config's optional `search` whitelist, or (if no whitelist) all columns minus a hardcoded blocklist (`password`, `remember_token`, `two_factor_secret`, `two_factor_recovery_codes`) to prevent ever searching/exposing sensitive columns even if a resource config forgets to restrict them.
4. Searches via `CAST(`{column}` AS CHAR) LIKE ?` per column (handles non-string columns like dates/enums/ints uniformly), term `addcslashes`-escaped, capped at 25 results ordered by primary key descending.
5. Each result is composed via `compose()`: joins the config's `title`/`subtitle` column lists into strings, with type-aware formatting (`DateTimeInterface` → `Y-m-d`, `BackedEnum` → `->value`, scalars trimmed, stringable objects via `__toString`) — falls back to `'#'.$record->getKey()` if the composed title is empty.

Returned shape: `[{key: "{resource}:{id}", resourceId, recordId, label, subtitle, url}]` — `key` is what the frontend's `localStorage['user_shortcuts']` pin entries are keyed by.
