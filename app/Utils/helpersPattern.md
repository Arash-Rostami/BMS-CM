Verified against source on branch `master` (2026-07-25). Where this doc conflicts with CLAUDE.md, this doc is authoritative for the global-helper + locale/RTL layer. `resources/css/stylesPattern.md` is authoritative for the `.tb-badge` CSS `tabBadge()` emits; `app/Models/modelsPattern.md` is authoritative for the `Localization` trait these helpers pair with.

# BMS-CM Global Helpers & Locale/RTL Pattern

`app/Utils/helpers.php` is the single home for cross-cutting presentation helpers — date formatting, currency, localized names, Filament-tab badges, cache management, and the Jalali calendar gate. Autoloaded as a `files` entry in `composer.json`, so every function is globally available with no `use` statement. There is exactly one helper file in this project — add to it, never create a second one.

## Core idea

Ten pure functions, each one screen of logic, no classes, no state except a `static` color map. Two of them (`maybeJalali`, `getLocalizedName`) enforce the project's locale contract: locale is `fa` (Farsi/RTL), `en`, or `fr`; `fa` implies Jalali dates + the `name` column, every other locale implies Gregorian + the `english_name` column. The calendar-type gate's byte-identical three-site literal contract is detailed in §2.

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
Single money formatter — never call `number_format()` directly for a money column. Null/`''` value → `'-'`. No currency → plain `number_format` (dot decimals, comma thousands). Currency present: a 1–4 letter alphabetic string (regex `/^[A-Za-z]{1,4}$/`, not real ISO-4217 validation — e.g. `Rial` matches) is appended uppercased (`1,234.50 USD`); anything else (symbol or 5+ letter word, e.g. `$`, `Toman`) is prepended (`$ 1,234.50`). No current call site passes `$currency` — the branch exists but is unexercised in practice.

### `maybeJalali($component)`
The Jalali gate for Filament date components: calls `->jalali(true)` when in Jalali mode, else returns the component unchanged. The gate expression is the load-bearing literal — see §2. Never inline the `session(...)` check in a resource; always route through this helper or `->adaptive()` (§2).

### `tabBadge(string $label, int|string|null $count, string $color = 'info'): HtmlString`
Returns `{label} <span class="tb-badge tb-{color}">{count}</span>` for `Tab::make()->badge(...)` / infolist headers. Blank `$count` → bare escaped label, no badge. Valid colors: `info`/`success`/`warning`/`danger` → `tb-info`/`tb-success`/`tb-warning`/`tb-danger`; unknown falls back to `info`. Both args HTML-escaped via `e()`. Only PHP-side producer of `.tb-badge` markup — see §4.

### `clearApplicationCaches(): void`
Runs `cache:clear`, `config:clear`, `route:clear`, `view:clear`, `optimize:clear`, `filament:clear-cached-components` in sequence via `Artisan::call()`. Backs the `/clear` route.

### `cacheApplicationConfig(): void`
Runs `config:cache`, `route:cache`, `view:cache`, `filament:cache-components`. Backs the `/cache` route.

### `resetApplicationCache(): void`
`clearApplicationCaches()` → `sleep(1)` → `cacheApplicationConfig()`. The 1s pause is deliberate breathing room between clear and rebuild. Backs the `/reset` route and the panel user-menu "Reset Cache" action (the latter wraps the call in `dispatch(fn () => resetApplicationCache())->afterResponse()` — running it synchronously mid-Livewire-request breaks the rendering component, since it invalidates compiled views/Filament registry the current render depends on).

## 2. The `calendar_type` literal contract

```php
session('calendar_type', app()->isLocale('fa') ? 'jalali' : 'gregorian') === 'jalali'
```

Appears byte-identically in **three places** and must stay identical in all of them:
1. `app/Utils/helpers.php` → `maybeJalali()` (read side).
2. `app/Providers/FilamentMacroServiceProvider.php` → `DatePicker::macro('adaptive')` in `boot()` (sibling read side; date pickers commonly chain `->adaptive()` instead of/alongside `maybeJalali()`).
3. `app/Livewire/CalendarToggle.php` → `mount()` (read side, initial toggle state).

Write side (not part of the byte-identical literal, but the sole writer): `CalendarToggle::toggle()` → `session(['calendar_type' => $this->isJalali ? 'jalali' : 'gregorian'])`, then dispatches `calendar-toggled` (consumed via `#[On('calendar-toggled')]` on Filament pages).

Semantics: session key `calendar_type`, value `'jalali'` or `'gregorian'`. No session value → locale-driven default (`fa` → `'jalali'`, else → `'gregorian'`).

**Why byte-identical:** if the default expression drifts between the three sites, the toggle's initial state and the date pickers' actual calendar disagree on first load. Never inline a fourth copy of this literal anywhere — route through `maybeJalali()` or `->adaptive()`.

## 3. Locale & RTL conventions

- **Three locales:** `en`, `fa` (Farsi/RTL), `fr`, switched via `bezhansalleh/filament-language-switch`.
- **`fa` is the only RTL locale.** Every locale branch is `app()->getLocale() === 'fa'` (else covers `en`+`fr` together) — never branch per-`en`/`fr`.
- **Name columns:** `fa` → `name`; `en`/`fr` → `english_name`. Enforced in `getLocalizedName()`, the `Localization` trait's `localeColumn()` (`modelsPattern.md` §3), and nowhere else — prefer the helper/trait over a direct `$record->name` read.
- **Dates:** `fa` → `toPersianDate()`; `en`/`fr` → `toGregorianDate()`. The Jalali/Gregorian *calendar* is independently toggleable via `calendar_type` (§2) — locale and calendar are coupled by default but decoupled by the toggle.
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
| Format money ± currency | `delimiter($value, $currency, $decimals)` | Single money formatter — §1. |
| Make a date picker respect Jalali | `maybeJalali(DatePicker::make(...))` or `->adaptive()` | Keeps the `calendar_type` literal in one place — §2. |
| Add a count badge to a Tab/infolist header | `tabBadge($label, $count, $color)` | Only producer of `.tb-badge` markup — §1/§4. |
| Clear all caches | `clearApplicationCaches()` | Backs `/clear`; also the first half of `resetApplicationCache()`. |
| Rebuild all caches | `cacheApplicationConfig()` | Backs `/cache`; also the second half of `resetApplicationCache()`. |
| Clear + rebuild in one call | `resetApplicationCache()` | Backs `/reset` and the panel's "Reset Cache" menu action. |
| Change the default calendar rule | Edit the literal in `maybeJalali()`, `CalendarToggle::mount()`, AND `adaptive()` identically | Keeps the three literal sites in sync — §2. |
| Add a new global helper | Append to `helpers.php` inside `if (!function_exists(...))`; `composer dump-autoload` | One helper file — §5. |

## 7. Absolute Anti-Patterns

- ❌ Inlining `session('calendar_type', ...)` in a resource or view — duplicates the load-bearing literal (§2); route through `maybeJalali()`/`->adaptive()`.
- ❌ Letting the `calendar_type` default differ between the three sites in §2.
- ❌ Calling `number_format()` directly for a money column — use `delimiter()`.
- ❌ Hand-writing `<span class="tb-badge tb-info">N</span>` — use `tabBadge()`.
- ❌ Branching per-`en`/per-`fr` — the contract is binary, `fa` vs everything-else.
- ❌ Reading `$record->name`/`$record->english_name` directly in a Filament column — use `getLocalizedName()` or the trait accessor.
- ❌ Creating a second helper file — there is one, autoloaded as one `files` entry.
- ❌ Recomputing `app()->getLocale() === 'fa'` in a Blade partial that already receives `$isRtl`.
- ❌ Normalizing the `'-'` / `'—'` fallback discrepancy between the date helpers — intentional, not a bug.
- ❌ Calling the Artisan cache commands directly/ad hoc instead of the three cache helpers — keeps the command list in one place instead of drifting across `/clear`, `/cache`, `/reset`, and the panel menu action (this drifted once already; see CLAUDE.md Latest Changes 2026-07-25 for the incident).

## 8. Naming conventions

- **File:** `app/Utils/helpers.php` — the single global-helper file.
- **Functions:** `snake_case` or `camelCase` per existing name, no namespace, each wrapped in `if (!function_exists('name'))`.
- **Date helpers:** `toPersianDate` / `toGregorianDate` / `toYmdDate` — the `to…Date` family.
- **Locale helper:** `getLocalizedName` (relation form); on-model accessor is `getLocalizedNameAttribute` (`Localization` trait).
- **Money helper:** `delimiter($value, $currency, $decimals)`.
- **Calendar gate:** `maybeJalali($component)`.
- **Badge helper:** `tabBadge($label, $count, $color)` — 4-color map, §1/§4.
- **Cache helpers:** `clearApplicationCaches` / `cacheApplicationConfig` / `resetApplicationCache` — all no-arg, void-return, `Artisan::call()`-based.
- **Session key:** `calendar_type` (values `'jalali'`/`'gregorian'`); **Livewire event:** `calendar-toggled`.
- **Autoload:** `composer.json` → `autoload.files` → `app/Utils/helpers.php`.

## 9. Related dedicated lang namespaces (pointer only)

Two content lang namespaces outside `resources/{module}/strings.php` exist elsewhere in the codebase and are **not** part of `helpers.php`:
- `deskReference/{group}` — Desk Reference feature content, documented in `app/Filament/filamentPattern.md` §1.27.
- `dashboard/strings.greetings` — `AccountWidget` time-of-day greetings via `App\Services\GreetingService`, not a global helper.

Don't duplicate their conventions here — follow the cross-reference.
