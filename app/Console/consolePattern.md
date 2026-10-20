Verified against source on branch `master` (2026-10-19). Home of the project's Artisan commands; tests mirror them in `tests/Feature/Console/` (`tests/testPattern.md` §1).

# Console commands

Commands in `app/Console/Commands/` are auto-discovered by Laravel 12 (no registration). They are one-shot data tools, not schedulers: nothing here is scheduled.

## Conventions

- Signature `{domain}:{verb}-{object}`, options kebab-case. Every command that writes has `--dry-run` (report only, write nothing); every command that removes or rewrites source data has a separate opt-in flag (`--prune`) that only acts on rows the same run has verified.
- Idempotent by design: a re-run must not duplicate or overwrite rows already migrated.
- Row-level writes in a `DB::transaction`; bulk side effects that must not create audit/stamp noise go through `->toBase()->update()`.
- Ends with a summary table (counts per outcome); no row dumps.
- Zero comments, small methods, Eloquent/Query Builder only.

## `shipments:backfill-invoice-items`

Copies the `items` array of every `commercial_invoice` EAV row (trashed shipments included) into `shipment_items`, and sets the shipment's `total_net_weight`/`total_gross_weight`/`grand_total`. Shipments that already have items are skipped (never overwrites rows saved through the new form). Free-text `origin` and `unit` values are stored as the option keys the form Selects use (`CN`, `kg`) via `ShipmentResource::normalizeInvoiceKeys()`; an unknown value is kept as is. Product linking runs per line, first unique hit wins, otherwise `product_id` stays null and the line is reported as unmatched (never guessed):

1. normalized name (lower-case, punctuation/space collapsed, legacy JSON `english_description`/`description`, stored as `english_name`/`name`, vs product `english_name`/`name`) against the products of the invoice's own proforma invoice;
2. the same against the products of the shipment's registered order;
3. the same against the whole catalogue (must be unique);
4. a proforma invoice with exactly one distinct product links every remaining line to it.

`--prune` strips `items` from the stored JSON only for shipments whose `shipment_items` row count equals the JSON line count — copied in that run, or in an earlier run (so the usual "run, check, then run with `--prune`" sequence prunes). A row that throws (e.g. a non-numeric quantity) is rolled back, reported as `failed`, and the run continues (exit code 1 at the end); the "no items yet" check is repeated under a row lock inside the transaction so a concurrent invoice save is never appended to. Deploy order: migrate, backfill (dry-run first), later prune. Until it runs, `InvoiceForm::loadInvoiceData()` falls back to the JSON items, so nothing breaks.

**Production-only in practice.** Only old production invoices carry the legacy JSON `items`; invoices saved on dev go straight to `shipment_items` through the form. On dev, run `--dry-run` only (a smoke test). The real run and the later `--prune` are for production. To rehearse against production-shaped data, import `kevin.sql` into a scratch database (its MySQL 8 collation `utf8mb4_0900_ai_ci` must be rewritten to `utf8mb4_unicode_ci` for MAMP's MySQL), apply `2026_10_19_100000_create_shipment_items_and_shipment_totals.php` there with `--path`, point the process at it with the `DB_DATABASE` env var, run, then drop the scratch database. Rehearsal 2026-10-19: 6 shipments / 6 lines copied (5 matched, 1 unmatched, never guessed), re-run skipped all 6. The soft-deleted `commercial_invoice` row (shipment 42) is skipped by design, and shipments 40–43 carry a source `freight_charges` of 200000000000 that is copied as-is (a production data-entry error, not a backfill bug).
