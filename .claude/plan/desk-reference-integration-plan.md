# Embedded Business-Insight Reference — Integration Plan

## Context

BMS-CM's operational modules (Purchase Request, Proforma Invoice, Registered Order, Purchase Order, Payment, Shipment, Custom, Bank Profile) run on institutional trade/procurement knowledge that today lives only in people's heads: correct terminology (Inquiry vs. RFQ vs. Quotation vs. Proforma vs. PO), the correct process sequence, and the red-flag checks that prevent bad supplier deals (unusual prepayment demands, below-market pricing, sanctions exposure). The founder wants this knowledge embedded directly in the tool, next to the records it applies to — turning the ERP into something that also *teaches* correct procurement, not just records it.

This is explicitly **not** "add a tab with a name." It must be:
- **Interactive**, not a wall of static text
- **Trackable** — some signal of whether staff are actually engaging with it
- **Reminding** — a nudge mechanism, without becoming naggy
- **Minimal to implement** — cheap enough to actually extend to all 8 modules, not just Purchase Request (the only module with authored content today)
- **Extendable** — module #2 through #8 should require content only, no new code

Two independent product passes (Fable 5, acting as PM) converged with the technical exploration below on a design that threads all of this through patterns **already established in this codebase** — no new architecture class introduced, just recomposition of things that already exist here: `config/workspace.php`'s whitelist-registry convention, the `dashboard/strings.php` nested-array translation precedent, `Section::footerActions()` from `InvoiceForm.php`, and this project's existing cookie-free, DB-free client-state habits.

---

## Naming (needs final pick — not locked)

| Option | English | Persian |
|---|---|---|
| **Recommended** | Desk Reference | مرجع کاری |
| Alt | Business Playbook | راهنمای کسب‌وکار |
| Alt | Field Guide | راهنمای عملیاتی |

"Desk Reference" reads as *the thing a professional keeps at arm's reach while working* — matches the in-context, daily-use placement better than "playbook" (implies a one-time strategy read) or "field guide" (consumer/outdoorsy connotation). Used as the plan's working name below; trivial to rename before implementation (it's one translation key).

---

## Architecture

### 1. Content model (data layer — where content lives)

Two files per module, mirroring conventions that already exist in this codebase:

**`config/desk-reference.php`** — a whitelist registry, structurally identical to the existing `config/workspace.php` pattern:
```php
'purchaseRequest' => [
    'model'   => PurchaseRequest::class,
    'icon'    => 'heroicon-o-book-open',
    'version' => 1,
],
```
`version` is the mechanism that re-arms the "unreviewed" reminder when content changes — bump it when a module's content is rewritten.

**`lang/{locale}/resources/{camelCaseResource}/deskReference.php`** (new file per resource, all 3 locales) — structured nested arrays, extending the one real precedent already in this codebase (`lang/*/dashboard/strings.php`'s `steps` array of `{title, description}` objects):
```php
'terms' => [
    'inquiry' => ['term' => 'Inquiry', 'definition' => '...'],
    'rfq'     => ['term' => 'RFQ', 'definition' => '...'],
    // ...
],
'process' => [
    ['title' => 'Register internal request', 'description' => '...'],
    // ...
],
'dos'   => ['Verify company registration history...', '...'],
'donts' => ['Treat unusual prepayment demands as a red flag', '...'],
'tips'  => ['A "Did you know" one-liner for the landing page rotator (Phase 2)'],
```
No content = tab doesn't render (no placeholder, no "coming soon" — silent until authored, matching the zero-tolerance for a half-finished feel).

### 2. Delivery (one trait, reused by every resource — the extension point)

New `app/Filament/Traits/HasDeskReferenceTab.php`, modeled directly on `HasExtraAttributesManagement`'s static-method convention:
- `getDeskReferenceInfolistTab(): ?Tab` — derives the lang/config key via `Str::camel(class_basename(static::getModel()))` (same auto-derivation `HasResourcePermissions` already uses for permission prefixes — zero new convention).
- Returns `null` if `Lang::has("resources/{$key}/deskReference.terms")` is false → the calling resource wraps its tabs array in `array_filter()` so the tab silently disappears when unauthored.
- Tab body is **one generic Blade partial** (`resources/views/filament/desk-reference/panel.blade.php`) fed the resolved content array via a `Schemas\Components\View` component — not hand-built per-module `RepeatableEntry` chains. This is the actual scaling guarantee: every module's tab renders through the identical partial; only the data differs.

**Tab position:** appended as the new last tab, after Extra Attributes — "the two auxiliary tabs (Extra Attributes, Desk Reference) always trail General, in that order." Simpler than inserting mid-stack (avoids creating a second precedent for the mid-stack exception Shipment's Invoice tab already represents); reversible in five minutes if it should lead instead.

### 3. Interactivity (inside the one Blade partial — no new Alpine file needed)

Everything below lives in inline `x-data` inside `panel.blade.php` — no new registered Alpine factory, no new JS bundle entry:
- **Live glossary search** — a text input filtering term cards client-side (content's already in the DOM; ~10 lines of Alpine).
- **Auto-tracked "seen" state, zero clicks required** — `x-init` on the partial sets a plain browser cookie (`document.cookie`, no `fetch`, no Livewire action) the moment a user actually opens the tab: `dr_seen_{key}_v{version}=1`. Opening the tab **is** the usage signal — no separate "mark as reviewed" chore to forget.
- **The reminder** — `Tab::make(...)->badge(fn () => request()->cookie("dr_seen_{$key}_v{$version}") ? null : '●')->badgeColor('warning')`. A quiet dot on the tab label until first opened; disappears on the next page load after opening (cookie round-trip, not instant same-request reactivity — an acceptable, honest tradeoff for zero backend work). Content updates (`version` bump) silently re-arm it for everyone.
- **Do's/Don'ts** rendered as two icon-coded lists (`heroicon-o-check-circle` / success, `heroicon-o-exclamation-triangle` / danger) reusing the existing `.tb-badge` palette — no new CSS tokens needed.

Honest limitation, stated plainly: cookie-based tracking is per-browser, not per-audited-user — there is no admin-facing "% of staff who reviewed this" report in v1. That's the deliberate trade for zero DB/migration work. If that reporting is ever wanted, it's a bounded Phase-3 upgrade (below) — the partial's data contract doesn't change, only the persistence swaps from cookie to a DB write behind the same `x-init` call.

### 4. Extending to module #2–#8

Per new module, exactly three edits, no new code:
1. Add one entry to `config/desk-reference.php`.
2. Author `lang/{en,fa,fr}/resources/{key}/deskReference.php`.
3. Add `use HasDeskReferenceTab;` to the resource + one line appending `static::getDeskReferenceInfolistTab()` (inside the existing `array_filter([...])` tabs call) in its `Traits/Infolist.php`.

---

## Phasing

**Phase 1 (this pass — minimal, ships now):**
- `HasDeskReferenceTab` trait + `panel.blade.php` partial + `config/desk-reference.php` registry.
- Purchase Request content authored (fa first, then en/fr) from the seed content already provided.
- Trait wired into all 8 operational resources (tab silently absent for the 7 unauthored ones — plumbing-ready, not content-ready).
- Cookie-based seen-tracking + tab reminder dot + glossary search, as above.

**Phase 2 (once Phase 1 proves people actually open the tab):**
- Landing-page "Did you know?" rotator — one small Blade component on `landing-page.blade.php`, server-picks a random `tips[]` entry from whichever modules have content, deep-links to that module's Desk Reference tab. Cheap (one partial + one PHP call), and it's the answer to whether this needs a landing-page/workspace layer — a *pointer* into the embedded feature, not a duplicate destination.
- Explicitly **not** building a "Knowledge Center" aggregator page — it would duplicate the per-module surface and compete with the existing spotlight search. If aggregation is ever wanted later, index glossary terms into the existing `/api/search/spotlight` results instead of a new page.
- Optional 3-question self-check quiz per module (only for modules where the content author actually wants one — a stale/skipped quiz is worse than none).
- Optional field-level deep links (a hint icon on a specific form field jumping straight to its glossary term) — real value, but per-field manual wiring, so deferred until the base feature is validated.

**Phase 3 (only if leadership wants adoption reporting):**
- One `desk_reference_acknowledgements` table (`user_id`, `module_key`, `content_version`, `acknowledged_at`), swapped in behind the same `x-init` trigger, plus a small dashboard widget ("78% of procurement staff have reviewed Payment's red flags"). Bounded, additive, doesn't touch Phase 1/2 markup.

---

## Files to create / touch (Phase 1)

- `app/Filament/Traits/HasDeskReferenceTab.php` — new trait
- `resources/views/filament/desk-reference/panel.blade.php` — new generic partial
- `config/desk-reference.php` — new registry (mirrors `config/workspace.php`)
- `lang/{en,fa,fr}/resources/purchaseRequest/deskReference.php` — new, content authored from the seed material
- `app/Filament/Resources/Operational/PurchaseRequestResource/Traits/Infolist.php` (+ the equivalent trait file in the other 7 operational resources) — wire the trait's tab into the existing `->tabs(array_filter([...]))` call
- `app/Filament/filamentPattern.md` — document the new trait, the "auxiliary tabs trail General, in that order" rule, and the config/lang file convention (doc-sync policy)
- `app/Utils/helpersPattern.md` / CLAUDE.md localization section — document the new `deskReference.php` per-resource file + `config/desk-reference.php` registry pattern

## Verification

- `./vendor/bin/pint` and `php artisan test` after implementation (no test suite changes anticipated unless a feature test for tab visibility/hiding is added).
- `npm run build` if any CSS is touched (expect none — reusing `.fi-section`/`.tb-badge`).
- No browser tooling available in this environment — build/lint-verify, then ask for visual confirmation of the tab (search filter, reminder dot behavior, do/don't styling) in the actual dashboard once implemented.

---

## Still open

1. Final name pick (table above).
2. Confirm tab placement (last, after Extra Attributes) vs. before Extra Attributes.
