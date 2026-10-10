# Dashboard Analytics Widgets

## Overview

The Filament `Dashboard` page (`App\Filament\Pages\Dashboard`, not the vendor default) is tabbed: `AccountWidget` always visible at the top, then 3 tabs — **Risk Overview**, **Performance**, **Exposure** — each holding 2 of the 6 analytics widgets. Adding a widget later is a one-line addition to `Dashboard::TABS`; adding a category is a new `Tab::make()` entry.

All 6 widgets are thin wrappers: each calls exactly one `App\Services\AnalyticsService` method and has no query logic of its own. Every `AnalyticsService` method does its aggregation (`SUM`/`CASE WHEN`/`DATEDIFF`/HHI math) in SQL, not PHP loops, and is cached individually via plain `Cache::remember('analytics:{key}', 300, ...)` — not `SmartCacheManager`, since these are cross-model aggregates, not one model's data (see `filamentPattern.md` §1.13 for the full caching contract).

## In-app legend (`HasMetricLegend` / `<x-metric-legend>`)

Every widget shows a collapsed-by-default "About this metric" disclosure under its chart/list, with four parts in order: **what it shows** (plain language), **data used** (models/columns, business-readable), **why it matters** (business value), and — separated by a fading horizontal divider — **technical** (the actual query logic: exact `table.column` names, join keys, `CASE WHEN`/`DATEDIFF` bucket boundaries), so both a non-technical user and a future maintainer can stop at the depth they need.

- `resources/views/components/metric-legend.blade.php` — the shared disclosure component (plain Alpine `x-data="{ open: false }"`, no dedicated JS file). Lives under `resources/views/components/` (the reusable-anonymous-component namespace, per `viewsPattern.md`), **not** `resources/views/filament/`, specifically so the `<x-metric-legend>` tag syntax resolves via Laravel's default anonymous-component convention.
- `app/Filament/Widgets/Concerns/HasMetricLegend.php` — trait providing `getLegend(): array`, reading `resources/dashboard/strings.php`'s `widgets.legend.{LEGEND_KEY}.{what,data,why}` keys via a `protected const LEGEND_KEY` on the widget class.
- **`ChartWidget` subclasses** (4 of them) use the trait + override `protected string $view = 'filament.widgets.chart-with-legend';` — a near-copy of Filament's own `chart-widget.blade.php` with one addition: a `<x-slot name="footer">` passed to `<x-filament::section>` (which already supports a `footer` prop/slot natively).
- **`ConcentrationRiskWidget`** (`StatsOverviewWidget`) has no such view hook — instead it overrides `getSectionContentComponent()` and calls the schema `Section::footer()` method (`Filament\Schemas\Components\Section` supports `->footer(string|Htmlable|...)` natively).
- **`PipelineStallWidget`** already owns its Blade view entirely, so the legend is just a `<x-slot name="footer">` added directly to its existing `<x-filament::section>`.

Testing gotcha: rendering a widget's own view or `getSectionContentComponent()` standalone via `view(...)->render()` in tinker throws `Using $this when not in object context` / `Typed property ...Component::$container must not be accessed before initialization` — artifacts of forcing Livewire/Schema lifecycle methods outside a real request, not real bugs. Verify these paths via `Blade::compileString()` + `php -l` on the compiled output instead — Page/Schema construction (e.g. `Dashboard::content()`) doesn't need a live Livewire request the way widget rendering does, so that path *does* work standalone in tinker.

## The 6 widgets

| Widget | Class | Legend key | Data used |
|---|---|---|---|
| Trade Cycle Time | `TradeCycleTimeWidget` | `cycle_time` | `purchase_requests.approval_date` → `registered_orders.order_date` (via `registered_order_purchase_request` pivot) → `payments.payment_date` (via `targetable_id`/`targetable_type`) → `shipments.eta` → `customs.clearance_date` (via `shipments.exit_date`) |
| Concentration Risk | `ConcentrationRiskWidget` | `concentration` | `payments.total_amount` grouped by `payee_id` (supplier HHI) and by `currency_id` (currency HHI) |
| Shipment Punctuality | `ShipmentPunctualityWidget` | `punctuality` | `shipments.eta` (estimated **arrival**, not departure — `etd` exists but is unused here) vs `shipments.exit_date` |
| Overdue Payment Aging | `ExposureAgingWidget` | `exposure_aging` | `payments.payment_deadline`, `payment_date`, `payable_amount`, `currency_id` — unpaid rows past deadline, bucketed 0-30/31-60/61-90/90+ |
| Open Currency Exposure | `OpenCurrencyExposureWidget` | `open_exposure` | `registered_order_items.line_total` (summed per order) minus `payments.total_amount` (matched via `targetable_id`=RO), grouped by `registered_orders.currency_id` |
| Stalled Files | `PipelineStallWidget` | `pipeline_stalls` | `purchase_requests.required_by_date`, `registered_orders.expected_delivery_date` (+ no linked Shipment), `payments.payment_deadline`, `shipments.eta` — each compared to `CURDATE()` |

Business-facing prose for all 6 lives in `resources/dashboard/strings.php`'s `widgets.legend.*` keys, all 3 locales. Don't let that copy drift from what the SQL actually does — e.g. the ETA/ETD distinction above is a real fact checked against `AnalyticsService::shipmentPunctuality()`, not a guess.

## Known scope limitations (intentional, not bugs)

- **Open Currency Exposure** only counts Payments targeted directly at a RegisteredOrder (`targetable_type = RegisteredOrder::class`). A Payment targeted at a linked PurchaseOrder instead won't offset that RO's exposure.
- **Stalled Files** excludes Customs clearance — `customs` has no explicit target-date column to compare against (unlike the other 4 stages, which all have one), so it was left out of the UNION rather than force a weaker heuristic.
- MySQL 5.7 (this project's actual DB) has no window functions, so `cycleTimeByStage()`'s percentile calculation can't be pure `PERCENTILE_CONT`/`NTILE` SQL — durations are still SQL-sorted (`ORDER BY duration`), with PHP only doing an O(1) index pick, not a sort.

## Calendar tab (added 2026-10-08)

The Dashboard's FIRST tab is the Calendar Rules view: `CalendarGridWidget` (month grid + agenda, plain `Widget`) and `CalendarDayWidget` (the project's first `TableWidget`, grouped by module). `Dashboard::TABS` entries accept an optional per-tab `columns` / `spans` map (default 2 columns, so the other tabs are unchanged); the calendar tab uses a 10-column grid with spans 7 and 3. Both widgets are non-lazy and exchange state through the `calendar-day-selected` / `calendar-toggled` Livewire events; the query logic lives in `App\Services\Calendar\Display\CalendarBoard`, not in the widgets. Chrome strings are under `resources/dashboard/strings.widgets.calendar.*`, the legend footer under `legend.calendar.*`. Full rules: `app/Services/Calendar/calendarPattern.md` section 9. Layout is responsive: the tab grid is `[default 1, lg 10]` and the widgets span `[default full, lg 7]` (grid) and `[default full, lg 3]` (day panel) — stacked full width on mobile, 70/30 from the `lg` breakpoint (asserted in `CalendarGridWidgetTest`).
