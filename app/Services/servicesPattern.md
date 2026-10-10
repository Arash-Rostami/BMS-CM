# Services Layer Pattern (`app/Services/`)

## Overview

17 single-file services (this doc's full subject), plus two multi-file subsystems living in their own subdirectories — **`app/Services/Calendar/`** (the Calendar Rules engine: `Sync/` rule→hits + model-save routing, `Display/` month grid presenter, plus modules/path-resolver/alerts/activity; see `app/Services/Calendar/calendarPattern.md`) and: **`app/Services/Imports/`** — the shared bulk-import pipeline used by every module's Filament `Importer` classes. It's cohesive enough (12 files: column-definition/factory/pipeline core + 5 pipeline stages + supporting helpers) to warrant its own dedicated doc rather than folding into this one — see `app/Services/Imports/importsPattern.md`, not duplicated here.

The 17 single-file services below are all stateless (static methods) or trivially container-resolvable (plain constructor, no dependencies). None extend a shared base class or implement a shared interface — each is a standalone class grouped here only by directory convention. Two caching strategies coexist and must not be conflated:

| Strategy | Used by | Shape |
|---|---|---|
| `SmartCacheManager::remember()` | Per-model counts/lookups (nav badges, status lookups) | Registry-tracked keys, bulk-invalidatable per model |
| Plain `Cache::remember('analytics:{key}', 300, ...)` | `AnalyticsService`'s 6 cross-model aggregate methods | No registry, no bulk invalidation — 5-minute TTL is the only eviction |
| Plain `Cache::remember()` with a bespoke key | `DashboardStats` (`dashboard_counts:{userId}`), `WorkspaceSearchService` (`workspace_columns:{connection}:{table}` — the `{connection}` part is the literal string `default` at runtime, since the model's `getConnectionName()` is null in this code path, not `mysql`), `Workflow::insightGroups()` (`desk_reference_insight_groups:{locale}`, lives in the Livewire component, not a Service) | One-off keys, no shared registry |

Static-only services (`CodeGenerator`, `DashboardStats`, `DeskReferenceGroups`, `PermissionLabeler`, `SmartCacheManager`, `AnalyticsService`, `ExceptionPresenter`, `StatusWorkflow`) are called directly by class name. Constructor-instantiable services (`Country`, `FileUploadManager`, `GreetingService`, `InvoicePdfService`, `NotificationEvaluator`, `PersianCalendar`, `SearchService`, `WorkspaceSearchService`) are resolved via `app(Xxx::class)` or constructor injection — none declare their own constructor dependencies, so Laravel's container resolves them with zero binding config, except `PersianCalendar`, which has an explicit (redundant) `singleton` binding in `CalendarServiceProvider`. `DocChecklistMatcher` is static-only despite living conceptually next to `FileUploadManager`.

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

Per CLAUDE.md's 2026-07-23 nav-badge trim, only **Product, Category** currently render a badge in the sidebar; the other resources' `getNavigationBadge()` methods (and their `SmartCacheManager::remember()` calls) still exist in code but aren't wired into visible navigation — re-enabling one is a one-line change, not new plumbing.

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

Auto-generates sequential, date-scoped record codes (`PR-250612`, `PO-250612-2`, etc.) for the 9 operational "number" columns, plus `departments.code` (`DEPT-`) — an import-only 10th mapping: Department is NOT in `CODE_GENERATED_MODELS` and has no `CodeGeneratingObserver` registration, so its form codes stay user-entered; `generate('code')` is reached only through `ImportDefaults::fillReservedNumberIfBlank('code')` in `DepartmentImporter::beforeCreate()`.

```php
public static function generate(string $field): string
public static function fieldsForModel(string $modelClass): array
```

`$field` is a column name (`pr_number`, `invoice_no`, `ro_number`, `contract_no`, `po_number`, `bp_number`, `payment_no`, `shipment_no`, `custom_no`, `code`), looked up in a static `$map` of `field => [model, prefix]`. Format is `{PREFIX}-{ymd}` for the first code of the day, `{PREFIX}-{ymd}-{n}` for subsequent ones — the suffix comes from a `MAX` scan over same-day codes (`explode('-', $code)[2]`). Unknown `$field` or a model class that doesn't exist returns an `ERROR-{ymd}-FIELD`/`ERROR-{ymd}-MODEL` sentinel string rather than throwing — callers (form `->default()` closures) never see an exception, only an obviously-wrong code if misconfigured. **All 10 mapped columns carry a plain (non-unique — see `modelsPattern.md` §9) index** as of the bulk-import migration (departments.code got its `idx_departments_code` when the old DB unique was dropped for soft-delete compat).

**Three call patterns coexist**: `CodeGeneratingObserver::creating()` (registered per-model, calls `fieldsForModel()` to find which columns to auto-fill, then `generate()` for each) is the generic path; most resources *also* call `CodeGenerator::generate($field)` directly in a form field's `->default()` closure (so the code shows in the UI before save) and again in every `Prepare{Child}From{Parent}` cross-resource-creation trait (`PrepareShipmentFromRegisteredOrder`, `PrepareBankProfileFromRegisteredOrder`, `PreparesPurchaseOrderFrom*`, `PreparesProformaFrom*`, `PrepareRegisteredOrderFrom*`, `PrepareCustomFromShipment`, `PreparePaymentFromTargetable` — see `filamentPattern.md`'s cross-resource-creation section). **Corrected 2026-10-03 — the form-default value is NEVER what actually gets saved.** `CodeGeneratingObserver::creating()` has no `filled()` guard outside import mode — it unconditionally runs `$model->{$field} = CodeGenerator::generate($field)` on every create, overwriting whatever the form/default/prepare-trait already put there. The form-displayed value is a preview only; the real value is always recomputed fresh at `creating()` time, a few milliseconds before the actual INSERT. The third pattern, `CodeGeneratingObserver::duringImport(Closure)` (bulk import — see `filamentPattern.md` §1.8a), flips `creating()` to skip regeneration ONLY for fields already filled on the model, so `ImportDefaults`'s chunk-scoped reservation (`ImportDefaults::reserveNumberChunk()`, one `generate()` call per 100-row chunk instead of one per row) can pre-fill blank-numbered new rows without the observer clobbering them.

**Fixed 2026-10-03 — `App\Models\Traits\General\HasReliableCodeGeneration`, app-level only, no schema change.** The root cause: `lockForUpdate()` inside `generate()` provided no real protection, because a locking read only serializes against another transaction if BOTH run inside a real, overlapping `DB::transaction()` — nothing wrapped `generate()`'s call in one, and a bare Eloquent `Model::save()` does not open a transaction around `creating()`/the subsequent INSERT by default. In autocommit mode a `SELECT ... FOR UPDATE` acquires and releases its lock within that single statement, blocking nothing. The 2026-09-22 migration (`add_business_identifier_indexes`) only ever added a plain index — a performance aid for the `LIKE` scan, not a correctness guarantee — so this was silently possible since that fix, contrary to this doc's own prior (incorrect) claim that the lock prevented collisions. **A database-level unique constraint was built, verified working, and then explicitly rejected by the user 2026-10-03 — the shipped fix is app-level only, no ALTER TABLE, no new column, no index change.**

The fix is one ~10-line trait, composed onto the 8 models with a `CodeGenerator`-mapped column (`PurchaseRequest`, `ProformaInvoice`, `RegisteredOrder`, `PurchaseOrder`, `BankProfile`, `Payment`, `Shipment`, `Custom` — Department excluded, matching its own documented no-DB-uniqueness decision). It overrides `save()`:

```php
public function save(array $options = [])
{
    if ($this->exists || $this->getConnection()->transactionLevel() > 0) {
        return parent::save($options);
    }

    return $this->getConnection()->transaction(fn () => parent::save($options), 3);
}
```

Both guards are load-bearing: `$this->exists` leaves updates untouched (the observer only regenerates on `creating`, never on update); `transactionLevel() > 0` defers to any ambient transaction already open (including this test suite's own per-test wrapping transaction — `tests/testPattern.md` §0 — so tests behave identically whether or not this trait is present). `CodeGenerator.php` and `CodeGeneratingObserver.php` are both untouched — the fix works purely by finally giving the pre-existing `lockForUpdate()` scan a real transaction to run inside, so the already-existing 2026-09-22 index starts doing real locking work instead of none.

**Why a plain retry (not a schema constraint) is correct, not just convenient.** Under MySQL's default REPEATABLE READ, the scan-and-lock behaves differently depending on whether matching rows already exist for that day's prefix: if they do, the lock is a real record lock and the second transaction genuinely blocks until the first commits, then sees the fresh row. If NONE exist yet (the first code of a given prefix+day), InnoDB only takes a **gap lock** — and gap locks don't conflict with each other, so two concurrent first-of-day creates can both pass the lock, then deadlock against each other at INSERT time (error 1213). This is why the `3`-attempt retry in `DB::transaction($closure, $attempts)` is load-bearing, not a defensive extra — Laravel's own transaction helper auto-retries on exactly this class of error (`causedByConcurrencyError()`), and each retry re-fires `creating()`, which (via `CodeGeneratingObserver`, unconditionally outside import mode) computes a genuinely fresh number against what's now actually committed. The user never sees an error; nothing else on the form/record is touched by the retry — only the generated field is recomputed.

**Verified, not assumed:** 10 real two-OS-process concurrent creates of the same model (two separate `php` CLI processes racing against the same dev DB, not a single-process simulation) — 0 collisions, 0 errors, across all 10 runs. Permanent regression coverage lives in `tests/Feature/Traits/HasReliableCodeGenerationTest.php`, one dedicated test per model, each asserting trait composition and that a forced pre-existing "next number" still resolves to a distinct, successfully-saved record rather than an error (a true multi-process race isn't reproducible inside a single PHPUnit process, so that gap is covered by the one-time manual verification above, not a standing automated test).

**Residual, pre-existing, out of scope:** `ImportDefaults::reserveNumberChunk()` (bulk import's own chunk-scoped reservation, see `filamentPattern.md` §1.8a) calls `generate()` once per 100-row chunk with no transaction spanning the chunk's inserts — a form-create running concurrently with an in-flight import could still theoretically collide with a reserved-but-not-yet-inserted import number. Unchanged by this fix.

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
3. For each non-`'track'` row (the `'track'` row is the reserved Smart Tracer toggle and is never auto-matched), builds a set of "needles" from the row's label in all 3 locales plus any parenthetical abbreviation extracted via regex (`"Commercial Invoice (CI)"` → also matches `"ci"`). **The label needle keeps its code glued on — normalization strips punctuation, so the label needle is `"commercialinvoiceci"`, meaning `"Commercial Invoice (CI).pdf"` matches but `"commercial-invoice.pdf"` does NOT. Without the code suffix in the filename, only the bare-code-as-whole-token rule (`"CI-001.pdf"`) or the full label-with-code string can tick the row.**
4. A needle of ≤3 characters must match a **whole token** (`in_array` against the token list) rather than a substring — avoids `"do"` false-matching inside `"document"`. Longer needles use `str_contains` against the compact filename.
5. Flips `received` only when the computed presence differs from the stored value, and only writes back (`$record->setDocumentChecklist($rows)`) if anything actually changed.

Only consumer: `ShipmentResource\Traits\SyncsDocumentChecklist`.

---

## `ExceptionPresenter`

Static classifier turning a low-level exception (raised during a Filament Create/Edit save) into a short, translated, actionable `{title, body}` pair for an end user, while the full technical detail still reaches `laravel.log` tagged with the same reference code shown to the user.

```php
public static function present(Throwable $e): array   // ['title' => ..., 'body' => ..., 'reference' => 'ERR-ymd-His-XXXX']
```

- Generates the reference (`'ERR-'.now()->format('ymd-His').'-'.Str::upper(Str::random(4))`) and logs (`Log::error("[{$reference}] ".$e->getMessage(), ['reference' => ..., 'exception' => $e])`) **before** classifying — the log entry exists even if classification itself somehow throws.
- `classify()` branches on exception type: `QueryException` goes to `classifyQueryException()` (matches `$e->errorInfo[1]`, the MySQL driver-specific error code — 1264 out-of-range, 1406 too-long, 1265/1366 truncated-or-invalid-type, 1048 null-not-allowed, 1062 duplicate, 1451/1452 FK violations, 1213/1205 deadlock/lock-timeout, 2002/2006/2013 connection-lost, 1146/1054/1049 missing-table/column/database (a config bug, not the user's fault), default generic-database-error); `ModelNotFoundException`/`AuthorizationException`/`TokenMismatchException`/`PostTooLargeException` each get one dedicated message; anything else falls to a generic "something went wrong" pair.
- All copy is `__('errors/strings.notifications.*')` — the `notifications` group in `lang/{locale}/errors/strings.php`, sibling to that file's existing HTTP-status-code `codes` map (see `localizationPattern.md` §1) — not `resources/general/strings.php`, since this is error-page-adjacent content, not a resource or general-UI string.

Two consumers, covering Filament's two separate action-execution mechanisms (see `filamentPattern.md` §1.19): `App\Filament\Traits\HandlesSaveExceptions` (`CreateRecord`/`EditRecord`'s dedicated save flow — sends the presented pair as a persistent notification, then throws `Filament\Support\Exceptions\Halt` so the save fails gracefully) and `App\Filament\Traits\HandlesActionExceptions` (every other Filament Action — row/header/bulk — via `callMountedAction()`; used by `ListRecords`, `ManageRecords`, and all 25 RelationManagers in addition to `CreateRecord`/`EditRecord`; no `Halt` needed since the vendor method already rolls back before re-throwing).

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

Sole consumer: `InvoiceController::shipmentPdf()` (route `shipments.invoice.pdf`) — `abort_unless(userCan(Shipment::class), 403)` (2026-09-17; previously only `auth()->check()`, letting any logged-in user download any shipment's invoice by guessing the id), then reads the `commercial_invoice` EAV `EntityAttribute` off the `Shipment`, 404s if absent/not-array, resolves locale from `session('locale', app()->getLocale())`, calls `app(InvoicePdfService::class)->download($attr->value, $locale)`.

---

## `NotificationEvaluator`

**Change-line labels (2026-10-09):** FK columns in the email/in-app 'changes' lines resolve through `NameSearch::labels()` (both-name label, identifier fallback for models without a name column) — the same source as the value pickers; a date-only pick on a datetime column fires only when the record moves INTO that day (a day candidate always goes through the day rule, never the exact-match shortcut).

Evaluates every active `NotificationSetting` rule against a changed model and dispatches matching notifications; backs the auto-registered `NotificationDispatcher` observer.

```php
public function evaluate(Model $model, string $action, array $dirty = []): void   // $action: create|update|delete
```

1. Queries `NotificationSetting` rows whose JSON `settings->tables` contains the model's table and `settings->actions` contains `$action`, additionally filtered to `settings->is_active = true` OR an empty `is_active` JSON array. **The `orWhereJsonLength(...,0)` branch only matches an explicitly empty ARRAY `[]` — a missing or `null` `is_active` key does NOT match either branch (MySQL's `JSON_LENGTH(NULL)` is `NULL`, never `0`), so a setting with no `is_active` flag never fires. Pinned empirically in `NotificationEvaluatorTest` (`test_setting_without_an_is_active_flag_never_fires`).**
2. For each matching setting, `shouldNotify()` applies column/value filtering: no `columns` filter → always notify; on `update`, requires at least one *watched* column to be among the *actually changed* (`$dirty`) columns, then (if a `values` filter is also set) requires at least one watched column's **current** value to be in the allowed value pool (checks the model's current attribute values, not the dirty/changed values specifically); on `create`/`delete`, skips the changed-column check (nothing to diff) and goes straight to the same values-pool check if one is configured.
3. `dispatch()` resolves the target `User`s via `$setting->getUsers()`, then sends `ModelEventNotification` and/or `ModelEventEmail` per `$setting->notification_type` (`in_app`/`email`/`all`).
4. `buildChangeData()` (update-only) resolves each dirty column's old/new display value — for `*_id` columns, looks up the related model via a same-named camelCase relation method and prefers its `english_name`/`name` attribute over the raw FK integer; falls back to the raw value on any resolution failure (`try`/`catch (\Throwable)`).

Registered generically: `NotificationServiceProvider::boot()` scans `app/Models/` for classes declaring a `SCANNABLE_TABLE` constant and attaches the `NotificationDispatcher` observer automatically — no per-model `AppServiceProvider` wiring needed, just declare the constant.

**Revised 2026-10-08 — items 1-4 above describe the OLDER behaviour; the current contract is:**

- **Matching (per column):** `settings.values` is `{column: [values]}`. On update a rule fires only when a WATCHED column changes INTO a listed value for THAT column (the new normalized value is listed and the old one was not; moving between two listed values fires; unrelated changes, moving away and a value listed for another column do not). A watched column with no values fires on any change; no watched columns = any update fires. Create/delete compare the record's current value per column. Values are ONLY the per-column map: a flat list (the pre-2026-10-08 shape, no production data ever existed) is treated as malformed and fails closed; malformed stored values never fire. `is_active` accepts `true`, `1`, `"1"`, `"true"`; `[]` or a missing flag is off. Comparisons go through `App\Services\NotificationValueNormalizer` (decimal, int, string ids, date, datetime, bool, enum, null).
- **Value validation (2026-10-09):** matching is the normalized exact match, plus one rule (`NotificationValueNormalizer::matches`): a date-only candidate (`Y-m-d`) on a `datetime`/`timestamp` column fires on any time that calendar day; full datetimes stay exact. **Day-level update rule:** on an UPDATE with an old value the date-only match fires only when the old value's calendar day differs from the new one (the record moved INTO the picked day); a time-only change inside the picked day, or a record already on it, does not fire. Create/delete and null-old updates keep the any-time-that-day behaviour (`matches()` takes `$previous`/`$isUpdate`). Form validation no longer requires a value to be one of the stored options: FK (`*_id`) values must exist in the related table, all other values must fit the column type (date parsable, number numeric, boolean 0/1, else free scalar; trimmed, max 255 chars, max 50 per column, enforced both by the form and by `sanitizeSettings()`). **Name search:** `AppServicesNameSearch` is the shared helper for FK name pickers (Notification Settings FK search + Calendar relationship selectors): `columns()` = existing name/english_name/SCANNABLE_IDENTIFIER/title/code in locale order (fa: name first), `display()` = first of them, `apply()`/`options()` = LIKE-escaped OR search across all of them, capped at 50.
- **Recipients:** resolved once per save for all matching rules; a user is skipped when inactive (`users.status`) or when `$user->can("{module}.view")` is false (no admin bypass, both channels). The update body lists only the watched changed columns, never `updated_at`/`updated_by_id`; related-record display values are memoized per `evaluate()` call so queries per save do NOT grow with the number of matching rules.
- **Dates in mail:** `ModelEventEmail` has no session in the queue, so date/datetime change values (DateTime or `Y-m-d[ H:i:s]`) render by the process locale: `fa` Jalali, else Gregorian (`helpersPattern.md` §10).
- **Delivery:** `ModelEventNotification` / `ModelEventEmail` are `ShouldQueue` with `afterCommit()` and `deleteWhenMissingModels`, on the DEFAULT queue (a worker is required; a queued notification for a force-deleted record is dropped, force delete is banned anyway). Each rule and each recipient is wrapped in try/catch + `report()`, and `NotificationDispatcher` and `CalendarTouchObserver` isolate each other's exceptions. A restore sends nothing; delete and force-delete notifications link to the resource list; mail URLs are joined without a double slash.
- **Language:** in-app payloads store translation keys + params and are rendered at READ time in the viewer's locale by `App\Livewire\CalendarDatabaseNotifications` (shared with the calendar alerts; vendor-shaped payloads pass through, malformed payloads fail closed). Email renders in the locale of the sending PROCESS (the worker's default locale, or the editor's locale when the queue is `sync`): there is no per-user stored locale.
- **Caching:** active rules are cached through `SmartCacheManager` and flushed on saved/deleted/restored (also after commit, for other processes); `NotificationSetting::scannableModels()` is a static memo + `Cache` (TTL 1 h) replacing the per-request `app/Models` scan, so a model that gains `SCANNABLE_TABLE` is picked up after the cache clears (`/clear`, `optimize:clear`).


---

## `PermissionLabeler`

Human-readable label resolution for raw Spatie permission strings (`{module}.{action}`, e.g. `purchase_request.view`) and EAV entity-type FQCNs, with a generic prettification fallback when no dedicated translation exists.

```php
public static function getLabel(string $permissionName): string       // "purchase_request.view" -> "View Purchase Request"
public static function getEntityLabel(string $fqcn): string            // EntityAttribute::entity_type -> module label
public static function getModuleOptions(): array                       // [module => label], sorted, request-cached
```

- `getLabel()` splits on the first `.`; if there's no action segment, returns just the prettified module name. Otherwise looks up `resources/general/strings.actions.{action}` for the action label and `resources/{camelModule}/strings.general.model_label` for the module label — if the module lookup **falls through untranslated** (i.e. `__()` returns the key string itself, meaning no lang file has that key), it falls back to `prettifyModuleName()` (`Str::title(str_replace('_', ' ', $module))`) instead of showing the raw translation-miss string.
- **`status.grant_*` permissions are a special case, checked first.** These are dynamically generated by the status-workflow feature (`StatusResource\Traits\Form::generateApprovalPermissionName()`, one per gated `Status` row) and don't fit the fixed `resources/general/strings.actions.*` action set. `getLabel()` detects the `status.grant_` prefix and resolves the label by looking up the actual `Status` row via `Status::where('approval_permission', $permissionName)->first()` — using that row's own `getLocalizedNameAttribute()` (name) and `type`/`english_type` (category) rather than trying to parse a human name back out of the permission string. Falls back to a prettified tail-of-string if no matching Status row is found (e.g. an orphaned permission after a Status was deleted). Covered by `tests/Feature/Services/PermissionLabelerTest.php`.
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

**`registry()`** (private, rebuilt per call — carries closures, deliberately never cached) is the single source of per-model search config: 8 operational + 7 master-data model entries, each with `model`, `icon`, `color` (mapped to a Tailwind `THEME` class string), `by_user` (whether an unmatched search term additionally tries `user_id = $byUser->id`), `url` (edit-route closure), `label`, `search` (columns), `with` (eager loads), `progress` (columns used for the completion-% ring), `title` (display-title closure), `details` (label/closure pairs for the result card, deliberately excluding whatever the chain view already shows for that model).

**Authorization (2026-09-17):** both public methods gate per pipeline model via `userCan($cfg['model'])`/`userCan($registry[$type]['model'])` (`app/Utils/helpersPattern.md`) — before this, any authenticated user could pull any model's full record regardless of their actual Spatie permissions. `search()` silently `continue`s past a registry entry the user can't view (a spotlight hit for a model you can't see just doesn't appear — no error). `chain($type, $id)` returns the empty `['anchor' => null, 'chain' => []]` response if the user can't view the anchor's own type; for the other 7 pipeline stages in a permitted chain, the `attached` boolean is always accurate (harmless — it's an aggregate fact, not a record) but that stage's `records[]` (and therefore its `extras`) stays empty unless the user also has `.view` on that stage's model — so a `purchase_request.view`-only user sees which stages are attached, but never another model's field-level data.

**`search($term)`**: for each registry entry, finds the latest record matching any `search` column via `LIKE` (term is `addcslashes`-escaped against `% _ \`), or — if nothing matched and `by_user` is enabled and the term also matched a `User` by name — the latest record by that user. Returns `results[]` (one per model with a hit — `buildResult()` also emits `status`, the localized status name via the registry's eager-loaded `status` relation, `''` for models without one (e.g. ProformaInvoice has no status relation; `method_exists` guards it), rendered as a chip on the Search tab's result cards, 2026-10-10) + a `breadcrumb` built from which pipeline stages were found (`buildBreadcrumb()`: stages before the last-found stage are `missing`, after are `upcoming`, found ones are `completed` — this is the *search-match* breadcrumb, distinct from the chain's *attachment* breadcrumb) + `by_user`.

**`chain($type, $id)`**: resolves the anchor record, then walks `SearchService::PIPELINE` (the 8 operational stages, single source of truth for pipeline order) using `chainMeta()` (private, per-model `identifier`/`status_columns`/`extra` fields + a `ros` resolution strategy + a `fetch` closure). Registered Order (RO) id resolution (`resolveRoIds()`) branches per anchor type: `self` (anchor IS the RO), `payment` (anchor is a `Payment`, resolves via its polymorphic `targetable` — RO directly, or PO → RO via `PurchaseOrder::registeredOrders()`), or a named `relation` (belongsTo/pivot, loaded via `loadMissing`). As it walks the pipeline it accumulates `poIds`/`shipmentIds` from the PO/Shipment stages' own resolved records, since `Custom` needs both RO ids and shipment ids, and `Payment` needs both RO and PO ids. All FK/lookup labels (`Status`, `Company`, `Bank`, `Currency`) are resolved in **one batched `whereIn` pass per type** across the entire chain (`refs` accumulator collected during the per-record build, resolved once via `resolveMap()` after the full walk) — N+1-free by construction, not by later optimization. Returns `anchor`, `chain[]` (8 entries, each `attached` bool + `records[]`), and `breadcrumb` (`completed`/`missing` per stage, derived purely from `attached`).

Date formatting inside chain `extra` fields (`type: 'date'`) branches on `app()->getLocale() === 'fa'` → `toPersianDate()`, else a private `self::d()` Gregorian `Y-m-d` formatter — distinct from the rest of the app's `maybeJalali()`/session-based convention, since this is read-only API output, not a form field.

---

## `StatusWorkflow`

Stage-driven status approval workflow gate (foundation shipped 2026-09-26; Purchase Request is the first module wired to it — other modules and the admin UI for `stage_order`/`approval_permission` follow in later stages). Sits alongside `Status`/`StatusFinder` but is deliberately its own service, not a `Status` trait — it reasons about a transition (two `Status` rows + an acting `User`), not about one row in isolation.

```php
public static function initialFor(string $englishType): ?Status
public static function nextFor(Status $current): ?Status
public static function canSet(?User $user, Status $target, ?Status $current): bool
public static function assertAllowed(?User $user, Status $target, ?Status $current, string $column = 'status_id'): void
public static function isTerminal(Status $status): bool
```

- `isTerminal()` (added 2026-09-26): `true` only for a genuine ordered terminus — `stage_order` non-null AND `nextFor($status)` is `null`. This is the single shared "is this the last stage" check; it's used by `TracksStatusHistory::archiveAttachmentsIfTerminal()` (auto-archives a record's `Attachment` rows the instant its status hits this point — see `modelsPattern.md` §6b) and by `FormComponents::getAttachmentsField()`'s `->deletable()` gate (`filamentPattern.md` §1.14). Don't re-derive "is this the last stage" inline anywhere else — extend this method instead.

- `statuses.stage_order` (nullable `unsignedSmallInteger`) is a status's position within its own `english_type`; `NULL` means unordered/free (e.g. `Conditional`/`Declined` on Purchase Request) — the default for every status until an admin opts a type into staged approval. `statuses.approval_permission` (nullable `string`) is a Spatie permission name gating who may set that status; `NULL` means ungated.
- `initialFor()` returns the lowest non-null-`stage_order` row for a type — the "auto-set on create" target — via a `SmartCacheManager::remember('Status', ['english_type' => ..., 'type' => 'ordered_stage_list'], 60, ...)`-cached ascending list (private `orderedStatuses()`), invalidated by `StatusObserver` (`saved`/`deleted`/`restored`, registered in `AppServiceProvider::registerObservers()`) on any `Status` write — the one gap the pre-existing `Status` model had no lifecycle hook for.
- `nextFor()` (added in the Purchase Request wiring stage) returns the single status one `stage_order` ahead of `$current` within the same cached `orderedStatuses()` list, or `null` when `$current` is unordered or already the last stage — the sole building block a form field needs to restrict its dropdown to "current + one step forward" without re-deriving the ordered list itself.
- `canSet()` checks, in order: (1) if `$target->approval_permission` is set, `$user` must `can()` it (Spatie) — no user or no permission is an unconditional `false`, checked before any ordering logic; (2) a `NULL` `$target->stage_order` (unordered/free) with the permission gate already cleared is always `true` — current unrestricted behavior; (3) an ordered target requires either `$current === null` and `$target` IS that type's `initialFor()` result, or `$current->stage_order` non-null and exactly `$target->stage_order - 1` — strict one-step-forward only, no skipping, no backward move (backward is the distinct "Return for Revision" action below, which deliberately bypasses `canSet()`/`assertAllowed()` since it is an intentional jump back to stage 1, not a forward transition).
- `assertAllowed()` wraps `canSet()` and throws `Illuminate\Validation\ValidationException` (field key defaults to `status_id`, overridable via the trailing `$column` param for a multi-status-column model; message `resources/general/strings.status_workflow.transition_not_allowed`, all 3 locales) when the transition is disallowed — the form-level consumer calls this rather than re-deriving the check.

**Consumer mechanism: `App\Filament\Traits\HasStatusWorkflow`** (extracted from PurchaseRequest's own bespoke wiring 2026-09-26 into a reusable trait — see `app/Filament/filamentPattern.md` §1.12b for its full public API). `getStatusWorkflowField(string $column, ?Closure $extra)` scopes the Select's `relationship()` query (`modifyQueryUsing`) to exactly `[current, nextFor(current), …every unordered status of the type]` via `availableStatusWorkflowIds()`, and additionally `disableOptionWhen()`s any of those candidates `canSet()` rejects — Filament's own `in()` option-validity rule then rejects a disabled/out-of-scope selection at schema-validation time, before the record is ever touched (verified live: submitting a disabled option produces a form error from Filament's built-in rule, not from the resource's own mutation trait). `helperText()` names the actual granted approvers (`status_workflow.locked_helper_with_names`, 1–3 users) or falls back to the generic `status_workflow.locked_helper` (none/many) — Filament v4's `Select::disableOptionWhen()` has no per-option description/reason argument (confirmed against `Filament\Forms\Components\Concerns\CanDisableOptions`), so a single shared helper text is the sanctioned fallback per this doc's own convention. `assertStatusTransitionAllowed()` is the real trust boundary and must be called from every composing resource's own save path — **`PurchaseRequestResource` is the sole consumer so far**; `Traits/HandleStatusMutation::mutateStatusData()` calls `PurchaseRequestResource::assertStatusTransitionAllowed($record, 'status_id', $newStatusId)` before its existing approver-stamp logic, independent of the Select's own client-facing scoping (`PurchaseRequestResourceTest::test_server_side_transition_guard_rejects_a_skipped_stage_independently_of_the_select_options` exercises the trait directly, bypassing the Select entirely, to prove this; `HasStatusWorkflowTraitTest` exercises the multi-column-override contract point via a stub resource, since PurchaseRequest itself never exercises a non-default column). `applyInitialStatusOnCreate()` replaces the old direct `initialFor()`-in-the-mutator call — `Pages/CreatePurchaseRequest::mutateFormDataBeforeCreate()` now calls `PurchaseRequestResource::applyInitialStatusOnCreate($data)` (the form field itself is still `->disabled()` on the create operation, matching the existing `approver_id`/`approval_date` pattern — a disabled field is not dehydrated, so the create mutator is the only place the initial status is actually assigned).
- **"Return for Revision" / "Resubmit"** — `App\Filament\Actions\{ReturnForRevisionAction,ResubmitAction}` (generic, `make(string $typeConstant)`, reusable by any future `StatusWorkflow`-wired module, mirroring `ManageCustomAttributesAction`'s standalone-class shape). Both gate purely via Filament's own `->authorize(fn (?Model $record): bool => …)`, which doubles as both the visibility check and the execution-time guard (`CanBeAuthorized::isAuthorized()`/`CanBeHidden`) — no separate `abort_unless()` needed. `ReturnForRevisionAction` requires the acting user hold the record's *current* status's `approval_permission` (a status with no `approval_permission` cannot be returned-from at all), resets to `StatusWorkflow::initialFor($typeConstant)`, and requires a `reason` (`Textarea`, required) that must be persisted on the resulting `StatusHistory` row — see `TracksStatusHistory`'s `withStatusHistoryReason()` below. `ResubmitAction` is gated to the record's original creator (`user_id`, not a `creator_id` column — confirmed against `PurchaseRequest::$fillable`) and only when `status.english_name` is `Conditional`/`Declined`, also resetting to `initialFor($typeConstant)`. Neither action routes through `canSet()`/`assertAllowed()` — both are deliberate, permission-gated exceptions to the forward-only sequence, not violations of it.

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
5. Each result is composed via `compose()`: joins the config's `title`/`subtitle` column lists into strings, with type-aware formatting (`DateTimeInterface` → `Y-m-d`, `BackedEnum` → `->value`, scalars trimmed, stringable objects via `__toString`) — falls back to `'#'.$record->getKey()` if the composed title is empty. A scalar subtitle column listed in the config's optional `translate` map (`column => lang namespace`, keys = lowercase raw values) is localized via `translateColumn()` — unknown keys fall back to the raw value (e.g. `urgency_level` renders متوسط, not "medium").

Returned shape: `[{key: "{resource}:{id}", resourceId, recordId, label, subtitle, url}]` — `key` is what the frontend's `localStorage['user_shortcuts']` pin entries are keyed by. Resources declaring a `status` relation also return `status` (the localized status name, `''` when absent) — rendered as a chip in the picker rows AND on the pinned tile (pins persist it via `decorateRecord`; pins saved before 2026-10-10 have no `status` and simply hide the chip until re-pinned).

## `PredefinedOptions`

Discovery of columns with a fixed value set (enum cast, module form Select, boolean, low-cardinality scan) - resolution order and heuristics in `modelsPattern.md` section 11. Contract change for `NotificationSetting::sanitizeSettings()`: for a column with predefined options (`ModelInspector::predefinedOptions`) only values inside the option set survive; other columns keep type-based validation. Calendar: see `calendarPattern.md` section 12.
