Verified against source on branch `feature/landing-page-enterprise-redesign` (2026-07-18). Where this doc conflicts with CLAUDE.md, this doc is authoritative for the global-helper + locale/RTL layer. `resources/css/stylesPattern.md` remains authoritative for the `.tb-badge` CSS that `tabBadge()` emits; `app/Models/modelsPattern.md` is authoritative for the `Localization` trait these helpers pair with.

# BMS-CM Global Helpers & Locale/RTL Pattern

`app/Utils/helpers.php` is the single home for cross-cutting presentation helpers — date formatting, currency, localized names, Filament-tab badges, and the Jalali calendar gate. It is autoloaded as a `files` entry (verified `composer.json` line 44–46), so every function is globally available with no `use` statement. There is exactly one helper file in this project; do not create a second one — add to this file. This doc also owns the **locale / RTL / calendar-type contract** (the `session('calendar_type', …)` literal) because that contract is what the helpers enforce; it is not split into a separate views doc.

## Core idea

Seven pure functions, each one screen of logic, no classes, no state except a `static` color map. Two of them (`maybeJalali`, `getLocalizedName`) are the runtime enforcement of the project's locale contract: the app's locale is `fa` (Farsi/RTL), `en`, or `fr`; `fa` implies Jalali dates + the `name` column, every other locale implies Gregorian + the `english_name` column. The calendar-type gate is a **literal** that must stay byte-identical in three places — `maybeJalali()` here, `CalendarToggle::mount()`, and `DatePicker::macro('adaptive')` in `FilamentMacroServiceProvider::boot()` — or the toggle, the date pickers, and the `->adaptive()` macro drift out of sync.

## Recommended structure

```
app/Utils/
    helpers.php            ← the single global-helper file (autoloaded via composer.json "files")
app/Livewire/
    CalendarToggle.php     ← the other site of the calendar_type literal (the toggle producer)
app/Models/Traits/General/
    Localization.php        ← the model-side locale gate (localeColumn() → name | english_name)
```

## 1. The seven helpers

All signatures/behavior verified in `app/Utils/helpers.php`:

### `toPersianDate`
```php
function toPersianDate(DateTime|string|null $date): string
{
    if (!$date) return '-';
    return jdate($date)->format('d F Y');
}
```
Persian (Jalali) display date via `morilog/jalali`'s `jdate()`. Empty/null → `'-'`. Format is `d F Y` (e.g. `15 تیر 1404`). Used by `HasFormattedName` (see `modelsPattern.md` §4) and any Persian-facing date column.

### `toGregorianDate`
```php
function toGregorianDate(DateTime|string|null $date): string
{
    if (!$date) return '-';
    $date = is_string($date) ? new DateTime($date) : $date;
    return $date->format('Y F d');
}
```
Gregorian display date. Empty/null → `'-'`. Accepts a `DateTime` or a string (coerced via `new DateTime()`). Format is `Y F d` (e.g. `2026 July 19`) — note the deliberately different field order from `toPersianDate` so the two are visually distinguishable in mixed-locale UIs. Pairs with `toPersianDate` inside `HasFormattedName::buildFormattedName()` — `fa` calls the Persian one, every other locale calls this one.

### `getLocalizedName`
```php
function getLocalizedName(object $record, string $relationship): ?string
{
    return app()->getLocale() === 'fa'
        ? $record->{$relationship}?->name
        : $record->{$relationship}?->english_name;
}
```
Resolves a relation's locale-correct name: `fa` → the `name` column, else → `english_name`. Null-safe on the relation (`?->`). This is the helper form of the `Localization` trait's `localeColumn()` rule (see `modelsPattern.md` §3) — use this in Filament columns/infolist entries where you have a `$record` + a relation name; use the trait's `getLocalizedNameAttribute` on the model itself when the localized field is on the record directly.

### `toYmdDate`
```php
function toYmdDate($record, DateTime|string|null $date = null): string
{
    if (!$date) return $record->created_at ? $record->created_at->format('Y-m-d') : '—';
    $date = is_string($date) ? new DateTime($date) : $date;
    return $date->format('Y-m-d');
}
```
ISO `Y-m-d` for export/PDF/sorting. When `$date` is omitted, it falls back to the record's `created_at` (or `'—'` if that is also null) — so `toYmdDate($record)` is a safe "when was this created" formatter. Accepts `DateTime` or string. Note the em-dash fallback `'—'` here vs the hyphen `'-'` in the date helpers above — do not normalize; the two are intentional.

### `delimiter`
```php
function delimiter($value, ?string $currency = null, int $decimals = 2): string
{
    if ($value === null || $value === '') return '-';
    $formatted = number_format((float)$value, $decimals, '.', ',');
    if (!$currency) return $formatted;
    $currency = (string)$currency;
    if (preg_match('/^[A-Za-z]{1,4}$/', $currency)) return $formatted . ' ' . strtoupper($currency);
    return $currency . ' ' . $formatted;
}
```
Money + currency formatter. Rules:
- `null`/`''` value → `'-'`.
- No currency → `number_format` only (dot decimals, comma thousands, default 2 decimals).
- A 1–4 letter *purely alphabetic* string (ISO 4217 shape: `USD`, `EUR`, `IRR` — but also any other 1–4 letter word, e.g. `Rial`) → **appended uppercased after a space** (`1,234.50 USD`, `1,234.50 RIAL`).
- Anything else (a non-letter symbol, or a 5+ letter label like `$`, `€`, `Toman`) → **prepended before a space** (`$ 1,234.50`).
The branch decision is a regex (`/^[A-Za-z]{1,4}$/`) on the currency string's *length and character class*, not its ISO-4217 validity — so a short non-code word like `Rial` is treated as a code (appended, uppercased) while a longer word like `Toman` is treated as a label (prepended). No real call site in this codebase currently passes a `$currency` argument — every existing `delimiter()` call site passes only `$value` — so this branch is implemented but currently unexercised in practice. This is the single money formatter for tables, infolists, and exports — do not call `number_format` directly elsewhere.

### `maybeJalali`
```php
function maybeJalali($component)
{
    return session('calendar_type', app()->isLocale('fa') ? 'jalali' : 'gregorian') === 'jalali'
        ? $component->jalali(true)
        : $component;
}
```
The Jalali gate for Filament date components. Wraps any `DatePicker`/`DateTimePicker`: in Jalali mode it calls `->jalali(true)` (the `mokhosh/filament-jalali` integration), otherwise returns the component unchanged. The gate expression is the **load-bearing literal** — see §2. Real usage (`ShipmentResource/Traits/InvoiceForm.php`):
```php
maybeJalali(
    DatePicker::make('_inv_invoice_date')
        ->label(__('resources/shipment/strings.invoice.invoice_date'))
        ->native(false)
        ->adaptive()
        ->dehydrated(false)
),
```
Note it is commonly chained with `->adaptive()`, the sibling `DatePicker` macro registered in `FilamentMacroServiceProvider::boot()` (§2) — the two independently re-check the same `calendar_type` literal. Never inline the `session(...)` check in a resource; always route through this helper so the contract stays in one place.

### `tabBadge`
```php
function tabBadge(string $label, int|string|null $count, string $color = 'info'): HtmlString
{
    if (blank($count)) return new HtmlString(e($label));
    static $colorClasses = [
        'info' => 'tb-info',
        'success' => 'tb-success',
        'warning' => 'tb-warning',
        'danger' => 'tb-danger',
    ];
    $class = $colorClasses[$color] ?? $colorClasses['info'];
    return new HtmlString(sprintf('%s <span class="tb-badge %s">%s</span>', e($label), $class, e($count)));
}
```
Returns a `HtmlString` of `{label} <span class="tb-badge tb-{color}">{count}</span>` for Filament `Tab::make()->badge(...)` / infolist headers. Empty/blank count → bare escaped label (no badge). Color map (the only four valid keys): `info` / `success` / `warning` / `danger` → `tb-info` / `tb-success` / `tb-warning` / `tb-danger`. Unknown color falls back to `info`. Both `label` and `count` are HTML-escaped via `e()`. The `.tb-badge` + `.tb-{color}` classes are defined in `resources/css/fi-custom.css` (see `stylesPattern.md`) — this helper is the ONLY producer of those classes in PHP; do not hand-write the `<span>` markup.

## 2. The `calendar_type` literal contract

The expression below is the project's single source of truth for "is the current calendar Jalali?":

```php
session('calendar_type', app()->isLocale('fa') ? 'jalali' : 'gregorian') === 'jalali'
```

It appears **byte-identically in three places** and must stay identical in all of them:
1. `app/Utils/helpers.php` → `maybeJalali()` (the read side — every Filament date picker wrapped with the helper).
2. `app/Livewire/CalendarToggle.php` → `mount()` (the write side — the toggle's initial state).
3. `app/Providers/FilamentMacroServiceProvider.php` → `DatePicker::macro('adaptive')` in `boot()` (the in-chain sibling read side — date pickers call `->adaptive()` instead of/alongside wrapping with `maybeJalali()`).

Semantics:
- The session key is `calendar_type`. Its value is the string `'jalali'` or `'gregorian'`.
- When the session has no value, the **default is locale-driven**: `fa` → `'jalali'`, any other locale → `'gregorian'`. So a Persian user gets Jalali dates automatically until they toggle; an English/French user gets Gregorian until they toggle to Jalali.
- `CalendarToggle::toggle()` flips the value, writes it back to the session, and dispatches the `calendar-toggled` Livewire event (consumed by Filament pages via `#[On('calendar-toggled')]` — see `scriptPattern.md` Custom Events).

**Why byte-identical:** if the default expression in `maybeJalali()` ever drifts from the one in `CalendarToggle::mount()` or the `adaptive()` macro (e.g. one says `isLocale('fa')` and another checks a different locale), the toggle's initial checkbox state and the date pickers' actual calendar will disagree on first load. Changing the default rule is a three-file edit; changing the session key is a four-file edit (these three plus any reader). Never inline a further, fourth copy of the `session('calendar_type', …)` literal anywhere else — route through `maybeJalali()` or `->adaptive()`.

## 3. Locale & RTL conventions

The locale contract these helpers enforce, stated plainly:

- **Three locales:** `en`, `fa` (Farsi/RTL), `fr`. Switched via `bezhansalleh/filament-language-switch` (see `filamentPattern.md` configurators).
- **`fa` is the only RTL locale.** Every locale-driven branch in this codebase is `app()->getLocale() === 'fa'` (the `else` covers `en` + `fr` together); there is no per-`en`/`fr` branching.
- **Name columns:** `fa` → the `name` column; `en`/`fr` → the `english_name` column. Enforced in three places that must agree — `getLocalizedName()` here, the `Localization` trait's `localeColumn()` on the model (`modelsPattern.md` §3), and any direct `$record->name` / `$record->english_name` read (prefer the helper or the trait accessor over a direct read).
- **Dates:** `fa` → `toPersianDate()` (Jalali); `en`/`fr` → `toGregorianDate()`. The Jalali/Gregorian *calendar* is independently toggleable via `calendar_type` (§2) — locale and calendar are coupled by default but decoupled by the toggle.
- **`$isRtl` (Blade):** the landing-page and PDF views receive a `$isRtl` bool prop (the single source of truth for layout direction in Blade — `{{ $isRtl ? 'right' : 'left' }}` anchors, chevron rotation, slide direction). It is computed once at the page root and passed down; sub-components receive only the props they need. See `filamentPattern.md` / `scriptPattern.md` for the prop's consumers. Do not recompute `app()->getLocale() === 'fa'` inside a Blade partial — use the passed `$isRtl`.
- **PDF/Invoice RTL:** `InvoicePdfService` sets `dir`/font/text-align from the locale (Persian → IranYekan + RTL; else DejaVu + LTR). The same `fa`-gate, applied at the mPDF layer.

## 4. `tabBadge` ↔ `.tb-badge` CSS coupling

`tabBadge()` emits exactly four class combinations, and the CSS for them lives in `resources/css/fi-custom.css`:

| Helper color arg | Emitted class | CSS family |
|---|---|---|
| `'info'` (default) | `tb-badge tb-info` | blue |
| `'success'` | `tb-badge tb-success` | green |
| `'warning'` | `tb-badge tb-warning` | amber |
| `'danger'` | `tb-badge tb-danger` | red |

This is the **only** PHP-side producer of `.tb-badge` markup. To add a fifth color you must (1) add the key to the `$colorClasses` map here AND (2) add the `.tb-{name}` rule to `fi-custom.css` — either change alone leaves a badge unstyled or a CSS class dead. See `stylesPattern.md` for the `.tb-badge` rule definitions.

## 5. Autoloading

Verified `composer.json`:
```json
"autoload": {
    "psr-4": { … },
    "files": [
        "app/Utils/helpers.php"
    ]
}
```
The `files` array is what makes the seven functions globally available without `use`. After adding a new helper to `helpers.php`, run `composer dump-autoload` (or any `composer` command that rebuilds the autoload map) once for the new function to be registered in an already-running process. Each function is wrapped in `if (!function_exists(...))` to stay idempotent across re-autoloads and safe if a third-party package ever ships a same-named function.

## 6. Developer Decision Matrix

| When you need to… | Do this… | Why… |
|---|---|---|
| Format a date for Persian display | `toPersianDate($date)` | The `fa` branch of `HasFormattedName`; empty → `'-'`. |
| Format a date for Gregorian display | `toGregorianDate($date)` | The non-`fa` branch; note `Y F d` order vs Persian's `d F Y`. |
| Format a date for export/PDF/sort | `toYmdDate($record, $date)` | ISO `Y-m-d`; falls back to `$record->created_at`. |
| Show a relation's localized name in a column | `getLocalizedName($record, 'relation')` | The helper form of `Localization::localeColumn()`; null-safe. |
| Show a localized field on the model itself | Use the `Localization` trait's `getLocalizedNameAttribute` | Don't re-implement the `fa`/`else` gate inline. |
| Format money ± currency | `delimiter($value, $currency, $decimals)` | ISO codes append uppercased; symbols prepend. Single money formatter. |
| Make a Filament date picker respect Jalali | `maybeJalali(DatePicker::make(...))` | Keeps the `calendar_type` literal in one place (§2). |
| Add a count badge to a Tab/infolist header | `tabBadge($label, $count, $color)` | Only producer of `.tb-badge` markup; color map is fixed at 4. |
| Change the default calendar rule | Edit the literal in `maybeJalali()`, `CalendarToggle::mount()`, AND the `adaptive()` macro identically | Drift between the three desyncs the toggle's initial state from the pickers (§2). |
| Add a fifth badge color | Add the key to `tabBadge()`'s `$colorClasses` AND add `.tb-{name}` to `fi-custom.css` | Helper and CSS must move together (§4). |
| Add a new global helper | Append to `app/Utils/helpers.php` inside `if (!function_exists(...))`; `composer dump-autoload` | One helper file, autoloaded via `files` (§5). |
| Branch on locale anywhere | Use `app()->getLocale() === 'fa'` (else covers `en`+`fr`) | The only locale gate in the project; never branch per-`en`/`fr`. |

## 7. Absolute Anti-Patterns (Do Not Do This)

- ❌ **Inlining `session('calendar_type', ...)` in a resource or view.**
  - Why: it duplicates the load-bearing literal (§2). Always go through `maybeJalali()`; add a new helper if a third site needs the raw gate.

- ❌ **Letting the `calendar_type` default differ between `maybeJalali()`, `CalendarToggle::mount()`, and the `adaptive()` macro.**
  - Why: the toggle's initial state and the date pickers' calendar will disagree on first load. The three literals must stay byte-identical.

- ❌ **Calling `number_format()` directly for a money column.**
  - Why: `delimiter()` is the single money formatter and owns the ISO-code-vs-symbol side rule. Bypassing it produces inconsistent currency placement.

- ❌ **Hand-writing `<span class="tb-badge tb-info">N</span>` in a Blade/PHP file.**
  - Why: `tabBadge()` is the only producer; hand-writing it bypasses the color-map fallback and the `e()` escaping, and desyncs from any future CSS class rename (§4).

- ❌ **Branching per-`en` / per-`fr`.**
  - Why: the locale contract is binary — `fa` vs everything-else. A per-`en`/`fr` branch implies a third date/name convention that does not exist.

- ❌ **Reading `$record->name` / `$record->english_name` directly in a Filament column.**
  - Why: it skips the locale gate. Use `getLocalizedName($record, 'relation')` for a relation, or the `Localization` trait's accessor for a field on the record.

- ❌ **Creating a second helper file (e.g. `app/Utils/format.php`).**
  - Why: there is one helper file, autoloaded as one `files` entry. Add to `helpers.php`; a second file needs a second autoload entry and fragments the contract.

- ❌ **Recomputing `app()->getLocale() === 'fa'` inside a Blade partial that already receives `$isRtl`.**
  - Why: `$isRtl` is the single source of truth for layout direction in Blade. Re-deriving it desyncs from the page-root computation.

- ❌ **Normalizing the `'-'` / `'—'` fallbacks across the date helpers.**
  - Why: `toPersianDate`/`toGregorianDate` return `'-'`; `toYmdDate` returns `'—'` for the no-`created_at` case. The difference is intentional; unifying it is a behavior change.

## 8. Naming conventions

- **File:** `app/Utils/helpers.php` — the single global-helper file.
- **Functions:** `snake_case`, no namespace, each wrapped in `if (!function_exists('name'))` for idempotent autoload.
- **Date helpers:** `toPersianDate` / `toGregorianDate` / `toYmdDate` — the `to…Date` family.
- **Locale helper:** `getLocalizedName` (relation form); the on-model accessor is `getLocalizedNameAttribute` (`Localization` trait).
- **Money helper:** `delimiter($value, $currency, $decimals)` — named for the thousands delimiter it renders.
- **Calendar gate:** `maybeJalali($component)` — "maybe" because it returns the component unchanged when not in Jalali mode.
- **Badge helper:** `tabBadge($label, $count, $color)` — color arg is one of `info` / `success` / `warning` / `danger`, matching the `.tb-*` CSS families verbatim.
- **Session key:** `calendar_type` (values `'jalali'` / `'gregorian'`); **Livewire event:** `calendar-toggled`.
- **Autoload:** `composer.json` → `autoload.files` → `app/Utils/helpers.php`.