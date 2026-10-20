# Cross-cutting Gotchas

Durable, non-obvious lessons that don't belong to a single domain doc. CLAUDE.md points here instead of carrying them. Add new cross-cutting gotchas to this file; domain-specific ones belong in their own `*Pattern.md`.

## `.env`'s `APP_URL` must match the port `php artisan serve` actually binds to

`composer run dev` runs plain `php artisan serve` with no `--port` flag, so it always uses Laravel's default (8000). Every signed/absolute URL the app generates (queued-job notification download links, etc.) is built from `APP_URL`, so a mismatch silently produces links to a port nothing is listening on — the browser shows `ERR_CONNECTION_REFUSED`, which looks like a broken feature but is `.env` config drift. After changing it: `php artisan config:clear` + `queue:restart` (a queue worker caches config at boot — see `app/Jobs/jobsPattern.md`).

## "Overdue" comparisons on a `date`-cast column must bind the same midnight boundary everywhere

`$dateColumn < now()` on a plain `date`-cast column (e.g. `Shipment.eta`) flags a record whose date is literally today as already overdue — midnight-today is less than the current time-of-day, off by one day from SQL's own `CURDATE()` semantics (`AnalyticsService::shipmentPunctuality()`'s `eta < curdate()`). Always compare with `today()`/`->startOfDay()`, never bare `now()`. All sites comparing the same column for the same "overdue" concept must bind the identical boundary value, or they silently disagree on today's own records.

## Never wire a one-shot `Prepares{X}From{Y}` prefill helper into a live reactive closure if it also generates a reserved identifier

`PreparePaymentFromTargetable::prepareData()` (Payment's auto-populate-from-target helper) calls `CodeGenerator::generate('payment_no')`, which runs a real `SELECT ... FOR UPDATE` — fine as a one-time call from `afterFill()` on page mount, but wired into a `MorphToSelect`'s `afterStateUpdated` (so re-picking the target live-refills related fields) it re-ran the locking query on every manual re-selection and threw the result away, unused. Fixed by splitting a non-generating `copyTargetableAttributes()` out of `prepareData()` — the live closure calls only the split-out copy method, the one-shot mount-time prefill still calls the full `prepareData()`. Before wiring any existing prefill helper into a live/reactive closure, check whether it does anything beyond copying related-record attributes (a `CodeGenerator` call, a cache write, any DB write) — split it first if so.

## Relations are discovered by reflection - a new relation can leak into the calendar

`CalendarPathResolver::reflectRelations()` (and through it `ModelInspector`, the Calendar router and the Table picker) exposes EVERY typed public relation method declared under `app/Models`. A convenience relation added for code use (`hasManyThrough`, a `belongsToMany` shortcut over a pivot) would show up as a bogus 1-hop "Directly linked" table and could be saved into rules. Mark such relations `#[Indirect]` (through relations are also dropped by class) and keep the invariant test (`CalendarSubsystemTest::test_indirect_relations_never_reach_the_calendar_relations_or_table_options`) green. Adding an inverse can also silently change the picker: the resolver keeps the FIRST relation (method order) that reaches a table, so declare a new relation to an already-reachable table after its siblings in the trait. Details -> `app/Models/modelsPattern.md` §4b.

## A Livewire `#[Computed]` read inside an action freezes the old state for the render

Computed properties are cached for the whole request. An action that reads one (`$this->range`, `$this->miniMonth`) BEFORE assigning the property it derives from makes the same request's render draw the stale value (the calendar showed the previous month after «Today», and the Go-to popover preselected the old month). In actions call the underlying service with the explicit argument, or `unset($this->name)` after the assignment.

## A scope that adds a sub-select column must also select `table.*`

`->addSelect(['read_at' => $subquery])` on a query with no explicit columns REPLACES the implicit `*` — the model hydrates with only that one column. `CalendarHit::scopeWithReadAt` therefore starts with `select('calendar_hits.*')`, and it must never be applied to an aggregate/grouped query (`CalendarBoard::monthGroups`).

## A real HTTP/Livewire update request inside a rolled-back test cannot see the test's rows

The §0 `useMysql()` skeleton keeps data inside one open transaction; a request sent through `$this->post('/livewire/update')` runs on its own connection state (transaction level 0) and reads the real dev data instead. State carried in the component snapshot hides this (the old Needs-attention test passed that way); anything that re-queries on the update request (server-side pagination) must be tested through `Livewire::test()` instead, and the real-request test can only assert on the GET-rendered snapshot.

## `activity_log` holds two logs - always filter on `log_name`

The calendar (`log_name = 'calendar'`, written by `CalendarActivity`) and the audit trail (`'audit'`, written by `LogsAudit` on 8 models, selected columns only via `auditColumns()`) share one table AND the same subjects. An unfiltered read (`where('subject_id', ...)` alone, a bare `count()`) mixes them; every calendar query filters `CalendarActivity::LOG_NAME`, every audit query `AuditChanges::LOG_NAME` / `->inLog()`. Details -> `app/Models/modelsPattern.md` §6c, `app/Services/Calendar/calendarPattern.md` §8. If `activitylog:clean` is ever scheduled it must pass `--log=calendar`, or it prunes the audit trail; and a future `Relation::morphMap()` would break every class-string comparison on `subject_type`.

## Spatie date attributes are logged as UTC ISO strings

`LogsActivity::logChanges()` serializes every date-cast attribute with `serializeDate()` (`...T..:..:..Z`, UTC) even with raw values, so a `date` column at Tehran midnight is stored as the PREVIOUS day at 20:30Z. Never display those strings raw: `AuditChanges::date()` converts back to `app.timezone`. Do not "fix" it by overriding `serializeDate` on the model (it also drives JSON/Livewire serialization app-wide).

## An EAV `json`-cast value can come back as a STRING (double-encoded)

A legacy `entity_attributes` row whose `value` was written as an already-encoded JSON string is encoded a second time by the `json` cast, so `$attr->value` reads back as a string, not an array (production: one `commercial_invoice` row). Any reader of a hand-written EAV key must `json_decode` a string result once more before trusting it (`InvoiceForm::loadInvoiceData()`, `BackfillShipmentInvoiceItems::decode()`); re-saving through the cast with an array normalises the row.

## Every Filament/Livewire page "breaks" (`filamentSchema`/`isOpen is not defined`, widgets and modals dead) when `php.ini` has `zlib.output_compression = On`

Symptom: the page renders HTML but nothing is interactive, and the console is a wall of `X is not defined` Alpine errors (`filamentActionModals`, `filamentSchemaComponent`, `isOpen`, `getSelectedRecordsCount`…) in every browser. Packages, `vendor/` and `public/` are fine — do NOT reinstall anything. Cause: PHP's built-in server (`php artisan serve`) with output compression on prepends ~20 stray gzip bytes before the `HTTP/1.1 200 OK` line of every response to a client sending `Accept-Encoding: gzip` (all browsers), so each Filament script is corrupted and its `alpine:init` listener never registers. `curl` without that header looks healthy, which hides it. Diagnose with a raw socket request carrying `Accept-Encoding: gzip` — a response starting `1F-8B-08` instead of `HTTP/` is this bug. Fix: `zlib.output_compression = Off` in the `php.ini` that `php.exe` loads (`php --ini`; here `C:\MAMP\conf\php8.2.14\php.ini`), then restart `composer run dev` (PHP reads the ini only at start). The same ini also needs `zend_extension=php_opcache.dll` uncommented (opcache is otherwise silently off), `opcache.max_accelerated_files=20000` (Laravel+Filament exceed 4000), `opcache.revalidate_freq=0` (edits show instantly in dev) and `memory_limit=500M` (tests/Livewire renders exceed 128M). If MAMP/MAMP PRO regenerates `php.ini` from its template, edit the template too or the fix reverts. Recovery if the ini is ever lost/reset again: copy `D:\Git-Flow\DEV-ENV\_php-ini-known-good\php.ini.known-good-2026-10-19` (outside MAMP and outside the repo, so neither can overwrite it) to `C:\MAMP\conf\php8.2.14\php.ini`, restart `composer run dev`, and verify with `php -r "echo ini_get('zlib.output_compression'), extension_loaded('Zend OPcache') ? 'opcache' : 'NO-opcache';"` (expect empty + `opcache`). Refresh that copy whenever the ini is deliberately changed.

## Never run `DatabaseSeeder` in production — seed permissions with `PermissionSeeder` alone

`DatabaseSeeder` also creates the roles and a `test@example.com` user with the password `password` (assigned `admin_senior`), so running it against production would recreate a known-credential admin if that user is missing. The permission list lives in `database/seeders/PermissionSeeder.php` (idempotent `firstOrCreate`, called by `DatabaseSeeder`): to add permission rows in production run `php artisan db:seed --class=PermissionSeeder --force`, then assign them to roles in the Role form. The Purchase Request workflow statuses (stage order 1–4 and the two `status.grant_*` approval permissions on «Sales Manager Approval» and «Authorized») are seeded the same way with `php artisan db:seed --class=PurchaseRequestStatusSeeder --force` (idempotent, never renames an existing status). Add every new permission prefix there with its five standard actions (`ResourceAuthorizationIntegrityTest` demands the full set per prefix) and its action labels in `lang/*/resources/general/strings.php`.

## One fatal in any class under `app/Filament/Pages|Widgets|Resources` takes the whole panel down

Filament auto-discovers (autoloads) every class in those folders when the panel boots — including abstract bases like `Pages\Reports\ReportPage` — so a PHP fatal at class load (a trait-method collision, a missing parent) breaks the dashboard for every page, not just the new one. Hit while building `ReportPage`: `Filament\Pages\Dashboard\Concerns\HasFiltersForm` and `Filament\Tables\Concerns\InteractsWithTable` both define `normalizeTableFilterValuesFromQueryString`, so combining them needs an explicit `insteadof` (and an alias for any other shared method). After writing or editing such a class, run `php artisan about` and `php artisan route:list --path=dashboard` before anything else — a fatal shows there immediately — and keep a test that resolves the page class and the panel.

## MySQL 8 reserved words as aliases break production while the 5.7 dev DB passes

Production is MySQL 8.0.29, dev is 5.7. Words reserved only in 8 (`over`, `rank`, `groups`, `system`, `lead`, `row`, `rows`, `window`, `empty`, `of`, `function`, …) used as a column alias (`as over`) are a 1064 syntax error in production and nothing on dev — the R5 customs card shipped one (`over`, now `over_threshold`). Alias with a descriptive suffix instead. `ReportsEngineTest::test_no_report_sql_uses_a_mysql_eight_reserved_word_as_an_alias` scans the report source folders for `as <reserved>`; extend its word list when a new one is found. Also avoid 8-only features (CTEs, window functions, `VALUES ROW`) unless guarded by a version check with a 5.7 fallback.

## Stale caches after a refactor show up as «Undefined constant» / missing-class errors

After renaming or moving a constant or class (seen: `FileActivity::IDLE_SQL`), the page can keep failing from cached Filament components, compiled views or opcache even though the code is right. Run `php artisan optimize:clear && php artisan filament:clear-cached-components` and restart `php artisan serve` before debugging further. An agent's half-finished edits can also break a page for minutes — re-check after the agent finishes.

## Parallel phpunit runs on the shared dev DB deadlock

Two `phpunit`/`artisan test` processes at once against the one dev MySQL cause lock-wait timeouts and deadlocks (record-number locks, the shared fixtures) and `fopen` permission errors in `storage/framework/testing`. Check for a running test process first and run ONE at a time; heavy report runs use `php -d memory_limit=-1`. Details → `tests/testPattern.md` (Reports subsystem row).

## `array_map` over two arrays of different length passes `null` to the callback

In chart widgets, `array_map(fn ($a, $b) => …, $labels, $values)` with arrays of different length pads the shorter one with `null`, which then fails on arithmetic or a typed parameter (or silently draws a zero). Build both arrays from the same key list (map over the keys and look each value up) so the lengths cannot differ.

## Never set the MySQL connection timezone to fix «today» boundaries

`CURDATE()` follows the MySQL server timezone while PHP `today()` follows the app timezone, so between midnight and the offset they disagree on a UTC MySQL. Do NOT set the connection `timezone` in `config/database.php`: it shifts every TIMESTAMP read and write. Report SQL embeds PHP's today as a literal through `App\Services\Reports\Support\SqlToday::literal()` and never uses `CURDATE()`/`NOW()`.

## Scripted string edits (sed, python, perl) mangle CRLF files and backslashes

The working tree has Windows line endings in places; shell/python search-and-replace silently normalises them or eats PHP namespace backslashes, producing whole-file diffs or broken class names. Make code and doc changes with the Edit tool (visible diffs); only a repeated identical change across many places justifies a batch replace, and then verify with `git diff --stat`.

## Covered by their domain docs (don't duplicate here)

- Tooltip reliability on client-only toggles → `resources/js/scriptPattern.md` §10
- Sidebar-collapse chevron next to the topbar logo is Filament's shipped default → `resources/css/stylesPattern.md`
- Dead-code sweeps on assets need a real build, not just grep → CLAUDE.md's "Critical non-obvious rules" item 4 (`import.meta.glob` catch-all)
- Implicit `date` validation rule on every `DatePicker`/`DateTimePicker` → `lang/localizationPattern.md`
- Long-running queue workers cache class code AND config at boot → `app/Jobs/jobsPattern.md`
- A RelationManager never gets bulk import → `app/Filament/filamentPattern.md` §1.20, `app/Services/Imports/importsPattern.md`
- OPcache reset from the CLI never touches the running server's cache (FPM or `php artisan serve`) → `app/Utils/helpersPattern.md`
- Modal-less actions run inside `mountAction()`; `$set` on an infolist Entry never persists → `tests/testPattern.md` §3j
- HTTP-request tests: the `DatabaseConnectionMiddleware` disconnects the DB after every request, so only the first request sees the test's transaction and later writes commit to the dev DB → `tests/testPattern.md` §3a-2
- Typing a relation method's return type needs the matching `use` import in that trait file: `php -l` and Pint both pass, the TypeError only fires when the relation is called (it broke `RegisteredOrder`/`Shipment` pipeline links and hid them from the calendar until a broader test run) → `app/Models/modelsPattern.md` §13
- Correlated `EXISTS` that is only linked to the outer row through a NESTED `EXISTS` makes MySQL scan the whole inner table once per outer row (quadratic: R7's stage CASE took >240 s at 3,000 orders while passing every small test). Drive the `EXISTS` from the link/pivot table instead (`… from registered_order_purchase_request l join shipments on shipments.registered_order_id = l.registered_order_id where l.purchase_request_id = <outer column>`), and prove it with a volume run, not a unit test → `app/Services/Reports/reportsPattern.md` (R7)
- Never read a Filament field's closure-valued setting (`getPlaceholder()`, `getLabel()` …) while building the form schema in a static helper: the field has no container yet and the page 500s with «Typed property … $container must not be accessed before initialization». Classify fields by name/type (`getName()`, `instanceof`, `isMultiple()`), as `HasReportFilters::arrangeFilters()` does
- When an agent (or you) introduces a helper, define it BEFORE adding its call sites: a half-finished edit left `static::hinted(...)` calls without the method and took every report page down with `BadMethodCallException` until it was defined
- Never clean up with `taskkill /IM php.exe`: it kills `php artisan serve` and every other PHP process on the machine; kill only the PIDs you started
