Verified against source on branch `master` (2026-07-25). Exclusive, authoritative home for BMS-CM's localization conventions — CLAUDE.md and `app/Filament/filamentPattern.md` only point here; do not restore a shadow copy in either.

# BMS-CM Localization Pattern

Three locales: `en`, `fa` (Farsi, RTL), `fr`. No `lang/{locale}/validation.php` exists — this is a deliberate, load-bearing absence (see Validation Messages below), not an oversight.

## 1. Locale / Key Structure

```
lang/{locale}/resources/{camelCaseResource}/strings.php   ← per-resource namespace
lang/{locale}/resources/general/strings.php                ← cross-resource shared keys
lang/{locale}/resources/dashboard/strings.php               ← nav groups, greetings-adjacent app chrome
lang/{locale}/deskReference/{group}.php                     ← Desk Reference content, not resource strings
lang/{locale}/errors/strings.php                             ← HTTP error pages (`codes`), plus in-app save-failure
                                                                 notifications (`notifications`, added 2026-09-17 —
                                                                 see App\Services\ExceptionPresenter)
```

Per-resource top-level groups:

```
general   → model_label, plural_model_label, navigation_group, enum labels
form      → field labels, validation_* messages, helper text; tab labels tab_general / tab_items
table     → column labels
filters   → filter labels
infolist  → entry labels; tab labels tab_general / tab_items / tab_documents
```

Referenced in code as `__('resources/{camelCaseResource}/strings.{section}.{key}')`. Tab labels always carry the `tab_` prefix, in both `form` and `infolist` groups.

**Rule:** when adding a new form or infolist tab, add its `tab_*` key to all three locale files simultaneously — a missing locale renders the raw dot-key string to the user. A master test, `tests/Feature/Integrity/LangKeyIntegrityTest.php`, enforces that every literal `resources/…` key referenced under `app/Filament` exists in all 3 locales — run it after adding keys.

## 1b. Vendor Translation Overrides

Package strings (`filament-actions::import.*`, etc.) can be reworded without publishing the whole vendor lang file: drop a partial file at **`lang/vendor/{namespace}/{locale}/{group}.php`** (e.g. `lang/vendor/filament-actions/en/import.php`) containing only the overridden keys — Laravel's `FileLoader::loadNamespaceOverrides()` merges it over the package lines, everything else falls back to vendor. Two gotchas: the path is `lang/vendor/{locale}` at the TOP of `lang/`, NOT nested under `lang/{locale}/vendor/` (the wrong shape silently no-ops — verified live); and the `filament-actions::import` strings are overridden this way to say "Excel" (upload placeholder, example-download label, format-neutral max-rows title — `ImportDefaultsTest::test_import_modal_wording_says_excel_in_all_locales` pins all three) because the app's `ImportAction` genuinely accepts `.xlsx` via its OpenSpout-to-CSV bridge — only reword vendor strings when the underlying behavior makes the new wording true.

## 2. Shared vs Resource-Scoped Keys

A key used **only by other resources** (a sibling `RelationManager`'s create-action label, a borrowed options vocabulary) belongs in `lang/{locale}/resources/general/strings.php`, never duplicated per-resource. A key a resource uses only internally (`form.metrics`, `filters.metrics`) stays resource-local even if the *values* happen to overlap with a shared vocabulary.

Process: repoint every borrower to the shared `general/strings.*` key first, grep `app/` to confirm zero remaining references to the resource-local copy, then delete it.

Applied examples (verified in `lang/en/resources/general/strings.php`):

| Shared key | Consolidates |
|---|---|
| `general.actions.add_record` | 6 operational RelationManagers' create-button label (`˙⋆✮ Create New`) |
| `general.metrics.*` | unit-of-measure vocabulary (`mt`/`kg`/`lb`/`oz`/`m3`/`ft3`/`l`/`gal`/`pcs`/`unit`), canonical owner was `target`, borrowed by PR/PI/RO/PO |
| `general.extra_attributes.*` (`key`/`value`/`add_action` + `validation_key_required`/`validation_key_max`/`validation_value_required`) | the `HasExtraAttributesManagement` Repeater's field labels and validation messages — its own group, not merged into `general.manage_custom_attributes`, since the two EAV entry points (KeyValue modal vs Repeater) use intentionally distinct wording |

There is no `lang/{locale}/resources/actions/` directory — all action labels (view/edit/delete/create + tooltips, bulk activate/deactivate, and per-action subgroups like `manage_custom_attributes`) live under `general/strings.php`.

**Delimiter-precise matching when repointing:** a bare substring like `…strings.metrics` is a prefix of `…strings.metrics_placeholder` and will wrongly match during a repoint/grep pass — use exact dot-key boundaries.

## 3. Validation Messages

**The no-English-leak rule (critical).** No `lang/{locale}/validation.php` exists, so any rule wired without a message falls back to Laravel's built-in **English** sentence templates in `fa`/`fr`. A localized `->label()` does **not** prevent this — Filament uses the label as the `:attribute` placeholder, but the leak is in the sentence template itself, not the attribute name. Every field (form field, Repeater inner field, RelationManager modal field) carrying a validation rule must wire `->validationMessages([...])`, plus `->validationAttribute(...)` wherever the message uses `:attribute`.

Rules that need wiring (non-exhaustive): `required`, `numeric`, `min`, `max`, `maxDigits`, `string`, `email`, `unique`, `regex`, `date`, `after`/`before`/`after_or_equal`/`before_or_equal`, `distinct`, `in`/`notIn`, `exists`, `gt`/`lt`/`gte`/`lte`, `integer`, `boolean`, `same`, `confirmed`. `nullable` is a marker, not a failing rule — never wire a message for it.

Custom rule objects that call `$fail(__('…'))` internally (`app/Rules/ValidAttachment.php`, `app/Rules/HasContent.php`, the Shipment `english_only` closure) are self-contained and need no `validationMessages` wiring — confirmed via `Shipment/Traits/Form.php`'s `$fail(__('resources/shipment/strings.form.validation.english_only'))`.

**Implicit `date` rule on every `DatePicker`/`DateTimePicker`.** `vendor/filament/forms/src/Components/DateTimePicker.php:89-92`, inside `setUp()`:

```php
$this->rule('date', static fn (DateTimePicker $component): bool => $component->hasDate());
```

This registers on every DatePicker automatically. It is **not** visible as a `->date()` call anywhere in the field definition, so it is the single easiest rule to miss on an audit pass — searching for `->date(` will not find it. Every form DatePicker must wire `'date' => __('resources/{r}/strings.form.validation_date')`. `->step()` does not add a rule. Filter DatePickers are exempt (§5).

**A `Select::make(...)->relationship(...)` validates against `Rule::in($options)`, not `exists` — wiring `'exists' => ...` silently does nothing.** `vendor/filament/forms/src/Components/Concerns/CanBeValidated.php::getInValidationRule()` builds the field's real validation rule as `Rule::in($values)` from the currently-resolvable option values (not a raw DB `exists:table,column` check), and Laravel resolves that rule's message-bag key as `in`. Reproduced live 2026-10-05 fixing Custom's `registered_order_id`/`shipment_id`/the 3 status Selects: wiring `->validationMessages(['exists' => ...])` (copying the shape used elsewhere for a plain `->exists('table','column')` rule, e.g. ProformaInvoice's `main_currency_id`) compiled fine, looked right on read-through, and had **zero effect** — the raw English "The selected :attribute is invalid." kept leaking through with only the attribute name translated, because `in` and `exists` happen to share that exact same default English sentence, making the leak easy to miss even when staring at the rendered message. The only way this was caught was dumping the real `MessageBag` from a mounted Livewire test (`$component->errors('data.field')`), not by reading the code or trusting that the message looked plausible. **Always verify which rule name actually appears in the error bag for a `->relationship()`-bound field before trusting a `validationMessages()` key** — don't assume `exists` just because the field involves a DB relation; a plain `->rules(['exists:table,column'])` call (no `->relationship()`) is the one case that genuinely does use the `exists` key.

**Implicit rules on every FileUpload** (same invisible-rule class as the DatePicker `date` rule): `->maxSize(kb)` registers `max:{kb}` and `->acceptedFileTypes([...])` registers `mimetypes:…` (vendor `BaseFileUpload.php`). Verified wiring, `General/FormComponents::getAttachmentsField()` (shared by Shipment/Custom/Correspondence): both rules reuse the pre-existing `general/strings.attachments.validation.attachments_size` / `attachments_type` keys rather than new ones. `Repeater::maxItems()` similarly registers `array` + `max:{n}`.

Verified reference wiring, `CustomResource/Traits/Form.php:36-40`:

```php
DatePicker::make('clearance_date')
    ->label(__('resources/custom/strings.form.clearance_date'))
    ->maybeJalali()
    ->validationMessages([
        'date' => __('resources/custom/strings.form.validation_date'),
    ]);
```

**Key naming — two shapes coexist.** Flat, everywhere except Shipment:
- Field-specific: `validation_{field}_{rule}` (e.g. `validation_contract_no_max`, `validation_declaration_no_max`).
- Generic/shared-within-resource: `validation_{rule}` (`validation_required`, `validation_numeric`, `validation_date`).

Shipment is the one resource whose validation vocabulary is **entirely** nested under `form`:

```php
// lang/en/resources/shipment/strings.php
'validation' => [
    'required' => 'This field is required.',
    'unique' => 'This value must be unique.',
    'english_only' => 'Only English letters, numbers, parentheses, and dashes are allowed.',
    // ...
],
```

referenced as `__('resources/shipment/strings.form.validation.{rule}')`. ProformaInvoice is a half-exception: flat everywhere except one nested `form.validation` group (`string`, `max_string`) used by its item-description field. Do not flatten either exception to match the rest, and do not nest a new resource's shape to match them — match whichever shape the target resource already uses.

Laravel placeholders `:attribute` / `:max` / `:date` / `:min` are used in templated messages on RegisteredOrder, Correspondence, and BankProfile's `after_or_equal` rule — preserve them verbatim when mirroring a message into a new field.

**Canonical generic wording** — for a genuinely new message, mirror the resource's own existing style if it has one; do not mass-normalize wording across resources that already differ on purpose (PI is verbose, User is friendly, RO/Correspondence are `:attribute`-templated — these are intentional per-resource voices). Reference wording only for a resource with no existing precedent:

| Key | en | fa | fr |
|---|---|---|---|
| `validation_required` | This field is required. | این فیلد الزامی است. | Ce champ est obligatoire. |
| `validation_numeric` | This field must be a number. | این فیلد باید عدد باشد. | Ce champ doit être un nombre. |
| `validation_date` | Please enter a valid date. | لطفاً یک تاریخ معتبر وارد کنید. | Veuillez entrer une date valide. |

`validation_description_max`-style character-limit messages should use the canonical verb "must not exceed" / "نباید بیش از" / "ne doit pas dépasser" unless the resource has its own established structural convention (e.g. Product's siblings all use a `:max` placeholder — keep that pattern rather than flattening to a literal number and breaking the internal convention).

## 4. Wording Conventions

**Farsi word for "character": use کاراکتر, never نویسه.** For length-limit/password-hint copy, کاراکتر (plural کاراکترهای) is the contemporary, universally understood term. نویسه is the Persian Academy's formal/purist term — native readers find it meaningless and stiff. Applies project-wide (verified clean across all 16 fa lang files that reference "character").

**fa/fr values must be genuine translations.** Never paste the English string into the fa or fr locale file. Every new key is added to all three locale files simultaneously.

**New-module import/export labels take the MAJORITY convention across all sibling modules — never lift from the nearest twin.** Settled wording: fa import = `وارد کردن X`, fa export = `خروجی گرفتن از X` (3 of 4 audited modules; only BankProfile deviates with صدور — do not copy it); fr = `Importer les X` / `Exporter les X`. The same applies to helper sentences: fr `helper_is_active` is the fixed sentence `Indique le statut actif/inactif de cet enregistrement.` (Bank/Company), not a fresh translation of the English helper. Learned building Department 2026-09-26, after a twin-lifted `صدور دپارتمان‌ها` export label was rejected. Also from that build: `general.actions.restore` now exists in all 3 locales — `PermissionLabeler` resolves every `{module}.{action}` row through it, so a new action suffix needs a matching `general/strings.php` `actions.*` key or the Permission/Role UI shows the raw key.

**Status labels are never hardcoded.** Resolve through `Status::findBy(Model::TYPE_X, 'EnglishName')`, never a literal English status string in resource code. This extends to color/icon maps keyed by status name — build the map from resolved `Status` IDs, not from the status's English name string directly. Verified pattern, `CorrespondenceResource/Traits/Table.php`:

```php
->color(function (Model $record): string {
    static $colors = null;

    if ($colors === null) {
        $colors = collect([
            'success' => ['Approved', 'Sent', 'Published'],
            'gray' => ['Draft', 'Pending'],
            'danger' => ['Rejected', 'Archived'],
        ])->map(fn ($names) => collect($names)
            ->map(fn ($name) => Status::findBy(Correspondence::TYPE_CORRESPONDENCE_STATUS, $name)?->id)
            ->filter()
            ->all()
        )->all();
    }

    $id = $record->status?->id;
    // match $id against $colors, default 'info'
})
```

A prior version of this exact column hardcoded the English status names directly into the `match()` — a real, since-fixed bug. Do not reintroduce a hardcoded-name comparison anywhere a `Status` relation is available.

## 5. Filter Localization

Every filter — `SelectFilter`, custom `Filter`, toggle filters (`getUnreadFilter`/`getMyMentionsFilter`), and each inner `DatePicker` of a date-range `Filter` — carries a localized `->label(__('resources/{r}/strings.filters.{key}'))`, key added to the `filters` group of all three locale files. A date-range filter has **two** user-facing labels (e.g. `filters.created_from` / `filters.created_until`) — both must be localized.

**Filter-form schemas are never validated.** Filament only fills/reads filter state (vendor `HasFilters` has no `validate()` call), so any rule set on a filter field — including the implicit `date` on filter DatePickers and the `numeric` on Target's quantity-filter TextInputs — is inert and must NOT get `validationMessages` wiring (dead code). Only filter labels are localized.

**Filter DatePickers use `->adaptive()`, not `maybeJalali()`.** `->adaptive()` is `DatePicker::macro('adaptive', ...)` registered in `FilamentMacroServiceProvider::boot()` — returns `$this->jalali()` when the session calendar is Jalali. It is the in-chain sibling of the `maybeJalali($component)` helper (`app/Utils/helpers.php`); both call the shared `isJalaliCalendar()` predicate, documented in full in `app/Utils/helpersPattern.md` §2.

**Verified correction to an older, overly-broad claim:** `maybeJalali()` is not actually the dominant convention on regular form `DatePicker`s — a repo-wide grep shows it is used in exactly one place, `ShipmentResource/Traits/InvoiceForm.php` (the EAV virtual-tab pattern), plus the Custom resource's form fields. Every other operational resource's `Form.php` `DatePicker`s (PurchaseRequest, PurchaseOrder, RegisteredOrder, ProformaInvoice, Payment, BankProfile, Shipment's own non-invoice fields, Target) chain `->adaptive()` instead, same as `Filters.php`. Both helpers read the identical `session('calendar_type', ...)` default, so either is functionally correct — but when touching an existing field, match what that field (or its nearest sibling) already uses rather than assuming `maybeJalali()` is the form-side default and `->adaptive()` the filter-side default.

`SelectFilter` option localization — six patterns, matched to the option source, do not invent a seventh:

1. `->relationship('rel', 'name')` — options resolve from the related model's `name` column; no per-option lang key needed.
2. **Bilingual models** (`Status`, `Company`, `Bank`, `Currency` — both `name` and `english_name` columns) use a locale-conditional title column: `->relationship('status', app()->getLocale() === 'fa' ? 'name' : 'english_name')`. `->relationship()` takes a real column string, not the `getLocalizedNameAttribute` accessor — the ternary is the correct way to make options locale-aware.
3. `->options(__('resources/{r}/strings.general.{vocab}'))` — the whole options map is one localized lang-array key (`delivery_terms`, `transport_modes`, `metrics`); a borrowed cross-resource vocabulary points at `general/strings.*` per §2.
4. `->options(SomeEnum::class)` — backed enum; labels come from the enum's `label()` doing `__()` internally (`Priority`, `Type`, `TargetStatus`, `UserStatus`). Enums whose `label()` falls back to a non-localized raw value (Company `Type`, Permission module title) are known-skip i18n bugs — not a pattern to replicate in a new enum.
5. `->options(['key' => __('…'), …])` — explicit inline array; every value must be a localized `__()` call. A bare string literal here is an English leak.
6. `->options(fn () => …pluck('name','id')…)` — dynamic DB-sourced options; the plucked column is the model's own localized name column, so no extra lang key.

`TrashedFilter::make()` is a Filament built-in rendering from Filament's own `soft_deletes` lang namespace — it takes **no** custom `filters.*` key. Every resource's `Filters` trait ends with `public static function getTrashedFilter(): TrashedFilter { return TrashedFilter::make(); }`.

## 6. RTL / Emoji Placement

`$isRtl` (Blade prop, landing page) and `app()->getLocale() === 'fa'` (resource code) are the two places RTL branches on. Emoji-prefixed labels flip position by locale rather than being dropped for fa — prefix in en/fr, suffix in fa. Verified, `notificationSetting/strings.php` `action_types` group:

```php
// en
'create' => '🟢 Create',
// fr
'create' => '🟢 Créer',
// fa
'create' => 'ایجاد 🟢',
```

Emoji and short-code labels (e.g. `Source::getLabel()`'s `'PR'`/`'PO'`/`'PI'`/`'-'`) are a deliberate project style choice, not something to strip during a localization pass — keep them, only adjust position/translation per locale.

## 7. Calendar Helper Contract

`maybeJalali($component)` / `->adaptive()` (date pickers) and `->adaptiveDate()` / `->adaptiveDateTime()` (display columns/entries) all gate on the shared `isJalaliCalendar()` predicate (`app/Utils/helpers.php`), which reads `session('calendar_type', app()->isLocale('fa') ? 'jalali' : 'gregorian')`. Full lifecycle is owned by `app/Utils/helpersPattern.md` §2. Localization-relevant halves: omitting either picker wrapper gives Gregorian to an `fa` user who has switched their session to Jalali, and a raw `->date()`/`->dateTime()` display column ignores the calendar toggle entirely — a locale-locked ternary (`app()->getLocale() === 'fa' ? toPersianDate(...) : toGregorianDate(...)`) is the known historical bug shape; display dates must go through the adaptive macros (enforced by `tests/Feature/Helpers/AdaptiveDateTest.php`).

## 8. Playbook — Adding a Localized Field/Filter/Tab

| Adding… | Do this |
|---|---|
| A form field with any validation rule | Wire `->validationMessages([...])` for every rule including the implicit `date` on DatePickers; add `->validationAttribute(...)` if the message uses `:attribute`; add keys to all 3 locales. |
| A new tab (form or infolist) | `tab_*` key in all 3 locale files, same session. |
| A filter | `->label(__('…strings.filters.{key}'))` in all 3 locales; DatePicker filters get `->adaptive()`, no validation wiring. |
| A cross-resource-only key | Add to `general/strings.php`, not the resource namespace; repoint any pre-existing local duplicates and delete them. |
| A new fa string | Genuine translation, کاراکتر not نویسه, matching the resource's existing tone. |
| A status-driven color/icon map | Resolve via `Status::findBy()`, never match on the English status name literal. |
