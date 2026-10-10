Verified against source on branch `master` (2026-07-25). Where this doc conflicts with CLAUDE.md, this doc is authoritative for the global-helper + locale/RTL layer. `resources/css/stylesPattern.md` is authoritative for the `.tb-badge` CSS `tabBadge()` emits; `app/Models/modelsPattern.md` is authoritative for the `Localization` trait these helpers pair with.

# BMS-CM Global Helpers & Locale/RTL Pattern

`app/Utils/helpers.php` is the single home for cross-cutting presentation helpers — date formatting, currency, localized names, Filament-tab badges, cache management, and the Jalali calendar gate. Autoloaded as a `files` entry in `composer.json`, so every function is globally available with no `use` statement. There is exactly one helper file in this project — add to it, never create a second one.

## Core idea

Eleven pure functions, each one screen of logic, no classes, no state except a `static` color map. Two of them (`maybeJalali`, `getLocalizedName`) enforce the project's locale contract: locale is `fa` (Farsi/RTL), `en`, or `fr`; `fa` implies Jalali dates + the `name` column, every other locale implies Gregorian + the `english_name` column. The calendar-type gate lives once in `isJalaliCalendar()` (§2).

## Recommended structure

```
app/Utils/
    helpers.php            ← the single global-helper file (autoloaded via composer.json "files")
app/Livewire/
    CalendarToggle.php     ← another site of the calendar_type literal (the toggle producer)
app/Providers/
    FilamentMacroServiceProvider.php  ← another site (the `->adaptive()` DatePicker macro)
app/Models/Traits/General/
    Localization.php       ← the model-side locale gate (localeColumn() → name | english_name)
```

## 1. The ten helpers

All signatures verified against `app/Utils/helpers.php`.

### `toPersianDate(DateTime|string|null $date, bool $withTime = false): string`
Jalali display date via `morilog/jalali`'s `jdate()`. Null → `'-'`. Format `d F Y` (`$withTime` appends ` - H:i:s`, used on audit timestamp columns).

### `toGregorianDate(DateTime|string|null $date, bool $withTime = false): string`
Gregorian display date. Null → `'-'`. String dates coerced via `new DateTime()`. Format `Y F d` (deliberately different field order from `toPersianDate`, so the two are visually distinguishable) — `$withTime` appends ` - H:i:s`, mirroring `toPersianDate`. Pairs with it in `HasFormattedName::buildFormattedName()`.

### `getLocalizedName(object $record, string $relationship): ?string`
`fa` → `$record->{$relationship}->name`; else → `->english_name`. Null-safe on the relation. Helper form of the `Localization` trait's `localeColumn()` (`modelsPattern.md` §3) — use this for a relation in a Filament column/infolist; use the trait's own accessor when the localized field is on the record itself.

### `toYmdDate($record, DateTime|string|null $date = null): string`
ISO `Y-m-d` for export/PDF/sort. Omitted `$date` falls back to `$record->created_at` (or `'—'` em-dash if that's also null — intentionally different from the other date helpers' `'-'` hyphen fallback; don't normalize).

### `delimiter($value, ?string $currency = null, int $decimals = 2): string`
Single money formatter — never call `number_format()` directly for a money column. Null/`''` value → `'-'`. No currency → plain `number_format` (dot decimals, comma thousands). Currency present: a 1–4 letter alphabetic string (regex `/^[A-Za-z]{1,4}$/`, not real ISO-4217 validation — e.g. `Rial` matches) is appended uppercased (`1,234.50 USD`); anything else (symbol or 5+ letter word, e.g. `$`, `Toman`) is prepended (`$ 1,234.50`). No current call site passes `$currency` — the branch exists but is unexercised in practice. Use this specifically for **Filament Table columns**, where a fixed decimal count is desirable for column alignment — the one place fixed rounding is acceptable project-wide (see the precision standard below).

### `preciseNumber($value, ?string $currency = null, int $maxDecimals = 5): string`
Same currency-prefix/suffix logic as `delimiter()`, but formats to `$maxDecimals` and then `rtrim`s trailing zeros (and a trailing bare `.`) — `4` stays `4`, `4.56` stays `4.56`, `4.56000` never appears. Use this for **every non-table display** of a price/quantity/rate/weight metric (Infolist entries, form `hint()`s, `_display` companion fields) — see the precision standard below for why table vs. everywhere-else is the split.

### `isJalaliCalendar(): bool`
The single home of the `calendar_type` gate literal (§2). Every read site calls this — never re-inline the `session(...)` expression.

### `adaptiveDate($date, bool $withTime = false): string`
The display-date gate: `toPersianDate($date, $withTime)` in Jalali mode, `toGregorianDate($date, $withTime)` otherwise — driven by the calendar session, **not** the locale, so the panel's calendar toggle flips it. Use for standalone format sites (tooltips, descriptions); for Filament columns/entries use the `->adaptiveDate()`/`->adaptiveDateTime()` macros (which wrap this).

### `maybeJalali($component)`
The Jalali gate for Filament date components: calls `->jalali(true)` when in Jalali mode, else returns the component unchanged. Never inline the `session(...)` check in a resource; always route through this helper or `->adaptive()` (§2).

### `userCan(string $modelClass, string $action = 'view'): bool`
`auth()->user()?->can(Str::snake(class_basename($modelClass)).'.'.$action) ?? false` — the exact same permission-string formula `HasResourcePermissions::getPermissionPrefix()` uses (`app/Filament/filamentPattern.md` §1.5), exposed as a plain function so plain Services/Controllers outside the Filament layer (no Resource class to delegate to) can gate against the identical Spatie permission rows. Added 2026-09-17 to close a gap where `SearchController`'s two endpoints and `InvoiceController::shipmentPdf()` checked only `auth()->check()`/nothing — any authenticated user, regardless of their actual permissions, could read any pipeline model's full record (including Payment IBAN/SWIFT/amount) or download any Shipment's invoice PDF by id. See `servicesPattern.md`'s `SearchService`/`InvoicePdfService` sections for where it's now called.

### `tabBadge(string $label, int|string|null $count, string $color = 'info'): HtmlString`
Returns `{label} <span class="tb-badge tb-{color}">{count}</span>` for `Tab::make()->badge(...)` / infolist headers. Blank `$count` → bare escaped label, no badge. Valid colors: `info`/`success`/`warning`/`danger` → `tb-info`/`tb-success`/`tb-warning`/`tb-danger`; unknown falls back to `info`. Both args HTML-escaped via `e()`. Only PHP-side producer of `.tb-badge` markup — see §4.

### `clearApplicationCaches(): void`
Runs `opcache_reset()` first (guarded by `function_exists`, since not every environment has OPcache — deliberately first so an `Artisan::call()` failure below can't skip it), then `cache:clear`, `config:clear`, `route:clear`, `view:clear`, `optimize:clear`, `filament:clear-cached-components`, `permission:cache-reset` in sequence via `Artisan::call()`. Backs the `/clear` route. The OPcache reset only takes effect because this runs inside a real HTTP request on the same PHP-FPM/mod_php SAPI that serves production traffic — `opcache_reset()` run from `php artisan tinker` or any other CLI invocation resets a *different* OPcache instance and has no effect on what the web server actually serves when `opcache.validate_timestamps` is off. Same trap on the dev stack: `php artisan serve` is its own long-running PHP process with its own opcache memory (and `opcache.enable_cli` is `Off` here) — if a fix provably works from `tinker` but the browser keeps showing old behavior after cache clears, restart the actual server process itself; re-clearing from the CLI will never reach it.

### `cacheApplicationConfig(): void`
Runs `config:cache`, `route:cache`, `view:cache`, `filament:cache-components`. Backs the `/cache` route.

### `resetApplicationCache(): void`
`clearApplicationCaches()` → `sleep(1)` → `cacheApplicationConfig()`. The 1s pause is deliberate breathing room between clear and rebuild. Backs the `/reset` route and the panel user-menu "Reset Cache" action (the latter wraps the call in `dispatch(fn () => resetApplicationCache())->afterResponse()` — running it synchronously mid-Livewire-request breaks the rendering component, since it invalidates compiled views/Filament registry the current render depends on).

## 1b. Numeric precision standard (price/quantity/rate/weight)

Every column carrying real calculation precision — price, quantity, amount, rate, weight — is stored and computed at **up to 5 decimal places**, never 2. This applies at all layers: migration column scale (`decimal(65,5)` as of the 2026-09-17 widening — see §8b note below; scale stays 5, only the integer-digit capacity grew), Eloquent `$casts` (`'decimal:5'`), and Filament calculation traits (`TotalXxxCalculation`/`Calculation` traits must never `round()`/`number_format()` a computed total down to 2dp before `$set()`-ing it back into form state — that silently discards precision before it ever reaches the DB).

**2026-09-17 widening:** every such column was originally `decimal(15,5)` (max ~10 integer digits, ~9.99 billion), which overflowed in production on a legitimately large `purchase_requests.total_estimated_cost` (`SQLSTATE[22003]`). `database/migrations/2026_09_17_000000_widen_pipeline_decimal_precision.php` widened every one of these columns project-wide to `decimal(65,5)` — MySQL's absolute maximum total-digit precision — so no realistic figure can overflow again. No Filament-side `maxValue()`/`max:` validation existed on any numeric field before or after this change (only string-length `max:` rules on unrelated text fields) — the ceiling was purely the DB column width, and now there effectively isn't one.

**The only place a fixed, rounded display is acceptable is a Filament Table column** (`showXxx()` via `delimiter()`) — rounding there is a readability/alignment choice, not a precision bug, since the underlying stored value is untouched. Everywhere else (Infolist entries, form hints, `_display` fields) must show the value's actual meaningful precision — use `preciseNumber()`, not `delimiter()` or a raw `number_format()`, so `4` renders as `4` and `3.64583` renders in full rather than as `3.65` or `4.56000`.

Don't confuse this with `HasComputedAttributes`' unrounded float accessors (`app/Models/modelsPattern.md` §4) — those already return full-precision floats; the fix this standard targets is calculation traits that explicitly truncate before persisting, and display code that reformats to fewer/more decimals than the value actually has.

## 2. The `calendar_type` literal contract

```php
session('calendar_type', app()->isLocale('fa') ? 'jalali' : 'gregorian') === 'jalali'
```

The literal lives in exactly **one place**: `app/Utils/helpers.php` → `isJalaliCalendar()`. All consumers call it:
1. `maybeJalali()` (picker gate, helpers.php).
2. `FilamentMacroServiceProvider::boot()` → `DatePicker::macro('adaptive')` (pickers) and the `TextColumn`/`TextEntry` `->adaptiveDate()`/`->adaptiveDateTime()` display macros (which delegate to `adaptiveDate()`).
3. `app/Livewire/CalendarToggle.php` → `mount()` (initial toggle state).

Write side (the sole writer): `CalendarToggle::toggle()` → `session(['calendar_type' => $this->isJalali ? 'jalali' : 'gregorian'])`, then dispatches `calendar-toggled` (consumed via `#[On('calendar-toggled')]` on Filament pages).

Semantics: session key `calendar_type`, value `'jalali'` or `'gregorian'`. No session value → locale-driven default (`fa` → `'jalali'`, else → `'gregorian'`).

**Toggle refresh contract:** List/Manage pages re-render on `calendar-toggled` (no-op listener) — enough for tables/infolists, whose columns re-evaluate per request. Create/Edit pages **redirect** instead (`$this->redirect(request()->header('Referer'))`) — a jalali picker is a `wire:ignore`'d Alpine component whose view Livewire cannot morph in place, so a soft refresh would leave the stale-calendar picker on the form.

**Why one site:** the default expression was formerly copy-pasted byte-identically across three sites; any drift made the toggle's initial state and the pickers disagree on first load. Never inline a second copy of the literal anywhere — route through `isJalaliCalendar()`, `maybeJalali()`, `->adaptive()`, `->adaptiveDate()`, or `->adaptiveDateTime()`.

## 3. Locale & RTL conventions

- **Three locales:** `en`, `fa` (Farsi/RTL), `fr`, switched via `bezhansalleh/filament-language-switch`.
- **`fa` is the only RTL locale.** Every locale branch is `app()->getLocale() === 'fa'` (else covers `en`+`fr` together) — never branch per-`en`/`fr`.
- **Name columns:** `fa` → `name`; `en`/`fr` → `english_name`. Enforced in `getLocalizedName()`, the `Localization` trait's `localeColumn()` (`modelsPattern.md` §3), and nowhere else — prefer the helper/trait over a direct `$record->name` read.
- **Dates:** `fa` → `toPersianDate()`; `en`/`fr` → `toGregorianDate()`. The Jalali/Gregorian *calendar* is independently toggleable via `calendar_type` (§2) — locale and calendar are coupled by default but decoupled by the toggle. Display surfaces (columns/entries) route through the `->adaptiveDate()`/`->adaptiveDateTime()` macros or `adaptiveDate()` so the toggle actually flips them — never a locale ternary or a raw `->date()`/`->dateTime()`.
- **`$isRtl` (Blade):** landing-page and PDF views receive a single `$isRtl` bool prop, computed once at the page root, used for all layout-direction decisions (`{{ $isRtl ? 'right' : 'left' }}`, chevron rotation, slide direction). Don't recompute `app()->getLocale() === 'fa'` inside a partial that already receives it.
- **PDF/Invoice RTL:** `InvoicePdfService` sets dir/font/text-align from locale (Persian → IranYekan + RTL; else DejaVu + LTR) — same `fa` gate, applied at the mPDF layer.

## 4. `tabBadge` ↔ `.tb-badge` CSS coupling

| Helper color arg | Emitted class | CSS family |
|---|---|---|
| `'info'` (default) | `tb-badge tb-info` | blue |
| `'success'` | `tb-badge tb-success` | green |
| `'warning'` | `tb-badge tb-warning` | amber |
| `'danger'` | `tb-badge tb-danger` | red |

CSS lives in `resources/css/fi-custom.css` (see `stylesPattern.md`). Adding a fifth color requires both (1) a key in `tabBadge()`'s `$colorClasses` map and (2) a matching `.tb-{name}` rule in `fi-custom.css` — either alone leaves a badge unstyled or a CSS class dead.

## 5. Autoloading

```json
"autoload": { "files": ["app/Utils/helpers.php"] }
```
This `files` entry is what makes every function globally available without `use`. After adding a helper, run `composer dump-autoload` once for it to register in an already-running process. Each function is wrapped in `if (!function_exists(...))` for idempotent re-autoload safety.

## 6. Developer Decision Matrix

| When you need to… | Do this… | Why… |
|---|---|---|
| Format a date for Persian display | `toPersianDate($date)` | Empty → `'-'`. |
| Format a date for Gregorian display | `toGregorianDate($date)` | Note `Y F d` order vs Persian's `d F Y`. |
| Show a timestamp *with* time-of-day | `toPersianDate($state, true)` / `toGregorianDate($state, true)` | Appends ` - H:i:s`; use on `created_at`/`updated_at`. |
| Format a date for export/PDF/sort | `toYmdDate($record, $date)` | ISO `Y-m-d`; falls back to `$record->created_at`. |
| Show a relation's localized name | `getLocalizedName($record, 'relation')` | Helper form of `Localization::localeColumn()`; null-safe. |
| Show a localized field on the model itself | `Localization` trait's `getLocalizedNameAttribute` | Don't re-implement the `fa`/`else` gate inline. |
| Format money ± currency in a Table column | `delimiter($value, $currency, $decimals)` | Fixed rounding is acceptable only in Tables — §1b. |
| Format a price/quantity/rate/weight value anywhere else (Infolist, hint, `_display` field) | `preciseNumber($value, $currency, $maxDecimals)` | Shows meaningful precision, no padded/truncated zeros — §1/§1b. |
| Make a date picker respect Jalali | `maybeJalali(DatePicker::make(...))` or `->adaptive()` | Keeps the `calendar_type` gate in one place — §2. |
| Format a display date that follows the calendar toggle | `->adaptiveDate()` / `->adaptiveDateTime()` on the column/entry, or `adaptiveDate($date)` at a standalone site | Raw `->date()`/`->dateTime()` and locale ternaries ignore the toggle — enforced by `tests/Feature/Helpers/AdaptiveDateTest.php`. |
| Add a count badge to a Tab/infolist header | `tabBadge($label, $count, $color)` | Only producer of `.tb-badge` markup — §1/§4. |
| Clear all caches | `clearApplicationCaches()` | Backs `/clear`; also the first half of `resetApplicationCache()`. |
| Rebuild all caches | `cacheApplicationConfig()` | Backs `/cache`; also the second half of `resetApplicationCache()`. |
| Clear + rebuild in one call | `resetApplicationCache()` | Backs `/reset` and the panel's "Reset Cache" menu action. |
| Change the default calendar rule | Edit the literal in `isJalaliCalendar()` only | Single home — every consumer follows automatically — §2. |
| Add a new global helper | Append to `helpers.php` inside `if (!function_exists(...))`; `composer dump-autoload` | One helper file — §5. |

## 7. Absolute Anti-Patterns

- ❌ Inlining `session('calendar_type', ...)` in a resource or view — duplicates the load-bearing literal (§2); route through `isJalaliCalendar()`/`maybeJalali()`/`->adaptive()`.
- ❌ A locale-ternary date branch (`app()->getLocale() === 'fa' ? toPersianDate(...) : toGregorianDate(...)`) or a raw `->date()`/`->dateTime()` on a display column/entry — both ignore the calendar toggle; use the adaptive macros (§2, enforced by `tests/Feature/Helpers/AdaptiveDateTest.php`).
- ❌ Calling `number_format()` directly for a money column — use `delimiter()` in a Table column, `preciseNumber()` everywhere else.
- ❌ Rounding/truncating a price/quantity/rate/weight value to 2 decimals anywhere outside a Table column — the standard is up to 5 decimals at DB/model/computation layers; only Table display may round for readability (§1b).
- ❌ Hand-writing `<span class="tb-badge tb-info">N</span>` — use `tabBadge()`.
- ❌ Branching per-`en`/per-`fr` — the contract is binary, `fa` vs everything-else.
- ❌ Reading `$record->name`/`$record->english_name` directly in a Filament column — use `getLocalizedName()` or the trait accessor.
- ❌ Creating a second helper file — there is one, autoloaded as one `files` entry.
- ❌ Recomputing `app()->getLocale() === 'fa'` in a Blade partial that already receives `$isRtl`.
- ❌ Normalizing the `'-'` / `'—'` fallback discrepancy between the date helpers — intentional, not a bug.
- ❌ Calling the Artisan cache commands directly/ad hoc instead of the three cache helpers — keeps the command list in one place instead of drifting across `/clear`, `/cache`, `/reset`, and the panel menu action (this drifted once already).

## 8. Naming conventions

- **File:** `app/Utils/helpers.php` — the single global-helper file.
- **Functions:** `snake_case` or `camelCase` per existing name, no namespace, each wrapped in `if (!function_exists('name'))`.
- **Date helpers:** `toPersianDate` / `toGregorianDate` / `toYmdDate` — the `to…Date` family.
- **Locale helper:** `getLocalizedName` (relation form); on-model accessor is `getLocalizedNameAttribute` (`Localization` trait).
- **Money helper (Table columns):** `delimiter($value, $currency, $decimals)`.
- **Precision helper (everywhere else):** `preciseNumber($value, $currency, $maxDecimals)` — trims trailing zeros, default cap 5 decimals.
- **Calendar gate:** `isJalaliCalendar(): bool` (the predicate) / `maybeJalali($component)` (pickers) / `adaptiveDate($date, $withTime)` (standalone display sites).
- **Badge helper:** `tabBadge($label, $count, $color)` — 4-color map, §1/§4.
- **Cache helpers:** `clearApplicationCaches` / `cacheApplicationConfig` / `resetApplicationCache` — all no-arg, void-return, `Artisan::call()`-based.
- **Session key:** `calendar_type` (values `'jalali'`/`'gregorian'`); **Livewire event:** `calendar-toggled`.
- **Autoload:** `composer.json` → `autoload.files` → `app/Utils/helpers.php`.

## 9. Related dedicated lang namespaces (pointer only)

Two content lang namespaces outside `resources/{module}/strings.php` exist elsewhere in the codebase and are **not** part of `helpers.php`:
- `deskReference/{group}` — Desk Reference feature content, documented in `app/Filament/filamentPattern.md` §1.27.
- `dashboard/strings.greetings` — `AccountWidget` time-of-day greetings via `App\Services\GreetingService`, not a global helper.

Don't duplicate their conventions here — follow the cross-reference.

## 10. Date display audit

- **Fixed (now `adaptiveDate()` / `->adaptiveDate()`):** BankProfile/Custom/Shipment infolist date entries (were forced `->jalaliDate()`); Target table + infolist dates (locale-gated `->jalaliDate()` removed); RegisteredOrder/PurchaseOrder/Payment `po_number`/`order_date`/`payment_date` tooltips; User `last_log_in` tooltip; `getGlobalSearchResultDetails()` of RegisteredOrder/PurchaseRequest/PurchaseOrder/ProformaInvoice/Payment; Shipment invoice Select option labels; Correspondence read-receipt label; calendar-rule preview list (`filament/calendar/preview.blade.php`); `SearchService::d()` (spotlight/chain details, `formatDate()` locale ternary gone); `WorkspaceSearchService::compose()` date parts; Calendar Rules condition builder (date pickers of the After/Before/Is-date operators follow the toggle via `maybeJalali`, stored value stays `Y-m-d`; operator summaries and the infolist condition lines use `adaptiveDate()`).
- **Queued mail/notification rule:** no session exists in a queue worker, so `ModelEventEmail` change values (DateTime instances or `Y-m-d[ H:i[:s]]` strings) follow the sending process's locale: `fa` → `toPersianDate`, otherwise `toGregorianDate`. Do not use `adaptiveDate()` there.
- **Verified clean:** Filament table/infolist date columns (all `adaptiveDate*`), calendar grid/day/agenda/activity views (`CalendarPresenter`, `CalendarActivityAction`), landing `Attention`, status-history entries, database-notification renderer (relative time), dashboard widget labels, `AccountWidget` (shows both calendars by design).
- **Left by design:** `isMonth`/`isYear` condition operators (Gregorian numbers matching the DB), Gregorian month names in `toGregorianDate()` are English in `fr` (helper-wide format), exporters (`jalaliDate()`/`jdate` Y-m-d, stable CSV format), commercial-invoice PDF `invoice_date` (ISO on an external document), `CalendarAlertNotification` (`alert_date`/`cal_date` are machine values), error-page timestamp (locale-based, no session guaranteed), disabled `last_log_in` form picker (never rendered; Master modules are infolist-only).
- **Not verifiable without a browser:** visual rendering of the Jalali/Gregorian toggle on the changed tooltips and Select labels.
