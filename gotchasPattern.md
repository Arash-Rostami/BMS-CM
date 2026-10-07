# Cross-cutting Gotchas

Durable, non-obvious lessons that don't belong to a single domain doc. CLAUDE.md points here instead of carrying them. Add new cross-cutting gotchas to this file; domain-specific ones belong in their own `*Pattern.md`.

## `.env`'s `APP_URL` must match the port `php artisan serve` actually binds to

`composer run dev` runs plain `php artisan serve` with no `--port` flag, so it always uses Laravel's default (8000). Every signed/absolute URL the app generates (queued-job notification download links, etc.) is built from `APP_URL`, so a mismatch silently produces links to a port nothing is listening on — the browser shows `ERR_CONNECTION_REFUSED`, which looks like a broken feature but is `.env` config drift. After changing it: `php artisan config:clear` + `queue:restart` (a queue worker caches config at boot — see `app/Jobs/jobsPattern.md`).

## "Overdue" comparisons on a `date`-cast column must bind the same midnight boundary everywhere

`$dateColumn < now()` on a plain `date`-cast column (e.g. `Shipment.eta`) flags a record whose date is literally today as already overdue — midnight-today is less than the current time-of-day, off by one day from SQL's own `CURDATE()` semantics (`AnalyticsService::shipmentPunctuality()`'s `eta < curdate()`). Always compare with `today()`/`->startOfDay()`, never bare `now()`. All sites comparing the same column for the same "overdue" concept must bind the identical boundary value, or they silently disagree on today's own records.

## Never wire a one-shot `Prepares{X}From{Y}` prefill helper into a live reactive closure if it also generates a reserved identifier

`PreparePaymentFromTargetable::prepareData()` (Payment's auto-populate-from-target helper) calls `CodeGenerator::generate('payment_no')`, which runs a real `SELECT ... FOR UPDATE` — fine as a one-time call from `afterFill()` on page mount, but wired into a `MorphToSelect`'s `afterStateUpdated` (so re-picking the target live-refills related fields) it re-ran the locking query on every manual re-selection and threw the result away, unused. Fixed by splitting a non-generating `copyTargetableAttributes()` out of `prepareData()` — the live closure calls only the split-out copy method, the one-shot mount-time prefill still calls the full `prepareData()`. Before wiring any existing prefill helper into a live/reactive closure, check whether it does anything beyond copying related-record attributes (a `CodeGenerator` call, a cache write, any DB write) — split it first if so.

## Covered by their domain docs (don't duplicate here)

- Tooltip reliability on client-only toggles → `resources/js/scriptPattern.md` §10
- Sidebar-collapse chevron next to the topbar logo is Filament's shipped default → `resources/css/stylesPattern.md`
- Dead-code sweeps on assets need a real build, not just grep → CLAUDE.md's "Critical non-obvious rules" item 4 (`import.meta.glob` catch-all)
- Implicit `date` validation rule on every `DatePicker`/`DateTimePicker` → `lang/localizationPattern.md`
- Long-running queue workers cache class code AND config at boot → `app/Jobs/jobsPattern.md`
- A RelationManager never gets bulk import → `app/Filament/filamentPattern.md` §1.20, `app/Services/Imports/importsPattern.md`
- OPcache reset from the CLI never touches the running server's cache (FPM or `php artisan serve`) → `app/Utils/helpersPattern.md`