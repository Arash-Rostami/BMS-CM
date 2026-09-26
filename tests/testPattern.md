Verified against source on branch `master`. Where this doc conflicts with CLAUDE.md, this doc is authoritative for test-suite conventions.

# BMS-CM Test Pattern

Canonical guide for everything under `tests/`. `database/factories/*.php` already covers every model — use those factories, don't hand-roll fixtures.

## 0. DB wiring — real dev MySQL + transaction rollback, NOT `RefreshDatabase`

**Verified 2026-09-22:** `database/migrations/` has zero active migration files — the entire schema lives archived under `database/migrations/migrated/`, which Laravel's migrator does not scan (`php artisan migrate:status` → "No migrations found"). Attempting to run those archived files directly against a fresh SQLite DB fails partway through — `2025_07_24_151000_create_purchase_request_items_table.php` calls `Blueprint::unsignedDecimal()`, which isn't a real Laravel method, and nothing has ever caught it because nothing has ever executed these files (the real dev schema was built some other way — a dump/import, not `artisan migrate`). There may be more such breaks further down the list; don't assume the rest are clean.

The only place the real schema exists is the actual dev MySQL database (`bms_cm` on `localhost:3307`, credentials in `.env`) — confirmed all needed tables are present there (`users`, `statuses`, `purchase_requests`, `purchase_request_items`, etc., 50 tables total). So DB-touching tests copy Fateh's pattern verbatim: connect to the real dev MySQL DB, wrap every test in a transaction, roll it back in `tearDown`. `phpunit.xml`'s `DB_CONNECTION=sqlite`/`DB_DATABASE=:memory:` stays as-is for the process default — each DB-touching test class overrides the connection to `mysql` at runtime via this skeleton, copy-pasted, not shared through a base class:

```php
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FooTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function useMysql(): void
    {
        $env = base_path('.env');
        if (is_file($env)) {
            $vals = [];
            foreach (explode("\n", (string) file_get_contents($env)) as $line) {
                if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $line, $m)) {
                    $vals[$m[1]] = trim(preg_replace('/\s+#.*$/', '', trim($m[2])), "\"' \t");
                }
            }
            $map = ['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'];
            foreach ($map as $envKey => $cfgKey) {
                if (isset($vals[$envKey])) {
                    config(['database.connections.mysql.' . $cfgKey => $vals[$envKey]]);
                }
            }
        }
        DB::purge('mysql');
        config(['database.default' => 'mysql']);
    }
}
```

**No `RefreshDatabase` / `DatabaseMigrations` / `DatabaseTruncation` trait — ever.** The transaction rollback is the isolation. This DB is your real dev database, not a throwaway — the rollback is load-bearing, not a style preference:
- Never call `DB::commit()` inside a test.
- Never truncate/seed against the real tables directly.
- **Auto-increment IDs are not reset between tests** — capture ids from the created model, never hardcode.
- A test that hard-crashes before `tearDown` runs (fatal error, `exit()`) can leave a committed row behind — if a test run ever leaves stray rows, look for the crashing test first, don't just delete rows by hand.
- **Baseline-delta for count assertions** on tables that already carry real dev-DB rows: assert the delta from before/after your inserts, not an absolute count.

Pure-logic tests that construct an in-memory `new Model([...])` and touch no DB (accessor/mutator tests on unsaved instances, pure static-method tests) skip this skeleton entirely — just `extends Tests\TestCase`.

**`UserFactory` gap, fixed 2026-09-22:** the real `users` table has `phone` and `status` as `NOT NULL` with no default — `UserFactory::definition()` didn't set either (nothing had ever exercised it against the real schema before this test suite existed). Fixed at the factory itself (`phone` a fake number, `status` defaulting to `'active'`) rather than per-test, since every future test creating a `User::factory()` benefits. If a future test needs `status => 'inactive'`, override it explicitly — those are the only two real values in the dev DB.

**`StatusFactory` gap, fixed 2026-09-22:** `english_type` is `NOT NULL` in the real `statuses` table, but the factory generated it via `fake()->optional()`, which can produce `null`. Fixed by deriving `english_type`/`english_name` from the same value as `type`/`name` instead of randomizing independently — a status row where the English and localized type/name genuinely diverge is normal in production (seeded by hand), but a factory-generated test fixture has no reason to diverge, and matching them removes both the `NOT NULL` risk and a source of flaky `Status::findBy()` mismatches in tests.

**`CompanyFactory`/`CurrencyFactory` gaps, fixed 2026-09-22:** found writing `ProformaInvoiceModelTest`'s scoped-relation tests (`sellerCompany`/`buyerCompany` kept resolving null for companies created active). `CompanyFactory` was building `types` from the wrong enum (`App\Filament\Resources\Master\CompanyResource\Enums\Type`, capitalized values) instead of `Company::getAvailableTypes()` — the real lowercase `TypeScopes::TYPE_*` constants the Filament form actually saves — so a factory `Company` never matched any `seller()`/`buyer()`-style scope. Fixed to use `Company::getAvailableTypes()`, and added `seller()`/`buyer()` factory states (`types => [Company::TYPE_SELLER]` / `[Company::TYPE_BUYER]`, `is_active => true`) so relation tests don't hand-roll this. `CurrencyFactory` separately called the non-existent Faker method `fake()->unique()->currency()` (threw `Unknown format "currency"` on first use) — fixed to `currencyCode()`.

**`CurrencyFactory`'s `is_active` default caused a real, repeat-offending flake — fixed at the source 2026-09-25, not per-test.** The factory used to default `'is_active' => fake()->boolean(90)` — any test creating a record via a `belongsTo` Currency (or a sibling factory referencing `Currency::factory()`, e.g. `RegisteredOrderFactory`'s `currency_id`) had a real ~10% chance of getting an inactive currency, and any code path scoped `->where('is_active', 1)` (e.g. `ProformaInvoice::mainCurrency()`) would then resolve null / fail form validation intermittently — first hit and patched inline on ONE PI test (`test_main_currency_infolist_entry_shows_the_localized_name`), then hit AGAIN on an unrelated RO test (`test_edit_page_loads_existing_values_and_persists_a_status_update`) that had nothing to do with the first fix, proving the per-test patch didn't actually close the hole. Fixed properly this time: `is_active` now defaults to `true` unconditionally; the factory already has an `inactive()` state for the (currently zero) tests that genuinely want an inactive currency. **Lesson: a flaky factory default bites every consumer eventually, not just the one test that happened to surface it first — fix the factory, not the symptom, the first time a random-boolean default causes a scoped-relation failure.**

**A model's own `*_number`-style column can be force-regenerated on create.** `PurchaseRequest.pr_number` (and siblings — `po_number`, `shipment_no`, etc.) get overwritten on every `create()` by `App\Observers\CodeGeneratingObserver`, regardless of what a factory or test passes in. Don't assert against a value you tried to set on such a column — capture the real post-create value (`$record->pr_number`) and assert against that instead.

**Assert business identity, not a specific row id, when a lookup can resolve to a pre-existing dev-DB row.** `Status::findBy($type, $name)` does a plain `->first()` with no ordering — in the shared dev DB, a test-created row and a real pre-existing row can share the same `type`/`english_name`, and `first()` may return whichever one MySQL happens to hand back, not necessarily the one your test just created. When a test proves "the record now has status X," assert `$record->status->english_name === 'X'`, not `$record->status_id === $myFixtureStatus->id` — the latter is only safe when you're proving a specific FK never changed (e.g. a "no cascade happened" test), not when proving a value now matches some target state.

## 1. Three layers, all mirrored, one master file each

Full coverage for a module means as many test files as it has distinct source layers — each is a different surface that fails independently:

| Layer | Source | Test file | Covers |
|---|---|---|---|
| Resource | `app/Filament/Resources/<X>Resource.php` (+ its `Traits/Form.php`/`Table.php`/`Infolist.php`/`Filters.php`) | `tests/Feature/Filament/<X>ResourceTest.php` | list/search/filter, create/validation, edit, infolist tabs, EAV, permissions, bulk, soft-delete/restore, global search title/details |
| Model | `app/Models/<X>.php` (+ its `app/Models/Traits/<X>/*.php`) | `tests/Feature/Models/<X>ModelTest.php` | custom accessors/mutators (`getFormattedNameAttribute`-style), custom scopes (`scopeSearchAll`-style), relation query-scoping (e.g. a `status()` relation constrained by `english_type`), casts, constants, model-Observer lifecycle behavior (`booted()`/`Observer::updated()` cascades) |
| Service | `app/Services/<X>.php` | `tests/Feature/Services/<X>Test.php` | public API methods, caching contract (`SmartCacheManager`/`Cache::remember` keys + invalidation), locale-dependent branches — see `app/Services/servicesPattern.md` for the inventory of all 15 |
| Job | `app/Jobs/<X>.php` | `tests/Feature/Jobs/<X>Test.php` | `middleware()` config, `handle()`'s success + failure paths, any row/accounting bookkeeping the job itself owns |
| Topbar surface | `resources/views/filament/partials/work-actions.blade.php` + the toggle partials (PHP-testable parts only — no JS tests exist in this project) | `tests/Feature/Filament/TopbarTest.php` | quick-create permission gating (create **and** view, per §3c), pipeline-group ordering of the menu, the `BMS_RESOURCE_MAP` export, hide-without-permissions, calendar-toggle abbr switching, theme partial's panel/lp surfaces, language-switch text-mode (no flags) regression guard, `topbar.*` lang-key presence in all three locales |

One file per resource / per model / per service, folding everything belonging to that target together — not split into `<X>FormTest`/`<X>TableTest`/`<X>ScopesTest` companions.

**Multi-file subsystems (not a single `<X>.php` service class) get a subdirectory, not a flattened name.** `app/Services/Imports/` (12 files, see `app/Services/Imports/importsPattern.md`) is covered by `tests/Feature/Services/Imports/ImportPipelineTest.php` — mirrors the source subdirectory exactly, one master file for the whole subsystem (its column-definition composition, the two pure-logic pipeline stages, `ImportColumnFactory`'s dispatch shape, `LocalizedMatcher`'s locale merging), not one file per class inside it. `PersistChildRows`/`SyncEavAttributes`/`RunModuleRecalculation` (the DB-touching stages) are deliberately NOT re-tested here — their transaction/rollback/EAV-guard behavior is already covered end-to-end through `PurchaseRequestResourceTest.php`/`ProformaInvoiceResourceTest.php`; this file only adds coverage for what those can't easily isolate (e.g. `ApplyColumnFallbacks`/`RejectMissingManualColumns` against a bare in-memory, unsaved `new Model([...])` — no DB needed, `$record->exists` toggled directly since it's a public Eloquent property). **Traits fold into the class that composes them — there is no separate `tests/Feature/Traits/` tree.** This project's own docs are explicit that traits aren't independent units: `app/Filament/filamentPattern.md` calls the Resource traits "presenters" of the root Resource class, and `app/Models/modelsPattern.md` says "the traits ARE the behavior" of the model that uses them. A trait has no meaning or lifecycle outside the class it's composed into, so it's tested through that class's own test file (a Resource's `Traits/Form.php` through `<X>ResourceTest.php`, a Model's `Traits/<X>/HasFormattedName.php` through `<X>ModelTest.php`) — same rule Fateh's sibling project uses for its own trait-heavy classes.

**Child/item models with no Filament resource of their own** (e.g. `PurchaseRequestItem` — only ever created through the parent's form Repeater, no standalone `*Resource.php`) do not get a separate `ModelTest` file when they carry no custom logic beyond relationships/casts/a status-type constant — fold their coverage into the parent's `ModelTest.php` instead (e.g. the Observer's status-cascade test, which is the one place `PurchaseRequestItem.status_id` behavior actually matters, lives in `PurchaseRequestModelTest.php`). Promote it to its own file the moment it grows real logic (a custom accessor, a scope, a lifecycle hook).

**A resource-scoped feature always folds into that resource's own master file — never a sibling companion, no matter how large.** Confirmed 2026-09-22: bulk import/export was first built as a separate `PurchaseRequestImportTest.php` alongside `PurchaseRequestResourceTest.php` — caught as drift from this exact rule and merged back in. Size/complexity of a feature is not grounds for a companion file; `Resource layer` in the table above already means "the resource's entire surface," import/export included.

**Project-wide integrity/convention tests are a genuinely different category — not a rule violation, don't fold them anywhere.** `tests/Unit/{RelationManagerIntegrityTest,ResourceAuthorizationIntegrityTest,SoftDeleteUniqueConstraintTest,SoftDeleteUniqueValidationTest}.php` and `tests/Feature/{LangKeyIntegrityTest,HttpEndpointAuthorizationTest,ResourceActionAuthorizationTest,EntityAttributeSyncTest}.php` each check one project-wide mechanism or convention across EVERY resource/model at once (e.g. "no resource overrides the shared permission trait," "lang keys stay consistent across all three locales") — none of them is *about* a specific resource, so none of them has a resource to fold into. These stay as their own files permanently; the one-file-per-target rule only applies to files that target one specific Resource/Model/Service.

**Planned cleanup, not yet done (flagged 2026-09-22, no rush — don't do this mid-feature-build):** `tests/Unit/{CodeGeneratorTest,AdaptiveDateTest,PalettesTest}.php` are sitting at the bare root but are NOT cross-cutting integrity checks — each targets exactly one file (`app/Services/CodeGenerator.php`, `app/Utils/helpers.php`'s `adaptiveDate()`, `config/palettes.php`) and should move into the mirrored folder that convention already calls for (`tests/Feature/Services/CodeGeneratorTest.php`, a `tests/Unit/Utils/` or `tests/Feature/Helpers/` home for helper tests, `tests/Unit/Config/PalettesTest.php`). Separately, the genuinely cross-cutting integrity files above should eventually move out of the bare `tests/Unit/`/`tests/Feature/` root into a dedicated grouping (e.g. `tests/Unit/Integrity/` folder, or grouped by which app-area each one audits — the two Filament-resource ones together, the two soft-delete ones together) rather than sitting loose at the root. Do this as one deliberate pass once the current module-by-module audit isn't actively mid-flight, not piecemeal.

## 2. Importance bar

Test the class's own logic, on whichever layer it lives:
- **Resource layer:** `HasResourcePermissions` gating, `getEloquentQuery()` eager-loads/scopes, `SmartCacheManager` badge counts, EAV `extraAttributes`/`customAttributes` round-trips, form validation rules, global search title/details.
- **Model layer:** custom accessors/mutators, custom scopes, a relation deliberately narrowed by a `where()` (e.g. `english_type` scoping), casts with defensive behavior, constants that pin a contract (`TYPE_*`), Observer-driven lifecycle cascades.
- **Service layer:** each public method's branches, caching read/invalidate contract, anything locale-dependent (`app()->getLocale()`).

A form-reactivity closure tightly coupled to Filament's live component tree (`Get`/`Set` callbacks like `TotalXxxCalculation::updateTotalCost`) is Filament-internal plumbing, not model or resource logic — skip a dedicated reflection test for it unless the calculation is lifted into a pure, framework-free method first. Skip other framework plumbing too — a plain `BelongsTo`/`HasMany` wire-up with no scoping, a default Filament column render, or `->columnSpanFull()` placement is not worth a test.

## 3. Cache/static leak guards

Same discipline as Fateh's core guide: if a Service reads through `SmartCacheManager`, `Cache::remember`, or a `once()`-memoized call, add the targeted `Cache::forget('exact-key')` (or `SmartCacheManager::invalidate($model)`) in **both** `setUp` and `tearDown` — cache is not transactional, so a value warmed by one test leaks past the DB rollback and breaks the next test.

## 3a. Per-module cadence — three lanes running concurrently, sync at the boundary

For each module, three things run in parallel, not in sequence: the Lead writes/runs the Resource + Model (+ Service, if relevant) test files and spawns a `claude-reviewer` pass on them; a `claude-ideator` subagent researches and proposes feature ideas for the same module (§3b); the user browser-tests the module against `tests/qa-checklist.html`. **Nobody starts the next module until all three finish and we sync** — tests green + reviewed, ideas discussed (accepted ones queued as real follow-up work, not built inline), browser checklist passed. This keeps modules from arriving half-verified from one lane while another lane is still mid-flight on it.

## 3b. The ideation pass — `claude-ideator`

Once a module's test files exist (or in parallel with writing them), spawn a fresh `claude-ideator` subagent via the `Agent` tool for that same module. It reads the Resource + Model + trait files, does one round of web research on how comparable screens work in real procurement/ERP products, and proposes 2-4 small, high-leverage UX/UI ideas — ranked, each scoped to "small/medium effort within the existing trait structure," never a new subsystem. See `.claude/agents/claude-ideator.md` for its full brief and rejection bar (no generic AI/dashboard suggestions, no new migrations without flagging the cost).

**This agent never implements.** Its output is relayed to the user for discussion. An idea only becomes real work after explicit agreement, at which point it re-enters the normal flow: `app/Filament/filamentPattern.md`-consistent implementation (Standard/Complex lane per CLAUDE.md's delegation policy), a `claude-reviewer` pass, and its own test coverage per this doc — same as any other change, not a shortcut around the pipeline.

## 3c. Resolved (was a false "harness bug") — `Create<X>`/`Edit<X>` page mounts require the `view` permission too, not just the page's own action permission

**Corrected 2026-09-22 — the original diagnosis below was wrong; kept only as a pointer so a future session doesn't re-chase the same dead end.** `Livewire::test(CreatePurchaseRequest::class)->fillForm([...])` threw `Error: Call to a member function getDefaultTestingSchemaName() on null` — this was never a Filament/Livewire testing-macro bug. `Filament\Resources\Pages\Concerns\CanAuthorizeResourceAccess::authorizeResourceAccess()` runs on every resource page mount (`List`/`Create`/`Edit`) and does `abort_unless(static::getResource()::canAccess(), 403)`, which resolves to the `view` permission regardless of which action the page itself performs. A test granting only `purchase_request.create` (or only `purchase_request.edit`) hits a genuine 403 on mount; Livewire's testing wrapper then reports that as the confusing null-instance error on the *next* chained call, not as the 403 itself. Confirmed via `withoutExceptionHandling()` around a bare `Livewire::test(CreatePurchaseRequest::class)` — the real thrown exception is the `HttpException(403)`.

**Fix, and the pattern going forward:** any test method that mounts `Create<X>`/`Edit<X>` must grant **both** the specific action permission (`{prefix}.create` / `{prefix}.edit`) **and** `{prefix}.view` — view alone is required just to reach the page, on top of whatever the page's own action needs.

The same file also carried two unrelated, genuinely pre-existing test-data bugs that surfaced once the permission gate above was fixed and the tests could actually reach form validation — worth naming since both are reusable lessons, not just this one file's problem:
- **A status-typed FK reused across a mismatched `Status.english_type` scope.** `PurchaseRequestItem::status()` is scoped `->where('english_type', 'Purchase Item Status')`, but a test building item rows reused the PR-level fixture (`english_type` = `'Purchase Request Status'`) for the item's `status_id` — the Select's relationship-scoped validation correctly rejected it. Any test creating both a parent and child status-bearing row needs one status fixture per distinct `TYPE_*` constant, never one shared status row across both.
- **`CodeGeneratingObserver`/`CodeGenerator::generate()` makes same-day `*_number` values prefix-related, not independent.** Two `PurchaseRequest::factory()->create()` calls in the same test (same day) get `PR-YYMMDD` and `PR-YYMMDD-1` — the second is literally the first's string plus a suffix, so any substring of the first is always also a substring of the second. A search-isolation test that derives its search term from one fixture's `*_number` and expects a sibling fixture to NOT match can never pass against the real generator. Fix: force distinct values via a raw `Model::whereKey($id)->update([...])` (bypasses the creating-event regeneration) when the test genuinely needs two non-overlapping identifiers, rather than trusting the factory-random seed to survive the observer.
  - **Generalizes to every field in `CodeGenerator::$map`, not just `pr_number`.** Confirmed 2026-09-25 on `RegisteredOrder.contract_no` (also mapped, alongside `ro_number`, per `modelsPattern.md` §9) while writing `RegisteredOrderModelTest`: a factory `->create(['contract_no' => 'CT-778899'])` is silently overwritten on save by `CodeGeneratingObserver::creating()`, which unconditionally regenerates every field `CodeGenerator::fieldsForModel()` returns for that model unless `CodeGeneratingObserver::duringImport()` is active. Same fix — create first, then `Model::whereKey($id)->update([...])` + `->refresh()` — applies to any of the 9 mapped columns (`app/Services/CodeGenerator.php::$map`), not only `pr_number`.

## 3d. Open — `fillForm()` silently drops plain-scalar fields when combined with a relationship-bound field, in THIS Filament/Livewire version's testing harness only

**Affects 6 tests in `PurchaseRequestResourceTest.php`, currently `markTestSkipped()`, 2026-09-22.** `Livewire::test(CreatePurchaseRequest::class)->fillForm(['status_id' => ..., 'urgency_level' => ..., 'required_by_date' => ..., 'items' => [...]])` — after the call, `status_id`/`cost_center_id` (both `->relationship(...)`-bound `Select`s) hold the filled value correctly, but `urgency_level` (plain `Select` with static `->options()`), `required_by_date` (`DatePicker`, no relationship), and `items` (a `Repeater`) all read back `null`/`[]`, with ZERO validation errors and ZERO thrown exceptions — the value is just gone. Reproduced with fields filled in one batched array AND with separate chained `->fillForm()` calls per field — same result either way, so it is not a batching artifact. The pattern holds specifically along the relationship-bound/plain-scalar line, not `->live()` (confirmed: `cost_center_id` has no `->live()` and still works; `status_id` has `->live()` and works; `urgency_level`/`required_by_date` have neither and fail). Traced as far as `Filament\Schemas\Concerns\InteractsWithSchemas::fillFormDataForTesting()` (`vendor/filament/schemas/src/Concerns/InteractsWithSchemas.php`) — its second loop calls `unsetMissingNumericArrayKeys()`, which has a real, independently-confirmed bug (`$currentStatePath .= ".{$key}"` never resets between `foreach` iterations, so the path string grows across unrelated sibling keys) — but a manual replica of that method's exact logic did NOT reproduce the value loss, so that bug is NOT confirmed as the actual cause here; it's a red herring or a second, unrelated bug. **Confirmed NOT a real app bug**: the exact same create/edit flows were independently verified working correctly through the real browser (manual QA, `tests/log/purchase-request.md`) — this is a test-harness-only artifact of this Filament v4 point version. If picking this back up: instrument `vendor/filament/schemas/src/Concerns/InteractsWithSchemas.php`/`HasState.php` directly (temporary `var_dump`s, not guesswork) rather than re-deriving from the outside — that's what burned the most time both times this was attempted.

**Confirmed on a second resource, 2026-09-22** — `ProformaInvoiceResourceTest.php`'s `test_create_happy_path_saves_a_new_proforma_invoice` hits the identical shape (relationship-bound `seller_id`/`buyer_id`/`main_currency_id` Selects combined with a plain `invoice_date` DatePicker) and is `markTestSkipped()` with the same reason, per this section's guidance rather than re-diagnosing. Validation-only Create tests that only assert `assertHasFormErrors()` (never checking a saved field's value) are unaffected and stay unskipped — the bug only corrupts values on the READ-BACK side, a missing-field validation error still fires correctly regardless.

## 3e. Testing a page-level `getFormActions()` override — use `TestAction::schemaComponent()`, not a bare `callAction()` name

`EditRecord::getFormActions()` actions (e.g. Shipment's `saveInvoice`/`printInvoice`/`resetInvoice`, `app/Filament/Resources/Operational/ShipmentResource/Pages/EditShipment.php`) are NOT registered in the Livewire component's `cachedActions` the way `getHeaderActions()` actions are — vendor `EditRecord.php` embeds them as a `Filament\Schemas\Components\Actions` schema component (keyed `'form-actions'`) inside the page's `'content'` schema (`getFormActionsContentComponent()`), and never composes `Filament\Pages\Concerns\InteractsWithFormActions` (the trait that would `cacheAction()` them). A bare `->callAction('saveInvoice')` in a test fails with "action ... is visible" (`assertInstanceOf(Action::class, null)`) because `resolveAction()` only checks `cachedActions`. Fix: target it through the schema explicitly —

```php
Livewire::test(EditShipment::class, ['record' => $shipment->getRouteKey()])
    ->set('data._inv_pi_id', $pi->id)          // ->set() on the raw 'data' array avoids the §3d fillForm() bug for plain-scalar fields
    ->callAction(\Filament\Actions\Testing\TestAction::make('saveInvoice')->schemaComponent('form-actions', 'content'));
```

`schemaComponent($componentKey, $schemaName)` — `$schemaName` is `'content'` for any page-level `getFormActions()` action (confirmed via `Filament\Pages\Page::420` picking `'form'` when present else `'content'` as the default schema name, and `getFormActionsContentComponent()` living in the `content()` schema); `$componentKey` is the `->key(...)` set on the `Actions::make(...)` call (`'form-actions'` — vendor default, unchanged by this project). Verified working end-to-end in `tests/Feature/Filament/ShipmentResourceTest.php::test_save_invoice_action_persists_the_live_form_state_to_eav`.

## 3f. Testing a `RepeatableEntry`'s child schema without a live container — reflection on `childComponents`

`RepeatableEntry::getChildComponents()` requires a bound `Schema`/Livewire container to resolve (throws/returns nothing useful on a bare, unmounted instance built via a static `view{X}Items()`-style helper called directly from a test) — there's no public API to inspect what fields a `RepeatableEntry` declares without actually rendering it inside a real Livewire test. Workaround, reading the private property directly:

```php
private function repeatableItemComponents(RepeatableEntry $entry): array
{
    $reflection = new ReflectionProperty($entry, 'childComponents');
    $reflection->setAccessible(true);

    return $reflection->getValue($entry)['default'];
}
```

Use this to lock in exactly which fields a compact infolist item-row shows (see `filamentPattern.md`'s "Compact one-row-per-item infolist display" convention) — e.g. `ProformaInvoiceResourceTest::test_invoice_items_infolist_entry_keeps_only_the_compact_field_set()` asserts the component name list is exactly `['product.name', 'quantity', 'unit', 'unit_price', 'total_amount']`, so a future edit that silently re-adds a dropped field (or drops a kept one) fails loudly. `childComponents['default']` is the key Filament stores the schema's non-conditional children under — verified against the actual property shape, not guessed; re-check if a Filament upgrade changes this internal structure.

## 3b. Test-run discipline — settled protocol, applies to every resource/module

1. While iterating on a fix: `--filter <MethodName>` only — never the whole file, never the whole suite, mid-fix.
2. At the end of a unit of work: run only the specific test FILE(s) actually edited/extended in that unit, once each — not sibling files in the same module that weren't touched, not the whole module's full audit set.
3. The whole-app suite (`php artisan test`, no filter) runs only on the user's explicit request — never inferred, never self-invented as an "exception" (baseline capture, pre-upgrade check, etc.).
4. No re-running anything "just to be safe" — once green, stop.

Once a piece of work has been through its mandatory `claude-reviewer` pass and passed, it's done — don't re-send it through review again on a later, unrelated turn just because it's nearby. Track what's already been reviewed (session memory / this doc's own updates count as the record) instead of re-reviewing the same code repeatedly.

## 4. Temp tests

Any probe/sanity test written mid-debug gets `*Tmp*` in its file name and is deleted before the turn that created it ends. `*Tmp*` files must never appear in a finished session.

## 5. `tests/qa-checklist.html` — the manual browser QA manifest

A self-contained, framework-free HTML page (published as a Claude Artifact) tracking manual browser verification across every Filament resource, grouped into the **Operational Pipeline** (9 resources, business order: Purchase Request → Proforma Invoice → Registered Order → Purchase Order → Payment → Shipment → Custom → Correspondence, then Bank Profile last since it sits outside the pipeline diagram) and **Master Data** (12 resources) tabs. Each module carries the same 12-item checklist (list/filter/sort, create happy-path + validation, edit + relations, infolist tabs, EAV, role permissions, bulk actions, export, badge + EN/FA/FR locale).

This is a manual-QA companion to the automated suite, not a replacement for it — it exists because this sandbox has no browser access, so the actual click-through has to happen in a real browser.

**Storage: `localStorage` only, plus a one-click JSON export — no shared `db` capability.** An earlier version of this page synced to the artifact's shared `db` capability so Claude could read progress directly; abandoned 2026-09-22 after real-world testing showed `db`/`window.claude.use(...)` resolves `null` when the artifact is opened as a plain top-level browser tab rather than framed inside an active Claude session (per the platform's own capability contract — see `artifact-capabilities` skill), which is how this page is actually used. Checkbox/note state writes to `localStorage` (key `bms_cm_qa_manifest_v1`) on every interaction — that remains the only durable layer. The masthead's **⬇️ Export JSON** button (declares `capabilities: {downloads: true}`) saves the FULL, ALL-MODULES `state` object via the `downloads` capability, with a clipboard-copy/prompt fallback if that capability is unavailable. **⬇️ Copy this module** (added 2026-09-22, in the panel header next to Reset) copies only the currently active module's items, tagged with its `module`/`name` key so there's no ambiguity about which module's data was captured — added after real confusion where a user copying while viewing Proforma Invoice kept getting Purchase Request's data, because the global export was never scoped to the active tab in the first place (by original design, not a regression) — use this button when you want one module's findings, `Export JSON` when you want everything.

**`fallbackCopy()`'s clipboard write must be awaited, never fire-and-forget in a synchronous `try/catch`.** `navigator.clipboard.writeText()` returns a Promise; wrapping the bare call in `try { navigator.clipboard.writeText(payload); alert('copied') } catch {}` does NOT catch an async rejection — the success alert fires unconditionally even when the write silently failed (permission denied, non-secure context), leaving the user copying stale/empty clipboard content while believing it worked. Fixed 2026-09-22: `.then()` shows the success alert only on actual resolution, `.catch()` falls through to `window.prompt()` for a manual copy. Any future clipboard-writing code on this page must follow the same `.then()/.catch()` shape.

**How Claude actually sees progress: the user reports findings in chat, Claude records them in `tests/qa-findings.json`.** Not automatic, not the artifact talking to Claude directly — proven more reliable than chasing capability availability across viewing contexts. When the user pastes checklist JSON or describes what they found per item, capture it in that file keyed by module and item code (see the Purchase Request entry for the shape), including anything already fixed vs. still open, and anything flagged as app-wide or out-of-scope for the current module.

- **Never change** the `localStorage` key (`bms_cm_qa_manifest_v1`) — it silently orphans every existing check-mark.
- To update the page, edit this file and republish to the **same artifact URL** (do not create a new artifact — that starts a fresh `localStorage` origin).
- Adding a resource: add one `{key, name, path}` entry to the relevant group's `modules` array in the inline script. Do not add a 13th generic checklist item without also asking whether it applies to every module — the list is intentionally the same across resources.

## 6. `tests/log/<module>.md` — the user-facing changelog (added 2026-09-22)

One markdown file per module, bulleted, written **from the user's perspective** — plain language, no technical framing ("Custom fields: leaving one blank no longer shows the word 'null'", not "fixed a `json_encode(null)` bug in `HasExtraAttributesManagement`"). This is a curated changelog for the user to hand to stakeholders, not a QA/debug record — `tests/qa-findings.json` is where the raw investigation trail (what broke, root cause, fix location) belongs; this folder is the distilled "what changed for you" output.

**Only genuinely worthy items — not every fix.** Skip anything the user wouldn't notice or care about (test-only corrections, internal refactors with no behavior change, a permission-gap fix in a test fixture). Include real bug fixes the user would have hit, new features, and UX changes — one line each, no "was broken / found the cause / fixed by X" narrative, just the resulting fact.

**Don't log an in-progress feature before it's verified done.** The bulk-import/export work in flight as of this section's creation is deliberately NOT yet in `purchase-request.md` — add it only once a coder agent reports it complete and tests confirm it.

Update the relevant module's file as real user-facing changes land during that module's audit, not only at the very end.
