Verified against source on branch `master`. Where this doc conflicts with CLAUDE.md, this doc is authoritative for Filament resource architecture.

# BMS-CM Filament Resource Pattern

This is the replicable pattern for BMS-CM's Filament v4 resources. Every new resource — Operational or Master — must be composed exactly as documented here: two-tab form/infolist, `getEloquentQuery()` eager-loads, `SmartCacheManager` navigation badges, and (where applicable) the EAV `extraAttributes` tab. The core mechanism is **trait-based schema composition**: each root Resource class composes its form, table, infolist, filters, permissions, and EAV behavior through a single `use` list of traits on the root class itself — there are no dedicated presenter/action classes per resource, the traits ARE the presenters. This keeps every resource's full surface area visible in one file, at the cost of trait method-name uniqueness, enforced by prefixing (`getXxxField` / `showXxx` / `viewXxx` / `getXxxFilter`). Future AI agents must reproduce this layout verbatim; deviations are bugs.

## Recommended structure

```
app/Filament/Resources/
    XxxResource.php                          ← root class, namespace App\Filament\Resources
    Operational/XxxResource/
        Traits/Form.php  Table.php  Infolist.php  Filters.php  TotalXxxCalculation.php
        Traits/InvoiceForm.php                ← Shipment only
        Enums/Status.php
        Exports/XxxExporter.php
        Pages/ListXxx.php / CreateXxx.php / EditXxx.php
        RelationManagers/…
    Master/XxxResource/
        Traits/Table.php / Infolist.php / Filters.php
        Pages/ManageXxx.php                   ← single-page (no create/edit routes)
    General/
        FormComponents.php   ← getAttachmentsField() + others
        InfoComponents.php   ← cross-resource relation badges
        TableComponents.php  ← matching table columns
app/Filament/Traits/
    HasResourcePermissions.php  HasExtraAttributesManagement.php
    HandleActivation.php  ExportDefaults.php  HasDeskReferenceAction.php
    HasStatusWorkflow.php
```

`DashboardPanelProvider` registers resources via `->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')`. Only root-level classes that extend `Resource` are registered. The `Operational/` and `Master/` subdirectories hold Traits/Pages/RelationManagers/Enums/Exports imported by the root file — they are NOT auto-registered as resources. **A new resource MUST create a top-level `app/Filament/Resources/{Name}Resource.php` with `namespace App\Filament\Resources`.**

## 1. Responsibility of each part

### 1.1 Root resource class — the composer

The root class declares `namespace App\Filament\Resources`, extends `Resource`, and `use`s the trait list (Pint sorts it alphabetically). It owns the four `static` Filament hooks (`form`, `infolist`, `table`, `getEloquentQuery`), plus `getPages`, `getRelations`, `getNavigationGroup`, `getNavigationBadge`. The trait methods are called from inside these hooks.

Verified `use` lines:

```php
// PurchaseRequestResource.php:51 — HasStatusWorkflow's first consumer
use HasDeskReferenceAction, HasExtraAttributesManagement, HasResourcePermissions,
    HasStatusWorkflow, PurchaseRequestFilters, PurchaseRequestForm, PurchaseRequestInfolist,
    PurchaseRequestTable, TotalCostCalculation;

// PurchaseOrderResource.php:53 — HasStatusWorkflow's second consumer, ungated no-op until PO's Status type gets an admin-configured stage_order
use HasDeskReferenceAction, HasExtraAttributesManagement, HasResourcePermissions,
    HasStatusWorkflow, PurchaseOrderFilters, PurchaseOrderForm, PurchaseOrderInfolist,
    PurchaseOrderTable, TotalCalculation;

// ShipmentResource.php:47 — Shipment inserts ShipmentInvoiceForm, the only sanctioned mid-tab
use HasDeskReferenceAction, HasExtraAttributesManagement, HasResourcePermissions,
    ShipmentFilters, ShipmentForm, ShipmentInfolist, ShipmentInvoiceForm, ShipmentTable;

// BankResource.php:33 — master: no EAV, no totals, no HasDeskReferenceAction, uses HandleActivation
use BankFilters, BankForm, BankInfolist, BankTable, HandleActivation, HasResourcePermissions;
```

Trait namespaces:
- Per-resource: `App\Filament\Resources\Operational\{Name}Resource\Traits\{Form|Table|Infolist|Filters}` (Form aliased `as {Name}Form`), plus `…\Traits\TotalCostCalculation` (PurchaseRequest) or `…\Traits\TotalCalculation` (PurchaseOrder / RegisteredOrder). Shipment uses `App\Filament\Resources\Operational\ShipmentResource\Traits\InvoiceForm`.
- Shared root-level: `App\Filament\Traits\{HasResourcePermissions, HasExtraAttributesManagement, HandleActivation, ExportDefaults, HasDeskReferenceAction, HasStatusWorkflow}`.

### 1.2 Per-resource schema traits — `Form`, `Table`, `Infolist`, `Filters`

Each trait exposes `static` helpers consumed by the root class. Method prefixes are mandatory:

| Prefix | Returns | Called from |
|---|---|---|
| `getXxxField()` | `TextInput` / `Select` / `DatePicker` / … | `form(Schema)` |
| `showXxx()` | `TextColumn` | `table(Table)` |
| `viewXxx()` | `TextEntry` / `RepeatableEntry` | `infolist(Schema)` |
| `getXxxFilter()` | `SelectFilter` / `Filter` / `TrashedFilter` | `table(Table)` |

The root `form()` / `table()` / `infolist()` methods just assemble these helpers into Tabs/columns — they do not define field internals.

### 1.3 Two-tab uniform form structure (the canonical 8 operational resources)

```php
Tabs::make('PurchaseRequest')
    ->tabs([
        Tab::make(__('resources/purchaseRequest/strings.form.tab_general'))
            ->icon('heroicon-o-…')
            ->schema([
                \Filament\Schemas\Components\Group::make()
                    ->schema([ Section::make(...)->schema([...])->columns(3) ])
                    ->columnSpan(['lg' => 2]),
                \Filament\Schemas\Components\Group::make()
                    ->schema([ Section::make(...)->schema([...]) ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3),                       // <- columns(3) on the TAB, not on root Schema
        static::getExtraAttributesFormTab(),    // <- always last tab
    ])
    ->columnSpanFull();                          // <- root Schema: columnSpanFull, NO ->columns()
```

Rules:
- `->columns(3)` is on the `Tab`, NEVER on the root Schema.
- `->columnSpanFull()` is on the `Tabs` container.
- The root Schema has no column setting.
- `static::getExtraAttributesFormTab()` is always the last tab.
- Shipment inserts `static::getInvoiceFormTab()` before the extra-attributes tab — the ONLY sanctioned mid-tab. No other resource may insert a tab between General and Extra Attributes without amending this doc.

### 1.4 Two-tab infolist structure

```php
Tabs::make('Details')->tabs([
    Tab::make(__('…infolist.tab_general'))->icon(…)
        ->schema([ Section::make()->schema([…])->columns(3) ]),
    // optional Items tab
    Tab::make(fn($record) => tabBadge(__('…infolist.tab_documents'),
        $record?->attachments->count() ?? 0, 'info'))
        ->schema([…]),
    static::getExtraAttributesInfolistTab(),   // always last
])->columnSpanFull()
```

`tabBadge($label, $count, $color)` is the global helper (`app/Utils/helpers.php`) returning an `HtmlString` — label + inline `.tb-badge` span. Use it for any tab whose label must show a count.

### 1.5 `HasResourcePermissions` — the access-control system for Filament resources

`App\Filament\Traits\HasResourcePermissions` is what every Filament resource relies on for its authorization checks. Derives the permission prefix from the model basename via `Str::snake()`. Permission strings follow `{snake_singular_model}.{view|create|edit|delete|restore}`. Every module prefix in the DB must carry all five actions — the Role form's per-action toggles resolve `%.{action}` from the DB, so a missing row (e.g. a seeder list updated after the last seed) makes the toggle silently save nothing; `ResourceAuthorizationIntegrityTest::test_every_module_prefix_has_the_full_action_set_seeded` catches the drift, and the idempotent repair is `Permission::firstOrCreate` for each missing `"{module}.restore"`-style row.

Verified — actual trait body (`app/Filament/Traits/HasResourcePermissions.php`):

```php
public static function getPermissionPrefix(): string
private static function allows(string $action): bool
private static function allowsResponse(string $action): Response
// + canViewAny / canView / canCreate / canEdit / canDelete / canDeleteAny
//   canForceDelete / canForceDeleteAny / canRestore / canRestoreAny
// + getViewAnyAuthorizationResponse / getViewAuthorizationResponse / getCreateAuthorizationResponse
//   / getEditAuthorizationResponse / getUpdateAuthorizationResponse / getDeleteAuthorizationResponse
//   / getDeleteAnyAuthorizationResponse / getForceDeleteAuthorizationResponse / getForceDeleteAnyAuthorizationResponse
//   / getRestoreAuthorizationResponse / getRestoreAnyAuthorizationResponse
//   / getAttachAuthorizationResponse / getDetachAuthorizationResponse / getDetachAnyAuthorizationResponse
//   / getAssociateAuthorizationResponse / getDissociateAuthorizationResponse / getDissociateAnyAuthorizationResponse
```

There is no `canCreateAny` or `canEditAny` on this trait — do not assume Filament's full `can*` surface is overridden; only the methods listed above exist. `canRestore`/`canRestoreAny` map to the `restore` permission; `canForceDelete`/`canForceDeleteAny` map to `delete`.

**`getUpdateAuthorizationResponse` exists alongside `getEditAuthorizationResponse` for a non-obvious reason**: Filament's RelationManager bridge (`InteractsWithRelationshipTable::getAuthorizationResponse()`) builds the method name it looks for from the *raw internal action string*, not the friendly one — Edit's internal action is `'update'`, so the bridge requests `getUpdateAuthorizationResponse`, never `getEditAuthorizationResponse`. Without the `Update`-named method, a RM's Edit action silently fell through to Filament's policy-based vendor default (allow-everything, since this project has no Policies) — same story for `getAttachAuthorizationResponse`/`getDetachAuthorizationResponse`/`getDetachAnyAuthorizationResponse`/`getAssociateAuthorizationResponse`/`getDissociateAuthorizationResponse`/`getDissociateAnyAuthorizationResponse`, all of which map to the `edit` permission. Enforced by `Tests\Unit\ResourceAuthorizationIntegrityTest::test_permission_trait_covers_every_action_the_relation_manager_bridge_can_request` — any future action Filament's bridge can request must have a matching method here or the test fails.

**The `get*AuthorizationResponse()` methods are load-bearing, not redundant with the `can*` booleans.** Filament's own table row/bulk actions (`DeleteAction`, `RestoreAction`, `ForceDeleteAction`, and `EditAction`/`CreateAction` button visibility) authorize via `Resource::get*AuthorizationResponse()` — see `vendor/filament/filament/src/Resources/Pages/Page.php`'s `getDefaultActionAuthorizationResponse()` — never via the `can*` booleans directly. Only page-level route access (`EditRecord`/`CreateRecord`/`ViewRecord::mount()`) calls the booleans. Before this trait overrode both families, the `get*AuthorizationResponse()` half silently fell through to Filament's Policy-based vendor default, which — since this project has zero Policy classes and no `Gate::before()` — resolved to `Response::allow()` for every action regardless of Spatie permission. This let any authenticated user delete/restore any record through the table UI even with only `view`/`create` permission. Both method families must stay in sync; if you add a new guarded action, override it in both places.

Every resource `use`s this trait for its own CRUD gating.

`app/Policies/` does not exist — confirmed empty/absent. `HasResourcePermissions` is the sole access-control system for every Filament resource; there are no Policies, wired or otherwise. Do not create one for a Filament-managed model — if it were ever auto-discovered, Filament would resolve it ahead of the trait's `can*` methods and silently change access semantics.

### 1.6 `HasExtraAttributesManagement` — the inline EAV Repeater

```php
public static function getExtraAttributesFormTab(): Tab
public static function getExtraAttributesFormSection(): Section   // legacy, back-compat only
public static function getExtraAttributesInfolistTab(): Tab
protected static function buildExtraAttributesRepeater(): Repeater
```

Repeater shape:

```php
Repeater::make('extraAttributes')
    ->relationship()
    ->schema([
        TextInput::make('key'),
        Textarea::make('value'),
    ])
    ->columns(2)
    ->defaultItems(0)
    ->reorderableWithButtons()
```

Only the `value` field/entry needs it (`key` is always a plain string):

```php
->formatStateUsing(fn($state) => match (true) {
    is_string($state) => $state,
    is_null($state) => '',
    default => json_encode($state, JSON_UNESCAPED_UNICODE),
})
```

**This `formatStateUsing` on `value` is mandatory.** `EntityAttribute.value` is JSON-cast, so on read Eloquent returns arrays/scalars, not strings. Without it, `Textarea::make('value')` and `TextEntry::make('value')` try to render an array and throw.

**`null` needs its own branch — do not fold it into the `json_encode` fallback.** `is_string(null)` is `false`, so a naive `is_string($state) ? $state : json_encode($state, ...)` sends `null` through `json_encode`, which returns the 4-character string `"null"` — a real bug, reproduced live 2026-09-22 in both the form `Textarea` and the infolist `TextEntry` (the infolist's `->placeholder('-')` never fires because the field no longer reads as empty once it holds the literal text `"null"`). Route `null` to `''` explicitly so the infolist placeholder and any "required" validation still see it as blank.

**The Repeater's save side is NOT Filament's default `->relationship()` sync** — `buildExtraAttributesRepeater()` chains `->saveRelationshipsUsing(function (Repeater $component): void { ... })` after `->relationship()`, which replaces the default create/update/delete closure entirely (it still keeps the default `loadStateFromRelationshipsUsing` for hydration). It collects `[key => value]` from `$component->getItems()` and delegates to `$record->syncCustomAttributes($keyValueMap)` — see `modelsPattern.md` §5 for why: `entity_attributes` carries a real `UNIQUE(entity_type, entity_id, key)` index, and Filament's default relationship-Repeater save path does a raw `create()` for any item not matched to an existing record ID, which collides with a soft-deleted row sharing the same key. Do not revert this to a bare `->relationship()` call.

Operational resources use the **Tab** variant (`getExtraAttributesFormTab`); the `Section` variant exists only for back-compat and must not be used in new resources.

### 1.6a `InfoComponents::getStatusHistoryTab()` — the shared read-only History tab

Same shape as `HasExtraAttributesManagement::getExtraAttributesInfolistTab()` (badge-counted `Tab` → `Section` → `RepeatableEntry`), but lives on `InfoComponents` (not a Filament trait) since it needs no form-side counterpart — the audit log is append-only, never user-edited:

```php
InfoComponents::getStatusHistoryTab(): Tab
    ->badge(fn ($record) => $record?->statusHistories()->count() ?: null)
    ->schema([RepeatableEntry::make('statusHistories')
        ->getStateUsing(fn ($record) => $record?->statusHistories()->with(['status', 'fromStatus', 'user'])->latest()->get())
        ->schema([fromStatus.localized_name, status.localized_name, user.name, created_at->adaptiveDateTime(), reason])])
```

The `reason` `TextEntry` (added 2026-09-26 alongside `StatusWorkflow`'s "Return for Revision" action) is `->columnSpanFull()` and `->visible(fn ($record) => filled($record?->reason))` per row — it only renders on the handful of history rows a reason-carrying action actually wrote (see `modelsPattern.md` §6b's `withStatusHistoryReason()`), staying invisible for the ordinary forward-transition rows every resource already had before this addition.

Composed into all 8 status-bearing operational resources' `infolist()` (`PurchaseRequest`, `RegisteredOrder`, `PurchaseOrder`, `Payment`, `Shipment`, `Custom`, `Correspondence`, `BankProfile` — `ProformaInvoice` has no status column and does not get this tab), placed as the last tab, or second-to-last immediately before `static::getExtraAttributesInfolistTab()` where that tab exists (`Correspondence` has no EAV tab, so History is simply its own last tab). Backed by `App\Models\StatusHistory` + `App\Models\Traits\General\TracksStatusHistory` — see `modelsPattern.md` §6b for the model/trait side. **`statusHistories` is deliberately NOT in any composing resource's `eagerRelations()`** — that array feeds the shared `getEloquentQuery()` used by both list-page table queries and single-record view/edit queries, and this relation grows unbounded over a record's lifetime, so a blanket eager load would load full history on every list-page row for a relation only ever displayed on this one infolist tab. Instead the tab scope-loads it itself, once, for the single record it renders (`$record->statusHistories()->with([...])->latest()->get()`), which is 4 queries total for that one record — not an N+1 concern since it never iterates over multiple parents.

### 1.7 `HandleActivation` — master `is_active` bulk toggle

```php
protected static function getActivateBulkAction(): BulkAction
protected static function getDeactivateBulkAction(): BulkAction
```

Both execute `static::getModel()::whereIn('id', $records->pluck('id'))->update(['is_active' => 1|0])` and call `deselectRecordsAfterCompletion()`. Master resources only.

### 1.8 `ExportDefaults` — exporter classes (not resources)

```php
getFileName(Export): string  // "{app}-{MODEL}-{His}"
static modifyQuery(Builder): Builder  // parent::modifyQuery($query)->with(['creator','updater', ...eagerLoadRelations()])->limit(1000)
getCompletedNotificationBody(Export)
protected static function eagerLoadRelations(): array  // override to add exporter-specific eager loads; empty by default
```

**Verified 2026-09-22 — was `getQuery(): Builder`, a dead-code method name.** Filament v4's actual `Filament\Actions\Exports\Exporter` base class has no `getQuery()` hook at all; the real query-modification hook `CanExportRecords` calls is the **static** `modifyQuery(Builder $query): Builder`. The export pipeline builds its query from `$livewire->getTableQueryForExport()` (or `$exporter::getModel()::query()` when not table-bound), then pipes it through `$exporter::modifyQuery($query)` — never an instance `getQuery()`. Every one of the 18 exporters project-wide relied solely on the shared `ExportDefaults` trait for this, so the bug was silent: the documented 1 000-row cap was never actually enforced on any export, and `eagerLoadRelations()` overrides were inert dead code — masked only because most exporters' extra eager loads duplicate what the owning Resource's own `getEloquentQuery()` already loads via the table query it inherits. Confirmed via a real end-to-end row-build probe (`Export::getExporter()->__invoke($record)`) both before the fix (no `LIMIT` on the built query) and after (`LIMIT 1000` + the full eager-load list present). Do not reintroduce `getQuery()` — the method name Filament actually dispatches to is `modifyQuery()`, static, taking/returning the `Builder`.

### 1.8a Shared import pipeline (`app/Services/Imports/`) — `App\Filament\Traits\ImportDefaults`, `App\Filament\Actions\ImportAction`, `App\Jobs\ImportCsv`

**Rewritten 2026-09-22 — column-building logic moved out of `ImportDefaults` into a shared, module-agnostic service.** Every per-column static helper that used to live on the `ImportDefaults` trait (`textColumn`/`numberColumn`/`booleanColumn`/`dateColumn`/`statusColumn`/`lookupColumn`/`columnLabel`/`localizedGuesses`/`localizedEnumMap`/`foldDigits`/`extraAttributeColumns`/`extraAttributeMapFromData`) was deleted — this was the "haphazard, per-module, easy-to-forget-a-point" design the shared service replaced. **Full architecture, file inventory, and the column taxonomy (match/fallback/optional/manual-set) live in `app/Services/Imports/importsPattern.md` — read that first, this section only covers what's still specific to the Filament layer (the trait/Action/Job glue the pipeline plugs into).**

`App\Filament\Traits\ImportDefaults` now holds ONLY genuine `Filament\Actions\Imports\Importer`-lifecycle glue — nothing column-shaped:

```php
public function __invoke(array $data): void   // locale switch + CodeGeneratingObserver::duringImport + NotificationDispatcher::suspended + DB::transaction(parent::__invoke) + deadlock reclassification
public function resolveRecord(): ?Model        // withTrashed() match on SCANNABLE_IDENTIFIER; trashed match throws RowImportFailedException (never auto-restores); a NEW row (no match, or blank identifier) also runs assertRequiredColumnsForNewRecordsPresent()
protected function requiredColumnsForNewRecords(): array   // column name => translated label; only for a column with no `ImportColumnDefinition` fallback/reject story of its own — neither current importer uses this any more (the pipeline's RejectMissingManualColumns stage covers it), the hook stays for an edge case a future importer might still need
public static function getCompletedNotificationBody(Import): string
public static function getOptionsFormComponents(): array   // locale/jalali/date_format hidden+select fields merged into $options — genuinely an Importer hook, not an ImportAction one, despite living conceptually with the Action; verify against vendor `Importer::getOptionsFormComponents()` before assuming otherwise
protected static function assertColumnNamesAllowed(array): array   // throws LogicException if any column is id/user_id/updated_by_id/*_type — ImportColumn::fillRecord() uses data_set() directly, bypassing $fillable
protected function fillReservedNumberIfBlank(string $field): void   // + nextReservedNumber()/reserveNumberChunk()/resetReservedNumbers() — chunk-scoped business-number reservation, unchanged by the rewrite
```

Each concrete importer (`PurchaseRequestImporter`, `ProformaInvoiceImporter`, and their item importers) implements `App\Contracts\Imports\Importable` and declares its columns ONCE via `importColumns(): ImportColumnDefinition[]`; `getColumns()` builds the real `ImportColumn[]` via `ImportColumnFactory::build()`. Fallback/reject logic runs from `afterFill()` (pre-save, via `ImportPipeline::runBeforeSave()`); child-row persistence/EAV-sync/recalculation runs from `afterSave()` (post-save, via `ImportPipeline::runAfterSave()`) — see `importsPattern.md` for exactly why the split is pre-save vs post-save (a blank NOT NULL column must be resolved or cleanly rejected BEFORE the first `saveRecord()`, not after).

**`total_estimated_cost` is recalculated from item rows on import when items carry a real cost, overriding whatever the CSV's own `total_estimated_cost` cell said** (`PurchaseRequestImporter::recalculateAfterPersist()`, called by the pipeline's `RunModuleRecalculation` stage at the end of `afterSave()`, after child items are persisted). Mirrors the create FORM's `TotalCostCalculation::updateTotalCost()` — the form field is effectively read-only/live-computed from items, never an independent user entry. Only recomputes when at least one item has a non-blank `estimated_cost`; if every item's cost is blank, the imported/default `total_estimated_cost` value is left alone. Formula is `sum(quantity * estimated_cost)` across all the parent's items, forced-filled and re-saved as a second `UPDATE` after the group's items exist — can't be done inside the same `INSERT` since items don't exist until `afterSave()`. Proforma Invoice has no equivalent — its `recalculateAfterPersist()` is a deliberate no-op.

**Column labels always show `Label (db_column_name)`, and the sample-file header matches it** — both `->label()` and `->exampleHeader()` are set to the same `LocalizedMatcher::columnLabel()` string by `ImportColumnFactory::build()` for every column, in every importer, unconditionally. `App\Filament\Actions\ImportAction::setUp()` RE-REGISTERS the vendor `downloadExample` modal action (via `registerModalActions([Action::make('downloadExample')->...])`) to filter out columns the importer marks auto-filled (`ImportColumnDefinition::includeInTemplate() === false`, read via `$importer::autoFilledColumnNames()`, duck-typed via `method_exists`) — so the template only asks for what the user must actually decide.

**Two real bugs found and fixed in `ImportAction.php` while re-verifying this section, 2026-09-22 (the accordion help-guide modal, in progress since an earlier session, was crashing on open):**
- `modalDescription()`'s closure was typed `function (\Filament\Actions\ImportAction $action): HtmlString` — Filament's `evaluate()` (`EvaluatesClosures::resolveClosureDependencyForEvaluation()`) injects `$this` for a parameter only when its type-hint string-matches `static::class` exactly; since this closure lives inside OUR subclass `App\Filament\Actions\ImportAction`, the vendor-class type-hint never matches, so `evaluate()` fell through to `app()->make('Filament\Actions\ImportAction')` — a broken, unrelated instance. Fixed by dropping the parameter entirely and using `$this` directly (the closure is defined inside an instance method, so `$this` is already correctly bound — no injection needed at all).
- `buildImportGuideHtml()` called `collect($columns)->whereIn(fn ($column) => $column->getName(), $autoFilledColumns)` — `Collection::whereIn($key, $values)` expects `$key` to be a string/dot-path (it calls `data_get($value, $key)` internally), not a closure; passing a closure made `data_get()` try to `explode('.', $closure)`, a `TypeError`. Fixed to `->filter(fn ($column) => in_array($column->getName(), $autoFilledColumns, true))`.

Both are covered by `test_import_modal_description_renders_the_accordion_guide_without_crashing` in `PurchaseRequestResourceTest.php` — a real `Livewire::test(...)->mountAction('importPurchaseRequests')` + `getModalDescription()` call, not a unit-level guess, since the failure only reproduced through Filament's actual modal-render path.

**Business rules a new importer must reproduce (still true after the rewrite):**
- Every column built by `ImportColumnFactory` calls `->ignoreBlankState()` — a blank mapped cell never overwrites existing data on update. Import cannot be used to CLEAR a field; that's a known limitation, not a bug.
- `applyNumber()` deliberately never calls Filament's own `->numeric()` — its built-in numeric cast silently zeroes Persian-digit input before any custom cast runs. Digits are folded (۰–۹ / ٠–٩ → ASCII) first via `LocalizedMatcher::foldDigits()`, state stays a string validated by a strict `^-?\d+(\.\d+)?$` regex, and the model's own `decimal:5` cast does the final numeric conversion on save.
- `applyDate()` reads `$options['jalali']`/`$options['date_format']` (wired via `getOptionsFormComponents()`), folds digits, then parses STRICTLY via a hand-rolled regex + `checkdate()` (Gregorian) or `Morilog\Jalali\CalendarUtils` (Jalali) — never Carbon's lenient `createFromFormat`. An unparseable/invalid date throws `RowImportFailedException` straight out of `castStateUsing`.
- `applyRelationMatch()` uses Filament's native `->relationship(resolveUsing: ...)` (memoized via `ImportColumn::$resolvedRelatedRecords`) plus an explicit `['bail', $closure]` rule pair so ONE named message shows instead of a duplicate vendor `validation.exists` message.
- The column denylist (`id`, `user_id`, `updated_by_id`, plus the app's four actual polymorphic-type columns `attachable_type`/`entity_type`/`targetable_type`/`correspondable_type`) is enforced by wrapping every `getColumns()` return in `static::assertColumnNamesAllowed([...])` — throws at definition time. **Was a blanket `str_ends_with($name, '_type')` suffix ban until 2026-09-25**, when Registered Order's own `currency_type` column (a genuine, `$fillable` plain string column, confirmed via `SHOW COLUMNS` — not a morph discriminator) tripped the blanket rule and had to be importable per that module's settled column policy. Fixed by replacing the suffix heuristic with an explicit list of the schema's real morph-type columns — strictly more precise, not more permissive for the columns the guard actually exists to protect (a genuine morph `*_type` column is never in a model's `$fillable` on the writing side of the relation, so listing the four real ones loses no protection). Don't reintroduce the blanket suffix check.

**`App\Filament\Actions\ImportAction extends Filament\Actions\ImportAction`:**
- `getUploadedFileStream()` sniffs the ZIP magic bytes (`PK\x03\x04`) FIRST, then only runs the BOM-tolerant `mb_check_encoding(..., 'UTF-8')` hard-reject on the non-XLSX (CSV/text) branch — checking raw bytes for UTF-8 validity before the XLSX sniff would reject every legitimate binary .xlsx upload, since a zip archive is never valid UTF-8 text. XLSX conversion goes through `openspout/openspout` (`OpenSpout\Reader\XLSX\Reader`, with `Options::SHOULD_FORMAT_DATES = true` — it defaults to `false`, so date-styled cells silently read back as raw Excel serial numbers without it) into a real temp file (`tempnam()`, never `php://temp` or an in-memory string), rejecting outright: more than one sheet, and any `FormulaCell` whose `getComputedValue()` is `null` (an unevaluated formula) or an `ErrorCell`.
- `getFileValidationRules()` REPLACES vendor's hardcoded `extensions:csv,txt` (verified in `vendor/filament/actions/src/ImportAction.php` — that literal is inline in the rules array vendor's method builds, not derived from an overridable hook by itself) with `extensions:csv,txt,xlsx` + a `mimetypes:...` rule + `max:10240` (10 MB), keeping the duplicate-column-detection closure but wrapping the `getUploadedFileStream()` call in a try/catch so our XLSX/UTF-8 rejection messages surface as a normal `$fail()` field error instead of an uncaught exception. **Deliberately not overridden:** the `FileUpload::make('file')->acceptedFileTypes([...])` browser-picker filter set inside vendor's `setUp()` stays CSV-only, since `Action::schema()` fully REPLACES (never merges) the modal schema — widening it would mean duplicating vendor's entire ~150-line `setUp()` body for a cosmetic file-picker hint; the actual security/validation boundary (`getFileValidationRules()`) is fully upgraded regardless.
- `resourceGate(string $resourceClass): static` wires `->authorize(fn () => $resourceClass::canCreate() && $resourceClass::canEdit(null))` — re-upload updates existing records, so create-only permission is insufficient.
- `setUp()` sets `->maxRows(5000)` and `->job(App\Jobs\ImportCsv::class)`.

**`parentImporter()` was removed 2026-09-22** (it guarded a two-file design's item importer from running concurrently with its still-in-progress parent import — see §1.8b; group-atomicity supersedes it, there is no longer a second importer to race against for Purchase Request).

**Action placement: table `->toolbarActions([...])`, not page header actions, and not an `ActionGroup` dropdown.** Corrected twice — first 2026-09-XX (per user feedback, moved OUT of a collapsed `ActionGroup` into standalone header buttons so they weren't hidden behind a dropdown), then 2026-09-22 (per user feedback again, moved OUT of `getHeaderActions()` entirely and INTO the table's `->toolbarActions([...])`, sitting as siblings of `BulkActionGroup::make([...])`, since import/export act on/produce a batch of table rows, not a single new record the way `CreateAction` does). Reference wiring: `PurchaseRequestResource::table()` calls `static::getImportAction()` / `static::getExportAction()` — two builder methods on the resource's own `Traits/Table.php` (matching this project's `getXxxFilter()`-style naming convention), NOT inline in `getHeaderActions()`. `ActionGroup` stays reserved for record-level actions (View/Edit/Delete on a table row — see `recordActions` elsewhere in this doc).

**`App\Jobs\ImportCsv extends Filament\Actions\Imports\Jobs\ImportCsv`:** overrides `middleware()` to give the `WithoutOverlapping` lock `->releaseAfter(15)` (vendor default is `null`, which tight-loops re-queuing with zero delay) alongside the existing `->expireAfter(600)`; overrides `handle()` to call `$this->importer->resetReservedNumbers()` in a `finally` after `parent::handle()`.

**Deadlock handling (`ImportDefaults::__invoke`/`isDeadlock`):** the nested `DB::transaction()` around `parent::__invoke($data)` is a real SAVEPOINT inside `ImportCsv::handle()`'s outer per-chunk transaction — deliberately NOT scoped to just `saveRecord()`, because `afterCreate`/`afterSave` hooks (future two-pass item-linking) run outside `saveRecord()` in Filament's real `Importer::__invoke()` (verified against vendor source). A `QueryException` classified as deadlock/lock-wait (SQLSTATE `40001`, MySQL driver codes `1213`/`1205`) is RETHROWN untouched — letting it hit `ImportCsv`'s generic `catch (Throwable)` branch, which calls `report()` (loud, visible in logs, since MySQL already rolled back the WHOLE outer transaction and the failure is systemic, not row-specific). Any other `QueryException` is wrapped into a `RowImportFailedException` (quiet, clean per-row failure, no `report()` noise) instead.

**Mismatch-aware completion notification:** `getCompletedNotificationBody()` compares `$import->failedRows()->count()` (real rows in the downloadable CSV) against `$import->getFailedRowsCount()` (a DERIVED `total_rows - successful_rows` figure) and appends a distinct warning line when they diverge — some rows failed hard enough (e.g. a mid-chunk deadlock) that they never made it into the failed-rows table at all. The notification's SUCCESS/WARNING/DANGER color itself is decided by vendor's own `ImportAction::setUp()` `Bus::batch(...)->finally()` closure and is NOT re-derived from this mismatch (would require duplicating that closure wholesale) — the textual warning is the only signal for this specific case, which is more visible than a color change alone.

**Export runs as a queued job (`App\Jobs\ExportPurchaseRequests`), matching the import job pattern — completed 2026-09-22.** `PurchaseRequestExporter::stream(Builder): StreamedResponse` (a plain Filament class, HTTP-streamed, 1000-row cap) was replaced by `PurchaseRequestExporter::write(Builder, string $absolutePath): int`, writing to `storage/app/exports/{user}/{file}.csv` via `lazy()` (no row cap). `Traits/Table::getExportBulkAction(): BulkAction` dispatches `ExportPurchaseRequests::dispatch($records->pluck('id')->all(), auth()->id(), app()->getLocale())` and immediately shows an "export started" notification; the job itself sends a SECOND notification on completion (success: signed download link via `ExportDownloadController`, `auth`+`signed` middleware, ownership + filename-regex checked; failure: a generic error notification, real exception goes to `report()`). Because it's a `BulkAction` (selection-based, not the old plain `Action` that exported the whole filtered query), it lives inside `BulkActionGroup::make([...])` alongside `DeleteBulkAction`/`RestoreBulkAction` — a real UX change from before: the user must select rows first. **Gotcha hit live 2026-09-22: a long-running `queue:work` process caches class code in memory at boot and does not pick up file edits — mid-session iteration on the exporter/importer classes left a stale worker executing old code, producing a confusing "could not be completed" failure that a fresh `php artisan tinker` reproduction of the same job could not reproduce. Fix: `php artisan queue:restart` (or restart the dev-stack's queue process) after editing any class a queued job touches, before trusting a "broken" report from the running app.** Future development: (1) no retention/cleanup job for `storage/app/exports/*` yet — files accumulate indefinitely; (2) the job's `public array $ids` constructor arg materializes every matched PK into the queue payload at dispatch time — fine at Purchase Request's realistic volumes, but rebuilding the query from filter criteria inside `handle()` (instead of a pre-fetched ID list) would scale further for a genuinely unbounded export.

**Export is wired into every resource that references Purchase Request, not just its own list page — Import is NOT (removed 2026-09-22, see `app/Services/Imports/importsPattern.md`'s "Import is never wired into a RelationManager" section for the full reasoning).** `PurchaseRequestResource::getExportBulkAction()` is a public static method precisely so it can be reused; the `PurchaseRequestsRelationManager` under `PurchaseOrderResource`, `RegisteredOrderResource`, and `ProformaInvoiceResource` all call it directly (or, worse, wiring Filament's native `ExportBulkAction::make()->exporter(PurchaseRequestExporter::class)`, which no longer works now that the exporter is a plain class, not a Filament `Exporter`). Export stays in `toolbarActions()`'s `BulkActionGroup` everywhere, since it's row-selection-based. `PurchaseRequestResource::getImportAction()` is ONLY called from `ListPurchaseRequests::getHeaderActions()` (beside `CreateAction`) — it used to also sit beside `AttachAction::make()` in all 3 relation managers, but that wiring let the import silently update an unrelated PR elsewhere in the system with no scoping to the relation manager's owner record; removed rather than fixed, since scoping it properly is real new plumbing, not a quick change. Two of the three relation managers (`PurchaseOrderResource`, `ProformaInvoiceResource`) had no explicit `headerActions()` override before Import was first added and relied on Filament's implicit default `AttachAction` — adding an explicit `headerActions([...])` array REPLACES that default rather than merging with it, so `AttachAction::make()` had to stay explicit even after Import was removed (an empty/missing `headerActions()` override would silently delete the Attach button too).

**CSV export escapes formula-injection characters on free-text fields** (`PurchaseRequestExporter::escapeCsvFormula()`, applied inside `plainText()` for `notes`/`item_notes`) — a cell value starting with `=`, `+`, `-`, `@`, tab, or carriage-return gets a leading `'` prefixed (OWASP CSV-injection guard; Excel/Sheets hides the leading quote, visible text is unaffected). `notes` is user-authored rich text with no character restrictions, so this is a real, not theoretical, injection surface — found in review 2026-09-22.

### 1.8b Grouped single-file import/export — Purchase Request reference implementation

**`league/csv` 9.27+ deprecates `createFromStream()`/`createFromPath()` in favor of a unified `AbstractCsv::from($filename, $mode = 'r+', $context = null)`** — `$filename` accepts a resource stream directly (not just a path), so it's a drop-in replacement everywhere: `Writer::from($stream)`/`Reader::from($stream)`. Fixed 2026-09-22 at all three call sites in this codebase (`PurchaseRequestExporter`, `App\Filament\Actions\ImportAction`, `GroupedImportAction`) — grep for `createFromStream`/`createFromPath` before adding a new CSV read/write path.

**Prerequisite fix (observer suspension re-entrancy):** `CodeGeneratingObserver::duringImport()` and `NotificationDispatcher::suspended()` used to hardcode their flag back to `false` in a `finally` block. Under this nested design, the PARENT importer's own `DB::transaction()`/suspension wrapper is already active when `afterSave()` triggers the NESTED item importer's `__invoke()`, which goes through `ImportDefaults::__invoke()` AGAIN and re-enters `CodeGeneratingObserver::duringImport()`/`NotificationDispatcher::suspended()` a second time — the inner call's `finally` used to reset the flag to `false` unconditionally, silently lifting suspension for the REST of the outer call (remaining rows in the chunk would stop being suspended). Fixed by capturing and restoring the PREVIOUS flag value instead of hardcoding `false` (`$previous = static::$flag; static::$flag = true; try { ... } finally { static::$flag = $previous; }`) — both observers, same shape. Covered by `tests/Unit/ImportDefaultsTest.php`'s nested-suspension tests.

Replaces the earlier two-file design (separate parent/item importers and exporters, two Import buttons, two Export buttons). One file, one Import button, one Export button: a Purchase Request row is followed immediately by its item rows in the same CSV/XLSX, classified automatically on read.

**Classification rule (the single source of truth — `GroupedImportAction::groupRows()`):** a row is an ITEM row **iff its mapped discriminator cell (`product_id` for Purchase Request) is non-blank**; otherwise it is a PARENT row starting a new group. Product presence signals "this is an item," not the inverse — a parent row's product cell is always blank, since parents have no product field at all. Two rejection guards, each a distinct translated failure recorded directly against the `Import` (not queued into a job — classification happens synchronously in the action, before jobs are dispatched):
- An item row (product cell filled) appearing before any parent row in the file — orphan, no group to attach to (`resources/general/strings.import.orphan_item_row`).
- A row whose discriminator cell is blank but some OTHER item-only cell (`quantity`/`unit`/`estimated_cost`/`item_status_id`/`item_notes`) is filled — item data with no product, rejected rather than silently treated as a parent (`resources/general/strings.import.item_without_product`).

**Column merge (`PurchaseRequestImporter::getColumns()`):** returns the parent's own columns PLUS `PurchaseRequestItemImporter::getColumns()`, with `status_id`/`notes` renamed to `item_status_id`/`item_notes` at the `ImportColumn` level (`->name()`/`->label()`/`->guess()` all changed) so Filament's auto-mapper can never cross-match a parent column to an item column. `PurchaseRequestImporter::COLUMN_LABEL_KEYS` (public const) is the ONE shared source for every column's translated label — both `getColumns()`'s guess hints and `columnLabels(): array` (the exporter's header-row source) read from it, so there is exactly one place these labels are ever written. `beforeFill()` (a real `Importer` lifecycle hook, confirmed against vendor source — fires after `validateData()`, before `fillRecord()`) unsets every item-prefixed key from `$this->data` before the parent's own `fillRecord()` runs — belt-and-braces, since the classification guards above already guarantee a parent row's item cells are blank by the time it reaches the importer, and `ignoreBlankState()` alone would already skip blank values in `fillRecord()`.

**Nested item processing (`afterSave()`, confirmed INSIDE the savepoint):** vendor's `Importer::__invoke()` lifecycle is `remapData → castData → resolveRecord → validateData → fillRecord → saveRecord → afterSave → afterCreate/afterUpdate`, and `afterSave()` fires **after** `saveRecord()` but still inside the same call — which `ImportDefaults::runInTransaction()` wraps in `DB::transaction(fn () => parent::__invoke($data))`. That `DB::transaction()` call IS a real SAVEPOINT nested inside `ImportGroupedCsv`'s outer per-group loop transaction, so `afterSave()` (and everything it does) runs inside the savepoint — verified directly against `vendor/filament/actions/src/Imports/Importer.php` before trusting it, per the plan that specified this feature. `GroupedImportAction`/`ImportGroupedCsv` stash each group's buffered item rows on the parent importer via `forGroup(array $itemRows): static` (called before `($this->importer)($parentRow)`), and `afterSave()` reads that buffer and calls `importGroup()`, which processes each item row through ONE cached nested `PurchaseRequestItemImporter` instance (`itemImporter()`, memoized on the parent importer instance so relationship-lookup memoization on the ITEM importer's own `ImportColumn`s persists across every group in a chunk) — `forParent($parent): static` is called once per group (resets a per-group `seenProductIds` duplicate-tracking array AND sets the parent) before the item rows loop. `PurchaseRequestItemImporter::resolveRecord()` resolves the product itself (same manual `where`/`orWhere` lookup the old standalone item importer used), then checks `seenProductIds` and throws (`resources/general/strings.import.duplicate_product_in_group`) on a repeat — scoped to one group, not the whole import run, by the `forParent()` reset.

**Group-atomic failure semantics (`App\Jobs\ImportGroupedCsv extends Filament\Actions\Imports\Jobs\ImportCsv`):** overrides `handle()`/reproduces vendor's structure but iterates GROUPS instead of individual rows. Any item failure inside `importGroup()` is wrapped as `GroupRowFailedException extends RowImportFailedException` (carries `rowOffset`, the failing item's 0-indexed offset within the group's items array) and propagates up through the savepoint (rolling back the PARENT insert too — full group atomicity, parent and every item live or die together). `ImportGroupedCsv::handleGroups()` catches `GroupRowFailedException`/`RowImportFailedException`/`ValidationException`/generic `Throwable` around each group invocation and calls `logGroupFailure()`, which logs **every** physical row in the group via vendor's own `logFailedRow()` — the row that actually threw carries its real error message, every sibling (parent + every other item) carries `resources/general/strings.import.sibling_row_failed` naming the OFFENDING row's physical row number — so the whole group lands in the downloadable failed-rows CSV, keyed by the ORIGINAL CSV headers (byte-shape-identical to the source file, directly re-uploadable). Row accounting stays in PHYSICAL rows, not groups: `successful_rows`/`processed_rows` increment by `1 + count($group['items'])` per group, so `Import::getFailedRowsCount()`'s `total_rows - successful_rows` math (and the mismatch-aware notification above) stays meaningful.

**`App\Filament\Actions\GroupedImportAction extends App\Filament\Actions\ImportAction`:** duplicates vendor `Filament\Actions\ImportAction`'s action-closure body (the file-read → `Import` row creation → job-dispatch pipeline in `setUp()`'s `$this->action(...)`) rather than extending it, because the grouping step has to sit BETWEEN "rows read from the file" and "rows chunked into jobs" — there is no vendor hook at that seam. A future Filament upgrade that changes `ImportAction::setUp()`'s action closure needs this class re-diffed against vendor by hand. `groupRows()`/`chunkGroups()` are the two seams that differ from vendor: `chunkGroups()` greedily bins groups into ~100-physical-row chunks and **never splits a group** — a group with 100+ items becomes its own oversized chunk rather than being sub-chunked, a deliberate, documented trade-off. `itemDiscriminatorColumn(string): static` / `itemOnlyColumns(array): static` are the two config points a future grouped importer (not just Purchase Request) would set at the wiring site, keeping `GroupedImportAction` itself resource-agnostic.

**Proforma Invoice is the second grouped single-file consumer, added 2026-09-22 — confirms the pattern generalizes.** `ProformaInvoiceImporter`/`ProformaInvoiceItemImporter`/`ProformaInvoiceExporter`/`App\Jobs\ExportProformaInvoices` mirror the Purchase Request files 1:1 (same `ITEM_COLUMN_MAP`/`COLUMN_LABEL_KEYS`/`beforeFill()`/`afterFill()`/`afterSave()`/`childImporterInstance()` shape via the shared `app/Services/Imports/` architecture — see §1.8a/`importsPattern.md`), discriminator column `product_id`, item-only columns `origin`/`hs_code`/`unit`/`quantity`/`unit_price`/`net_weight`/`gross_weight`/`item_freight_charges`/`item_total_amount`/`description`. Two PI-specific column collisions needed the `ITEM_COLUMN_MAP` rename this time: the ITEM's own `freight_charges`/`total_amount` columns collide with the PARENT's own `freight_charges`/`total_amount` columns (Purchase Request only had one such collision pair, `status_id`/`notes`) — renamed to `item_freight_charges`/`item_total_amount` at the `ImportColumn` level, same mechanism. **`GroupRowFailedException` now lives at `App\Services\Imports\GroupRowFailedException` — a shared location, not a PurchaseRequest-namespaced one.** (Corrected 2026-09-22: it used to sit under `PurchaseRequestResource\Imports\` specifically because `App\Jobs\ImportGroupedCsv` hardcoded that exact class in a `catch`; that coupling was a concrete example of the "haphazard, per-module" design the shared-pipeline rewrite exists to remove. Both `ImportGroupedCsv` and both modules' importers now import the shared class — a third grouped importer does the same, no new promotion needed.) Required-column handling differs per real schema (verified via `SHOW COLUMNS`, not assumed): `invoice_no` mirrors `pr_number`'s reserved-number auto-fill; `invoice_date` auto-fills to `now()` on a blank new row (mirroring the create form's own `->default('today')`, unlike PR which has no equivalent date default); `main_currency_id` has no context-derivable default (unlike PR's requester→department chain) and is rejected outright via a `match()`-plus-null-fallback-plus-reject `ImportColumnDefinition`; the item importer's `unit` column (NOT NULL in `proforma_invoice_items`, unlike PR's nullable `PurchaseRequestItem.unit`) gets the same reject treatment, since `resolveRecord()` is fully overridden and never reaches `ImportDefaults`'s own `requiredColumnsForNewRecords()` hook.

**Registered Order is the third grouped single-file consumer, added 2026-09-25.** `RegisteredOrderImporter`/`RegisteredOrderItemImporter`/`RegisteredOrderExporter`/`App\Jobs\ExportRegisteredOrders` mirror the same shape, discriminator column `product_id`, item-only columns `quantity`/`unit`/`unit_price`/`net_weight`/`gross_weight`/`entrance_fee`/`shipping_cost`/`extra_cost`/`packing_details`/`description`. **`ITEM_COLUMN_MAP` is genuinely empty for this module** — unlike PR (`status_id`/`notes`) and PI (`freight_charges`/`total_amount`), none of Registered Order's own parent columns collide with its item columns' names, so the merge in `getColumns()` needs no renaming; the (still-present, for shape-consistency) `array_search(..., self::ITEM_COLUMN_MAP, true)` simply always misses. `status_id` is required (NOT NULL, unlike PR's nullable one) and strict (`matchStatus()`, no `allowNullOnMismatch()`) with a `withFallback()` to `Status::findBy(RegisteredOrder::TYPE_REGISTERED_ORDER, 'Submitted')` — the same default the create FORM's own `getStatusField()` already uses for a brand-new record. `ro_number` (`SCANNABLE_IDENTIFIER`) and `contract_no` both fall back via two independent `fillReservedNumberIfBlank()` calls in `beforeCreate()` (`CodeGenerator::$map` already carries both, prefixes `RO`/`CT`). The item's `line_total` is never an importable column (auto-computed, like PI never imports its own item total) — `RegisteredOrderItemImporter` composes the FORM's own `ItemCalculation` trait directly and calls `computeItemLineTotalFromState()` (the exact same `round((quantity*unit_price)+shipping+extra, 5)` formula the live form preview uses) from its own `afterFill()`, after `ImportPipeline::runBeforeSave()` has resolved the row's other columns but before `saveRecord()` persists it.

**Pivot-attach columns (`pr_numbers`/`invoice_nos`/`po_numbers`) — a new, deliberately generic concept, not Registered-Order-specific plumbing.** Registered Order's `purchaseRequests()`/`proformaInvoices()`/`purchaseOrders()` are `belongsToMany` pivots (`app/Models/Traits/RegisteredOrder/Relationships.php`), not real `registered_orders` columns — so these three columns are built as plain `Filament\Actions\Imports\ImportColumn`s directly in `RegisteredOrderImporter::pivotColumns()` (same idiom as `ImportDefaultsShared::extraAttributeColumns()`'s `extra_key_N`/`extra_value_N` pairs: `ignoreBlankState()` + a trimming `castStateUsing()`, no `ImportColumnDefinition`/`ImportColumnFactory` involvement, since those would try to `data_set()` a non-existent model attribute), read out of `$this->data` in `beforeFill()` (captured into `pendingPivotAttach` BEFORE the column is `unset()`, mirroring the item-column-stripping idiom), and processed by a new, genuinely module-agnostic pipeline stage — `App\Services\Imports\Stages\AttachPivotRelations`, registered in `ImportPipeline::AFTER_SAVE_STAGES` (after `SyncEavAttributes`, before `RunModuleRecalculation`) and driven purely by `ImportRowContext::$pendingPivotAttaches` (a new public array property, empty/no-op by default — PR and PI never populate it, so the stage is inert for both). Each entry is `{relation, model, identifierColumn, label, values}`; for each comma-separated raw value, a blank-tolerant, additive `syncWithoutDetaching()` attaches every identifier that resolves against `{model}::query()->where(identifierColumn, value)->first()` — never detaching a pivot row the CSV doesn't mention — and any identifier that doesn't resolve gets appended to the record's own `notes` via the SAME `resources/general/strings.import.unresolved_match_left_blank` note the column-mismatch stage (`AppendUnresolvedMatchNotes`) uses, not a bespoke message, followed by an explicit `$record->save()` (this stage runs post-save, so mutating `notes` needs its own persist — the pre-save `AppendUnresolvedMatchNotes` stage doesn't, since `saveRecord()` hasn't run yet at that point). A future module with its own `belongsToMany` columns to expose through import reuses this stage for free — populate `pendingPivotAttaches` the same way, no stage changes needed.

**`assertColumnNamesAllowed()`'s denylist had a real false positive, fixed as part of this module.** See §1.8a above — `currency_type` (a genuine `$fillable` column) used to be blocked by a blanket `*_type` suffix ban meant for polymorphic morph discriminators; fixed by naming the actual four morph-type columns explicitly instead of guessing from the suffix.

**Bank Profile is the first FLAT (non-grouped) import/export consumer, added 2026-09-26 — it has no item rows at all**, so `BankProfileImporter` skips the entire grouping machinery this section documents (no `GroupedImportAction`, no `ImportGroupedCsv`, no item importer) and wires the plain `App\Filament\Actions\ImportAction` + `App\Jobs\ImportCsv` instead. Full column plan, exporter shape, and a shared-infra lang-key gap this module surfaced and fixed (`resources/general/strings.php` had no `import` key at all, affecting PR/PI/RO too) are documented in `app/Services/Imports/importsPattern.md`'s own "Bank Profile" section — not duplicated here.

**Rewiring `ExportBulkAction::make()->exporter(XxxExporter::class)` to a plain-class exporter breaks every OTHER call site too, not just the owning resource's own `table()`.** Converting `ProformaInvoiceExporter` from a Filament-native `Exporter` to a plain `write(Builder, string): int` class (matching `PurchaseRequestExporter`'s shape) meant the three sibling RelationManagers that show Proforma Invoice rows inside a different resource's page — `PurchaseRequestResource`/`PurchaseOrderResource`/`RegisteredOrderResource`'s own `ProformaInvoicesRelationManager` — could no longer call `ExportBulkAction::make()->exporter(ProformaInvoiceExporter::class)` (that native Filament action requires a real `Exporter` subclass). All three were repointed to `ProformaInvoiceResource::getExportBulkAction()` (the same `Traits/Table.php`-defined `BulkAction` the owning resource itself uses), and each needed `HasExtraAttributesManagement` added to its own `use` list since `ProformaInvoiceTable::showInvoiceNo()`'s EAV-search wiring (`static::orWhereExtraAttributesMatch(...)`) is a method that trait supplies — a RelationManager `use`-ing a sibling resource's `Table`/`Filters` traits inherits every static-method dependency those traits call via `static::`, not just the columns it explicitly invokes. Check this whenever converting ANY exporter from the native Filament shape to the plain-class shape — grep for `->exporter({Name}Exporter::class)` project-wide, not just inside the owning resource's own file. **Registered Order's own conversion (2026-09-25) had a wider blast radius — SEVEN sibling RelationManagers** (`ProformaInvoiceResource`, `PurchaseOrderResource`, `PurchaseRequestResource`, `ShipmentResource`, `PaymentResource`, `CustomResource`, `BankProfileResource`, each showing Registered Order rows inside their own page) all called `ExportBulkAction::make()->exporter(RegisteredOrderExporter::class)` and all needed the same repoint-to-`RegisteredOrderResource::getExportBulkAction()` + add-`HasExtraAttributesManagement` treatment (every one of them calls `RegisteredOrderTable::showRoNumber()`, which needs `orWhereExtraAttributesMatch()`).

**Export value-vocabulary contract (`PurchaseRequestExporter::stream(Builder): StreamedResponse`, plain class, NOT a Filament `Exporter`** — Filament's native `Exporter` is one-row-per-model and cannot produce this shape either):
- **Dates → Jalali**, via `jdate($date)->format('Y-m-d')` (confirmed 2026-09-22 user decision, matching the app's Persian-calendar convention) — NOT `toPersianDate()`/`adaptiveDate()` (those produce a human `d F Y` display string, not the numeric `Y-m-d` the import's `dateColumn()` parser expects). `jdate(...)->format('Y-m-d')` emits plain ASCII digits (verified against `Morilog\Jalali\CalendarUtils::date()` — no Persian-glyph conversion happens in that code path), matching `dateColumn()`'s `jalali=true, date_format='Y-m-d'` parser exactly — and the import options form already defaults `jalali` from `isJalaliCalendar()`, so an export-then-reimport in the same session round-trips with zero manual option changes.
- **Numbers → raw `(string) $value`**, NEVER `preciseNumber()` — it inserts thousands separators and renders `null` as the literal string `'-'`, both of which `numberColumn()`'s strict `^-?\d+(\.\d+)?$` regex rejects outright. A `decimal:5`-cast model attribute already stringifies with trailing zeros (e.g. `'1234.50000'`) — that is a valid, re-importable number, don't trim it.
- **Lookups → the model's `getLocalizedNameAttribute()`** (requester stays `email`, has no localized name) — department/cost-center/product/status all render in the ACTIVE locale (`name` for `fa`, `english_name` otherwise), fixed 2026-09-22 after a user-reported bug: exporting while in `fa` showed English status/department text. The re-import side (`ImportDefaults::statusColumn()`'s `castStateUsing`, and `lookupColumn()`'s `resolveColumns` arrays) matches against BOTH `name` and `english_name` for every one of these columns, so a Persian-locale export still re-imports with zero edits — `statusColumn()` used to match `Status::findBy()` (english-only) and would have silently rejected a re-uploaded Persian status value; don't regress it back to english-only matching.
- **Urgency/unit → the raw lowercase DB value** (already the same key the form `Select` options and `numberColumn`'s sibling `urgencyColumn()`/`unitColumn()` allow-lists use) — no translation round-trip.
- **Notes → HTML-stripped plain text** (`PurchaseRequestExporter::plainText()`: `strip_tags` + `html_entity_decode` + `trim`) — the parent `notes` field is a `RichEditor` (stores real HTML, an empty one round-trips as literal `<p></p>`/`<p> </p>`), fixed 2026-09-22 after a user-reported bug (raw HTML tags leaking into the exported cell). Re-import writes the plain-text result back into the same `RichEditor`-backed column; Filament renders plain text inside it fine, no HTML re-authoring needed.
- **Null/blank → an empty cell, never the literal word `"null"`** (the EAV `json_encode(null)` bug class from earlier work — don't reintroduce it here).
- **CSV starts with a UTF-8 BOM** (`"\xEF\xBB\xBF"` written before the `League\Csv\Writer` output) — without it, Excel on Windows misdetects the encoding and renders Persian text as mojibake; every other consumer (re-import, `str_getcsv`) ignores/must strip the BOM.
- One parent row (all parent columns filled, every item-prefixed column blank) followed by one row per item (only item-prefixed columns filled, every parent column blank) — mirrors `groupRows()`'s classification rule exactly, so a freshly exported file re-imports with zero edits.

### 1.9 EAV system — dual entry points to ONE `morphMany`

This is a BMS-CM hallmark. The model trait `App\Models\Traits\General\HasCustomAttributes` declares TWO independent methods, both returning `morphMany(EntityAttribute::class, 'entity')`:

```php
public function customAttributes()
public function extraAttributes()
```

Same morph map (`entity_type` / `entity_id`), same rows. This double-declaration (NOT `->as()`) is **intentional** — it prevents closure conflicts between the two consumers. `getCustomAttributesMap(): array` returns `pluck('value','key')`, JSON-encoding non-string values.

`EntityAttribute` model:

```php
protected $fillable = ['entity_type','entity_id','key','value','user_id','updated_by_id'];
protected $casts    = ['value' => 'json'];   // the value column is JSON-cast
```

Two coexisting entry points:

1. **`ManageCustomAttributesAction::make(): Action`** (`App\Filament\Actions`) — `KeyValue::make('attributes')` modal. `fillForm` from `$record->getCustomAttributesMap()`. Syncs via `$record->syncCustomAttributes($data['attributes'] ?? [])`. Operates on `customAttributes()`.
2. **`HasExtraAttributesManagement` Repeater** — inline form tab bound to `extraAttributes()` via `->relationship()`, save side overridden by `syncCustomAttributes()` too (see §1.6).

Both write to the same rows via `syncCustomAttributes()` (see §1.6, `modelsPattern.md` §5); the alias separation prevents closure binding conflicts. Do not collapse them into one, and do not reintroduce a direct `updateOrCreate`/bare `create()` write path.

### 1.10 Virtual-tab `->dehydrated(false)` EAV pattern (InvoiceForm)

The canonical pattern for any EAV-backed form tab. Verified in `ShipmentInvoiceForm`:

- The whole invoice tab (`getInvoiceFormTab()`) is built from `_inv_*` fields that ALL carry `->dehydrated(false)`. None touch the Shipment Eloquent model on save — they are scratch UI state.
- Persistence is via **page-level `getFormActions()`** (`EditShipment`), NOT a schema-level `Section::footerActions()` — a `Get $get` closure only exists inside a bound Schema, and a schema-embedded footer action buried the buttons away from the record's own Save/Cancel row (root cause of a real "selection doesn't persist" client report: users clicked the normal Save button, which silently discards every `dehydrated(false)` field). `EditShipment::getFormActions()` returns `[saveInvoice, printInvoice, resetInvoice, $this->getSaveFormAction(), $this->getCancelFormAction()]` — the three CI actions precede Save/Cancel for visual accent. All three read `$this->data` (the page's public raw form-state array, bound via `->statePath('data')` — holds `dehydrated(false)` fields; `Schema::getState()` would NOT, since it calls `dehydrateState()`) instead of a schema `Get`, and are `->disabled(fn () => blank($this->data['_inv_pi_id'] ?? null))` until a Proforma Invoice is actually selected.
- `saveInvoice`/`printInvoice` call `ShipmentResource::persistInvoiceToEav($this->data, $record)` — note the call site is the CLASS that `use`s the trait (`ShipmentResource`, via the `ShipmentInvoiceForm` alias), not the trait name itself. `printInvoice` persists first, then opens the PDF via `$this->js("window.open(...)")` inside the SAME action closure — never `->url()->openUrlInNewTab()` on an action that also has `->action()`, since Filament renders both as one `<a href>` and the browser follows the href synchronously while the `wire:click` AJAX save is async, racing the PDF route against the save.
- `resetInvoice` calls `$this->form->fill($this->hydrateInvoiceData($this->data))` — `hydrateInvoiceData()` is a page method shared with `mutateFormDataBeforeFill()` (extracted so both the initial page load and the manual reset use identical EAV→`_inv_*` mapping, including explicitly nulling/zeroing every `_inv_*` key when no EAV row exists yet, so a reset with nothing saved actually clears the tab instead of leaving stale in-progress values).
- `persistInvoiceToEav(array $formData, $record)` packs all `_inv_*` state into a structured array and upserts a SINGLE `EntityAttribute` row keyed `->where('key', 'commercial_invoice')`.
- Hydration lives in the Edit page's `mutateFormDataBeforeFill`, NOT in the form.

**Recipe for any new EAV-backed form tab:** `->dehydrated(false)` on every field + an explicit save action living in the Edit page's `getFormActions()` (reading `$this->data`, never a schema-level `footerActions()`/`Get`) beside the record's own Save/Cancel + page-mutator hydration. Do not mix `->dehydrated(true)` EAV fields with the model's own columns in the same tab. Do not bury a virtual tab's save action in a schema-level footer action separate from the record's own Save button — it's easy to miss and silently discards the tab's data.

### 1.11 Model traits (`app/Models/Traits/General/`)

| Trait | Effect |
|---|---|
| `Relationships` | `creator(): BelongsTo` (User, `user_id`) + `updater(): BelongsTo` (User, `updated_by_id`) |
| `UserStamps` | boots `static::creating` → `user_id = auth()->id()`; `static::updating` → `updated_by_id = auth()->id()` (only when `isDirty()` and `auth()->check()`) |
| `HasCustomAttributes` | EAV `morphMany` double-alias (`customAttributes()` + `extraAttributes()`) + `getCustomAttributesMap()` |
| `Localization` | `getLocalizedNameAttribute(): string` returns `$this->{$this->localeColumn()}`; `localeColumn()` → `'name'` when locale `fa`, else `'english_name'` |
| `HasScope` | `scopeActive($query)` → `where('is_active', true)` |
| `SellerEntity` | three `belongsTo(Company::class, 'seller_id')` variants scoped by company type + `is_active=1`: `manufacturerCompanyExclusive()` / `sellerCompanyExclusive()` / `supplierCompanyExclusive()` — filtered views of the same relation |

Models with `SoftDeletes` require `->withoutGlobalScopes([SoftDeletingScope::class])` in the resource's `getEloquentQuery()`.

### 1.12 Status model + `StatusFinder`

`Status` is a shared polymorphic lookup: columns `type` / `english_type` + `name` / `english_name`.

```php
// App\Models\Traits\Status\StatusFinder
public static function findBy(string $type, ?string $name = null): static|Collection|null
// with $name: where('english_type', $type)->where('english_name', $name)->first()
// without $name: returns full Collection for that type
```

`TYPE_*` constants live on the OWNING models (NOT on `Status`), e.g.:

- `PurchaseRequest::TYPE_PURCHASE_REQUEST = 'Purchase Request Status'`
- `Shipment::TYPE_SHIPMENT_STATUS`, `TYPE_CONTAINER_STATUS`, `TYPE_OPERATION_STATUS`, `TYPE_TRACKING_STATUS`, `TYPE_DOC_STATUS`
- `Payment::TYPE_PAYMENT`
- `Custom::TYPE_CLEARANCE_STATUS`
- `RegisteredOrder::TYPE_REGISTERED_ORDER`
- `BankProfile::TYPE_BANK_PROFILE`
- `Correspondence::TYPE_CORRESPONDENCE_STATUS`

The constant value is passed as `$type` to `Status::findBy(...)`, matched against the `english_type` column. Never hardcode status strings in resource code — always go through `Status::findBy(Model::TYPE_X, 'SomeStatus')`.

### 1.12a `StatusResource` Approval Workflow tab — dynamic per-row grant permissions

The one Master resource with a `Tabs::make('Status')` form (`tab_general` + `tab_approval_workflow`) — an exception to §1.18's "Master form = no Tabs" row, scoped to `StatusResource` only.

`Traits/Form.php` adds three fields, none of them `Status::$fillable` except `stage_order`: `getStageOrderField()` (`stage_order`, nullable numeric — position within the row's `english_type`), `getRequiresApprovalField()` (`requires_approval`, form-only `Toggle`, `->live()`, hydrated from `filled($record?->approval_permission)`), `getApprovalUsersField()` (`approval_users`, form-only multi-`Select` of `User`, visible only when `requires_approval` is on, hydrated from the current `approval_permission`'s grantees).

`processApprovalWorkflow(array $data, ?Status $record): array` — called from both `StatusResource::table()`'s `EditAction::mutateDataUsing()` and `ManageStatuses::getHeaderActions()`'s `CreateAction::mutateDataUsing()` — writes `approval_permission` as a Spatie `Permission` named `status.grant_{snake(english_type)}_{snake(english_name)}`, deduped with a `_2`/`_3`… suffix checked against **every** existing `Permission` row (not just ones other `Status` rows own), and stashes the pre-mutation value into `previous_approval_permission` (dropped by mass assignment, read only by the `after()` step below) so a later gate-off or rename can find the permission it's replacing.

`syncApprovalUsers(array $data): void` — called from the same two actions' `after()` (fires post-save, so `$record` is always persisted by then) — does `Permission::where('name', ...)->first()?->users()->sync($ids)`. `Permission::users()` (`app/Models/Permission.php`) is a `morphedByMany(User::class, 'model', ...)` scoped to that one `Permission` row's id, so `sync()` can only ever touch that permission's own grantees, never another Status row's. This is a separate mechanism from `HasResourcePermissions`'s static `{prefix}.{action}` CRUD permissions — these `status.grant_*` permissions are dynamically generated per-`Status`-row approval gates, unrelated to view/create/edit/delete.

### 1.12b `HasStatusWorkflow` — the reusable status-workflow-gating trait (PurchaseRequest is the first consumer, Purchase Order the second, Payment the third, Registered Order the fourth, Bank Profile the fifth, Correspondence the sixth, Shipment (5 columns) the seventh, Custom (3 columns) the eighth)

`App\Filament\Traits\HasStatusWorkflow` (mirrors `TracksStatusHistory`'s override-point shape) extracts the whole gating mechanism out of PurchaseRequest's own files into something any resource can compose. A composing resource declares `statusWorkflowType(): string` (its `Status::english_type`, no default — every consumer must define it; a resource gating several independently-typed columns can instead pass `$type` explicitly to every call below and leave `statusWorkflowType()` as a documentation-convention default for its primary column) and, only for multi-status-column models, overrides `statusWorkflowColumns(): array` (defaults to `['status_id']`; Shipment's override returns `Shipment::statusHistoryColumns()`, mirroring `TracksStatusHistory::statusHistoryColumns()` exactly, as designed).

**Architectural gap, opened 2026-09-26 while wiring Shipment (5 columns), resolved the same day.** `statusWorkflowType()` was originally single-type-per-resource with no column parameter anywhere in the trait's call chain, so a resource with several independently-typed status columns could not compose the trait's `Select`-building methods at all. Fixed by threading an optional `?string $type = null` parameter through `getStatusWorkflowField()`, `applyInitialStatusOnCreate()`, and `availableStatusWorkflowIds()` — each falls back to `static::statusWorkflowType()` when `$type` is omitted, so every pre-existing single-type consumer (PurchaseRequest/PurchaseOrder/Payment/RegisteredOrder/BankProfile/Correspondence) needed zero changes. A second, related gap surfaced at the same time: the trait's create-time `disabled()`+auto-default mechanism assumed `StatusWorkflow::initialFor($type)` always resolves (true only once a type has real `stage_order` rows) — for a NOT-NULL status column on an as-yet-ungated type, the field would be disabled-and-un-dehydrated on create with no fallback value, breaking every create against that column with a null-insert on a NOT NULL FK. Fixed with a 4th `?Closure $fallbackDefault = null` parameter on `getStatusWorkflowField()`: the Select is now only `disabled()` on `create` when `StatusWorkflow::initialFor($type)` actually resolves; otherwise it stays exactly as it was pre-wiring, and `$fallbackDefault` (when given) supplies the column's pre-existing create-time default so a NOT-NULL column never goes null while ungated. `statusWorkflowPipelineLines()`/`getStatusWorkflowPipelineHint()` were NOT extended with a `$column`/`$type` parameter in this pass — they remain single-column-only (`statusWorkflowColumns()[0]`) — so no multi-type consumer wires the pipeline hint yet; that stays a follow-up.

Public API:
- `getStatusWorkflowField(string $column, ?Closure $extra = null, ?string $type = null, ?Closure $fallbackDefault = null): Select` — builds the Select bound to `$column`'s relationship (name derived via `Str::camel(Str::beforeLast($column, '_id'))`, e.g. `container_status_id` → `containerStatus`; a consumer whose relation name doesn't follow that derivation, e.g. Shipment's `shipment_status_id` → `trackingStatus`, overrides the trait's `protected static function statusWorkflowRelation(string $column): string` directly on the resource class — see Shipment's paragraph below), scoped via a generic current+next+unordered `availableStatusWorkflowIds()`, `disableOptionWhen()` for locked options, and a `helperText()`. `$type` lets one resource gate several independently-typed columns without redeclaring `statusWorkflowType()` per column (falls back to it when omitted). `$fallbackDefault` supplies the pre-existing create-time default value for a column whose type isn't gated yet (used only when `StatusWorkflow::initialFor($type)` is null) — see the architectural-gap note above. `$extra` lets a resource chain its own `->label()`/`->validationMessages()`/`->validationAttribute()` without losing the shared mechanics — PurchaseRequestResource's own `getStatusIdField()` is now a 6-line wrapper around this.
- `applyInitialStatusOnCreate(array $data, string $column = 'status_id', ?string $type = null): array` — sets `$data[$column]` to `StatusWorkflow::initialFor($type ?? statusWorkflowType())?->id` when the type has an ordered initial status; otherwise leaves `$data` untouched (ungated types are a no-op). Called from `CreatePurchaseRequest::mutateFormDataBeforeCreate()`.
- `assertStatusTransitionAllowed(?Model $record, string $column, $newStatusId): void` — resolves `$record?->{relation}` as current and `Status::find($newStatusId)` as target, then calls `StatusWorkflow::assertAllowed($user, $target, $current, $column)` — the real server-side trust boundary. `StatusWorkflow::assertAllowed()` gained an optional trailing `string $column = 'status_id'` parameter (backward compatible) purely so the thrown `ValidationException`'s error key matches whichever column a multi-column resource is gating, not always `status_id`. No `$type` parameter is needed here — the column→relation derivation already resolves the correct current `Status` regardless of type.

**`getStatusWorkflowProgressColumn()` — a per-record progress badge, added 2026-09-26 to all 8 consumers' tables.** `getStatusWorkflowProgressColumn(string $column = 'status_id', ?string $type = null, bool $toggledHiddenByDefault = false): TextColumn` renders a badge (`"{percent}%"`, tooltip `"{completed}/{total}"`) computed from the record's position among `StatusWorkflow::orderedStatuses($type)` — reuses that method's existing `SmartCacheManager` cache (one query per type per table render, not per row; `orderedStatuses()` was widened from `private` to `public` for this). Returns `null` (renders `—`, gray) when the type has no ordered stages or the record has no current/unordered status. `$toggledHiddenByDefault` is forced `true` automatically whenever `StatusWorkflow::initialFor($type)` is null — an ungated module's column stays hidden-by-default (avoids a column full of dashes) and only defaults to visible once an admin actually configures that type's pipeline. Column placement convention: immediately before the resource's trailing relation-count column if one exists (e.g. `showProformaInvoiceCount()`), else last. Multi-column resources (Shipment, Custom) get one call per column, each with its own explicit `$type`; only the primary column omits `toggledHiddenByDefault: true` (still subject to the auto-hide-when-ungated rule above). The pipeline legend's `▶` "current stage" icon was removed as redundant now that this badge exists — `statusWorkflowStageIcon()` now returns `✅` for any stage at or before the current one, `''` for the initial stage on a not-yet-created record, `🔒` otherwise.

**The locked-helper-text mechanism is now genuinely generic, not a per-status `match` statement.** `statusWorkflowLockedHelperText()` resolves the *actual* users granted the next status's `approval_permission` (`Permission::where('name', ...)->first()?->users->pluck('name')`, the same relation shape as `StatusResource/Traits/Form.php::syncApprovalUsers()`) and renders `status_workflow.locked_helper_with_names` (`:status`/`:names` placeholders) when there are 1–3 named approvers, or falls back to the generic `status_workflow.locked_helper` (`:status` only) when there are none or more than 3. This **replaced** Purchase Request's old hardcoded `approvalPositionLabel()` match (`'Sales Manager Approval'`/`'Authorized'` → `resources/purchaseRequest/strings.general.position_*`) and the `locked_helper_with_position` lang key — both deleted; naming the real grantees is automatic for any future consumer with zero per-resource wiring.

`HandleStatusMutation` (PurchaseRequest's own page-level trait) now takes the pre-mutation `?Model $record` (not a bare status-id int) and calls `PurchaseRequestResource::assertStatusTransitionAllowed($record, 'status_id', $newStatusId)` before its existing approver-stamp logic — the disabled Select options remain UX only. `app/Filament/Actions/{ReturnForRevisionAction,ResubmitAction}.php` are the two moves exempt from the one-step-forward rule (back-to-stage-1 resets, gated by `->authorize()` closures, not just `->visible()`) — both already took a generic `make(string $typeConstant)` with no PurchaseRequest-specific hardcoding, so they needed no changes for this extraction.

**Correspondence, confirmed 2026-09-26 — wiring an *ungated-today* type onto the trait needs a `$fallbackDefault`, or the create-time value silently disappears.** `Correspondence Status` (`Correspondence::TYPE_CORRESPONDENCE_STATUS`) currently has zero rows with `stage_order`/`approval_permission` set, so `StatusWorkflow::initialFor()` returns null and `applyInitialStatusOnCreate()` is a genuine no-op — the option list stays the full unscoped set (`availableStatusWorkflowIds()`'s unordered-statuses branch already returns everything when nothing has a `stage_order`). `getStatusWorkflowField()` now only `disabled()`s (and hence un-dehydrates, per Filament's `disabled()`⇒`dehydrated()` coupling, `vendor/filament/schemas/src/Components/Concerns/CanBeDisabled.php`) the Select on `create` when `StatusWorkflow::initialFor($type)` actually resolves — so an ungated type like Correspondence Status stays enabled and dehydrated by default — but without a fallback, its create-time `->default()` would still resolve to `null` against a `NOT NULL` column. `CorrespondenceResource::getStatusField()` (`Operational/CorrespondenceResource/Traits/Form.php`) fixes this by passing `fn () => Status::findBy($type, 'Submitted')?->id` as `getStatusWorkflowField()`'s `$fallbackDefault` argument — the pre-extraction hardcoded `'Submitted'` default, now supplied declaratively instead of via a local `$extra` override (removed 2026-09-26 once the trait absorbed this gating itself), automatically superseded by the real ordered initial status the moment an admin configures `stage_order` for this type, zero further code changes needed. Any future consumer wiring an ungated-today type only needs to pass its own pre-existing hardcoded default (if any) as `$fallbackDefault` — no `$extra` override is required for this at all; the bare 6-line wrapper is now equally safe for a genuinely-gated type (PurchaseRequest) and an ungated one (PO/RO/BankProfile/Correspondence, or Payment's own pre-existing targetable-gated condition — see their own paragraphs below). `CreateCorrespondence`/`EditCorrespondence` call `applyInitialStatusOnCreate()`/`assertStatusTransitionAllowed()` inline (no separate `HandleStatusMutation` trait — Correspondence has no approver-stamp/rejection-reason side effects to bundle alongside the assertion, unlike PurchaseRequest's). `ReturnForRevisionAction`/`ResubmitAction` were deliberately NOT added to `EditCorrespondence` — `ResubmitAction`'s `->authorize()` keys off literal status names (`'Conditional'`/`'Declined'`) that don't exist in Correspondence's real status set, and `ReturnForRevisionAction`'s keys off `$record->status->approval_permission`, unset for every real Correspondence status today — wiring either now can't be verified as an inert no-op without a stage_order rollout decision, so both are left for that follow-up stage. `getStatusWorkflowPipelineHint()` WAS added in a later pass (2026-09-26, see the dedicated paragraph above).

**PurchaseOrder wiring (2nd consumer) — safe no-op today, no `HandleStatusMutation` trait needed.** `PurchaseOrder`'s `Status` type (`TYPE_PURCHASE_ORDER`) currently has no admin-configured `stage_order`/`approval_permission` on any row, so `StatusWorkflow::initialFor()` returns `null` and `getStatusField()`'s option list stays fully unrestricted — the wiring only activates once an admin configures the pipeline, exactly like `PurchaseRequest` did before its own `Status` rows were configured. `CreatePurchaseOrder`/`EditPurchaseOrder` call `PurchaseOrderResource::assertStatusTransitionAllowed()`/`applyInitialStatusOnCreate()` directly in their own `mutateFormDataBeforeCreate()`/`mutateFormDataBeforeSave()` — no page-level `HandleStatusMutation` trait, since PO has no approver/rejection-reason side-fields to stamp (unlike PR's version, which also touches `approver_id`/`approval_date`/`rejection_reason`). **`getStatusField()` passes `fn () => Status::findBy(TYPE, 'Submitted')?->id` as `getStatusWorkflowField()`'s `$fallbackDefault` argument** — `getStatusWorkflowField()` now only disables (and un-dehydrates, per Filament's `disabled()` semantics) the Select on `create` when `StatusWorkflow::initialFor($type)` actually resolves, otherwise it stays enabled and applies `$fallbackDefault()` as the create-time default; a no-op change for PR (its type always has a configured initial status) but essential for PO today, since `applyInitialStatusOnCreate()` is a no-op there and an unconditionally-disabled field would submit no `status_id` at all against a `NOT NULL` FK column, breaking every Purchase Order creation. This replaces the local `$extra`-based `->disabled(false)` override this paragraph used to describe (removed 2026-09-26 once the trait absorbed the fallback-aware gating itself); `applyInitialStatusOnCreate()` will still correctly force-overwrite the user's picked value once PO's `Status` type is eventually gated, so no future rework is needed. `ReturnForRevisionAction`/`ResubmitAction` were NOT added for PO in this pass — both actions' `authorize()` closures don't depend on `stage_order` at all (`ResubmitAction` keys off literal `english_name`s `Conditional`/`Declined`), so wiring them today risks surfacing new header actions against live, uncontrolled dev-DB `Status` data — deferred to whichever future stage actually configures PO's pipeline. `getStatusWorkflowPipelineHint()` WAS added in a later pass (2026-09-26, see the dedicated paragraph above) — collapsed by default, so it adds no visible UI until a user opts in.

**Custom (3 columns) — gating logic replicated inline, but composes `HasStatusWorkflow` (added 2026-09-26) solely for `getStatusWorkflowProgressColumn()`.** `statusWorkflowType()` returns `Custom::TYPE_CLEARANCE_STATUS` as a documentation-convention default only — every progress-column call site passes its own `$type` explicitly, same as Shipment. The field-building/mutation-gating mechanics below remain fully inline (not routed through the trait), safe no-op today. `Custom::statusHistoryColumns()` (`TracksStatusHistory`) already lists the 3 real columns — `clearance_status_id`/`bank_guarantee_status_id`/`commitment_status_id`, each its own `Status::english_type` (`TYPE_CLEARANCE_STATUS`/`TYPE_BANK_GUARANTEE_STATUS`/`TYPE_COMMITMENT_STATUS`) with independent pipelines, not sequenced against each other. Per the architectural-gap note above, `CustomResource` does not compose `HasStatusWorkflow` at all; `Operational/CustomResource/Traits/Form.php` instead adds two small protected helpers — `getWorkflowStatusField(string $column, string $relation, string $type, string $label): Select` (mirrors `getStatusWorkflowField()`'s `disableOptionWhen`/`default`/`live`/scoped-`modifyQueryUsing` shape, but takes `$type` as a plain argument instead of reading a resource-wide `statusWorkflowType()`) and `availableWorkflowStatusIds(string $type, string $relation, ?Model $record): array` (the same current+next+unordered set-building as the trait's `availableStatusWorkflowIds()`) — and `getClearanceStatusField()`/`getBankGuaranteeStatusField()`/`getCommitmentStatusField()` each call it with their own column/relation/type/label. **None of the 3 columns are disabled on `create`** (unlike PR/PO's single-column fields) — `Operational/CustomResource/Traits/PrepareCustomFromShipment.php`'s `afterFillFromShipment()` pre-fills `clearance_status_id` via `$this->form->fill([...])` when creating a Custom from a Shipment link, and a disabled (hence un-dehydrated, per Filament's `disabled()` semantics) field would silently drop that pre-filled value on save — the same root cause PurchaseOrder's paragraph above documents, independently re-confirmed here. A new page-level `Operational/CustomResource/Traits/HandleStatusMutation.php` (distinct from PurchaseRequest's own trait of the same name/namespace-sibling shape) exposes `assertCustomStatusTransitionsAllowed(array $data, ?Model $record = null): void`, looping its own `customStatusWorkflowColumns()` column→relation map and calling `StatusWorkflow::assertAllowed($user, $target, $record?->{$relation}, $column)` per column whenever that column is present in `$data` and resolves to a real `Status` — wired into `CreateCustom::mutateFormDataBeforeCreate()` and `EditCustom::mutateFormDataBeforeSave()`. None of Custom's 3 `Status` types have any admin-configured `stage_order` today, so `availableWorkflowStatusIds()` resolves to "every status of that type" (identical to the pre-existing plain `where('english_type', $type)` scoping) and `StatusWorkflow::canSet()` returns `true` unconditionally (`is_null($target->stage_order)` short-circuits) — confirmed a safe no-op via `CustomResourceTest`. `ReturnForRevisionAction`/`ResubmitAction` were deliberately NOT wired for Custom — both actions reset a single column to stage 1, and with 3 independent columns there's no non-ambiguous default for "which column does a return-for-revision apply to"; follow-up decision needed if that UX is ever wanted here.

**Payment wiring (single column) — safe no-op today, no `HandleStatusMutation` trait needed.** `PaymentResource::statusWorkflowType()` returns `Payment::TYPE_PAYMENT`; today none of that type's `Status` rows carry a `stage_order`/`approval_permission`, so `StatusWorkflow::initialFor()` returns `null` and `getStatusField()`'s option list stays fully unrestricted, matching the PO paragraph above. `CreatePayment`/`EditPayment` call `PaymentResource::applyInitialStatusOnCreate()`/`assertStatusTransitionAllowed()` directly in their own `mutateFormDataBeforeCreate()`/`mutateFormDataBeforeSave()` — no page-level `HandleStatusMutation` trait, Payment has no approver/rejection-reason side-fields to stamp. **`getStatusField()`'s `$extra` closure fully overrides `->disabled()`/`->hidden()` with Payment's own pre-existing target-selection gate (`empty($get('targetable_type')) || empty($get('targetable_id'))`), instead of passing a `$fallbackDefault` the way PO/RO/BankProfile/Correspondence do** — Payment's status field was already conditionally disabled/hidden before this trait existed (it only makes sense once a targetable is chosen), and the trait's own `->disabled()` (gated on whether a workflow is actually configured) would otherwise fight with that pre-existing condition rather than compose with it, so Payment keeps its full override rather than adopting the `$fallbackDefault` shape — same root cause as PO's paragraph (an ungated type's unconditional pre-extraction disable would have dropped `status_id` from `$data` on every create), resolved with Payment's own condition because it needed to survive the wiring unmodified. `getStatusField()` also keeps its own static `->helperText()` rather than the trait's dynamic locked-helper text, preserving the exact pre-existing UX. `ReturnForRevisionAction`/`ResubmitAction` were NOT added, for the same reasons as PO. `getStatusWorkflowPipelineHint()` WAS added in a later pass (2026-09-26, see the dedicated paragraph above), placed inside the same conditionally-hidden group as the status field. Verified via `PaymentResourceTest`'s status-workflow tests: the no-op today (full unrestricted option list, arbitrary status-to-status edits succeed) and the mechanism (forward transition rejected without the gating permission, succeeds with it) using a temporarily-configured `stage_order` pair inside the test's own transaction.

**RegisteredOrder wiring (4th consumer) — safe no-op today, same fallback-default root cause as PO/Payment, but WITH a page-level `HandleStatusMutation` trait and BOTH exempt actions.** `RegisteredOrderResource::statusWorkflowType()` returns `RegisteredOrder::TYPE_REGISTERED_ORDER`; none of that type's `Status` rows (`Draft`/`Submitted`/`Under renewal`/`Expired`) carry a `stage_order`/`approval_permission` today, so `StatusWorkflow::initialFor()` is `null` and `getStatusField()`'s option scoping stays fully unrestricted. `getStatusField()` passes `fn () => Status::findBy(TYPE, 'Submitted')?->id` as `getStatusWorkflowField()`'s `$fallbackDefault` argument — `disabled()` only becomes `true` on create once an initial stage actually exists, and `default()` prefers the configured initial status, falling back to that pre-existing `'Submitted'` default otherwise — functionally identical to PO's mechanism today (this paragraph used to describe RO's own local `$extra`-based re-derivation of both, removed 2026-09-26 once the trait absorbed it), self-upgrading the moment an admin configures RO's pipeline without any future edit. Unlike PO/Payment, RO's Create/Edit pages got a dedicated `Operational/RegisteredOrderResource/Traits/HandleStatusMutation.php` (mirrors PR's shape, minus the approver/rejection-reason stamping RO has no columns for) rather than inline calls — both are equivalent in effect; the trait was chosen here to keep the mutator unit-testable in isolation the same way PR's is. `ReturnForRevisionAction`/`ResubmitAction` WERE added to `EditRegisteredOrder`'s header actions (unlike PO/Payment, which deferred both) after confirming their `->authorize()` closures are genuinely inert against RO's live dev-DB `Status` data today — no row has `approval_permission` set, and none is named `Conditional`/`Declined` (the literal strings `ResubmitAction` checks), so both actions stay unreachable until an admin actually configures RO's pipeline; `getStatusWorkflowPipelineHint()` WAS added in a later pass (2026-09-26, see the dedicated paragraph above). Verified via `RegisteredOrderResourceTest`'s status-workflow tests: the no-op today (`availableStatusWorkflowIds()` returns every row for the type, `CreateRegisteredOrder`/`HandleStatusMutation`'s mutators pass values through unchanged) and the mechanism (`availableStatusWorkflowIds()` narrows to current+next once a `stage_order` triplet is temporarily configured inside the test's own transaction, and `HandleStatusMutation::mutateStatusData()` throws on a skipped-stage transition).

**Bank Profile wiring (5th consumer) — safe no-op today, same fallback-default root cause as PO/Payment/RO, WITH a page-level `HandleStatusMutation` trait, WITHOUT the exempt actions.** `BankProfileResource::statusWorkflowType()` returns `BankProfile::TYPE_BANK_PROFILE`; none of that type's 9 `Status` rows (`Allocated`/`Awaiting Supply`/`Purchased`/`Received`/`Ready for Allocation`/`Under Editing`/`Awaiting Bank Review`/`Rejected`/`Documentary Bill`) carry a `stage_order`/`approval_permission` today — note there is no `Submitted` row for this type despite the pre-existing hardcoded `Status::findBy(TYPE, 'Submitted')` default, so that lookup already resolved to `null` before this wiring too. `getStatusField()`'s `$extra` closure now only re-chains `->searchable()->preload()` (both pre-existing, dropped by the base `getStatusWorkflowField()`) — the `->disabled()` override this paragraph used to describe (removed 2026-09-26) is now the trait's own gating (`disabled()` only true on create once `StatusWorkflow::initialFor(TYPE)` resolves). `getStatusField()` still passes `fn () => Status::findBy(TYPE, 'Submitted')?->id` as the `$fallbackDefault` argument, preserving the pre-existing hardcoded default's exact expression even though it resolves to `null` either way, since no `Submitted` row exists for this type. `Operational/BankProfileResource/Traits/HandleStatusMutation.php` (mirrors PR's/RO's shape, minus approver/rejection-reason stamping BankProfile has no columns for) is wired into `CreateBankProfile::mutateFormDataBeforeCreate()` (alongside the pre-existing `PrepareBankProfileFromRegisteredOrder::afterFillFromRegisteredOrder()` context-prefill and the commission-mode NULL-out logic, both left untouched) and `EditBankProfile::mutateFormDataBeforeSave()`. Neither `ReturnForRevisionAction` nor `ResubmitAction` were added: `ReturnForRevisionAction` would be a genuine no-op (its `->authorize()` needs a filled `approval_permission`, absent on every Bank Profile Status row today) but adds a permanently-hidden header action with zero present-day value; `ResubmitAction`'s `->authorize()` hardcodes the literal `english_name`s `Conditional`/`Declined`, which don't exist in Bank Profile's own status vocabulary (`Rejected` is the nearest analog) — wiring it would stay dead even after an admin configures `stage_order`, since the name check itself doesn't generalize; both deferred pending a future decision on that vocabulary mismatch. `getStatusWorkflowPipelineHint()` WAS added in a later pass (2026-09-26, see the dedicated paragraph above). Verified via `BankProfileResourceTest`'s status-workflow tests: the no-op today (`availableStatusWorkflowIds()` returns every row for the type via `ReflectionMethod`, `applyInitialStatusOnCreate()` leaves `$data` untouched, the create-page Select stays enabled with a `null` default, `assertStatusTransitionAllowed()` permits an arbitrary status-to-status edit) and the mechanism (the Select locks and defaults to the configured initial stage once a `stage_order` pair is temporarily set inside the test's own transaction, and `assertStatusTransitionAllowed()` throws `ValidationException` on a skipped-stage jump).

**Shipment wiring (7th consumer, 5 independent columns) — the first live multi-type consumer of the `$type`/`$fallbackDefault` parameters above, safe no-op today.** `Shipment::statusHistoryColumns()` lists the 5 real columns — `status_id`/`container_status_id`/`operation_status_id`/`shipment_status_id`/`doc_status_id` — each its own independent `Status::english_type` (`TYPE_SHIPMENT_STATUS`/`TYPE_CONTAINER_STATUS`/`TYPE_OPERATION_STATUS`/`TYPE_TRACKING_STATUS`/`TYPE_DOC_STATUS` respectively), not sequenced against each other. `ShipmentResource` composes `HasStatusWorkflow` directly (unlike Custom's inline-replica approach above) and declares `statusWorkflowType(): string` returning the primary `TYPE_SHIPMENT_STATUS` (a documentation-convention default only — every call site below passes its own `$type` explicitly) and `statusWorkflowColumns()` returning `Shipment::statusHistoryColumns()`. Each of the 5 `Traits/Form.php` field getters calls `getStatusWorkflowField($column, $extra, $type, $fallbackDefault)` with its own type constant: `getStatusField()` (the one NOT-NULL FK) passes `fn () => Status::findBy(Shipment::TYPE_SHIPMENT_STATUS, 'Processing')?->id` as `$fallbackDefault` (the pre-existing hardcoded default) and merges the trait's dynamic `statusWorkflowLockedHelperText()` with that same static helper text as a fallback inside `$extra`, so the field's original UX text still shows while ungated; the other 4 (nullable FKs) pass no `$fallbackDefault` (they never had one) and override `->required(false)` in `$extra` since none were mandatory before this wiring. **One relation-naming exception surfaced during wiring:** the trait's mechanical column→relation derivation resolves `shipment_status_id` to `shipmentStatus`, but the real Eloquent relation on `Shipment` is named `trackingStatus` (paired with `TYPE_TRACKING_STATUS`). `ShipmentResource` overrides `statusWorkflowRelation(string $column): string` directly on the resource class (a plain method on the composing class always wins over a same-named trait method, no `insteadof` needed) to special-case that one column and fall through to the generic derivation for the other 4 — any future consumer with a similarly irregular relation name should copy this override shape rather than renaming the model relation to fit the trait. `CreateShipment::mutateFormDataBeforeCreate()` chains `applyInitialStatusOnCreate()` once per column with its own `$type`; `EditShipment::mutateFormDataBeforeSave()` loops `Shipment::statusHistoryColumns()` calling `assertStatusTransitionAllowed($record, $column, $data[$column] ?? null)` per column (column-driven already, no `$type` needed at that call site) — added without disturbing `EditShipment`'s existing custom `getFormActions()` Commercial Invoice save/print/reset action bar, since `mutateFormDataBeforeSave()` is a separate lifecycle hook the standard Save button still triggers underneath those custom actions. No `getStatusWorkflowPipelineHint()`/`ReturnForRevisionAction`/`ResubmitAction` were wired — the pipeline hint stays single-column-only per the gap note above, and both actions reset a single column to stage 1 with no non-ambiguous default among 5 independent columns, the same open question as Custom's paragraph above. Verified via `ShipmentResourceTest`'s status-workflow tests: the no-op today (`mutateFormDataBeforeCreate()` leaves all 5 status keys absent from `$data`, `mutateFormDataBeforeSave()` allows an arbitrary `container_status_id` transition) and the mechanism (temporarily configuring a 3-stage `stage_order` triplet on `Container Status` makes `assertStatusTransitionAllowed()` throw on a skipped-stage jump, while the legal next-stage transition still succeeds).

**Relocated from an inline form `Section` to a header-action-triggered modal, 2026-09-26.** `getStatusWorkflowPipelineHint(?string $sectionLabel = null): Section` (a collapsed `->collapsible()` `Section` wrapping a `Placeholder::make('status_workflow_pipeline')->listWithLineBreaks()`, embedded directly in each resource's `form()` right after the status field) is **removed**. Real user feedback: burying the legend inside the create/edit form meant it was easy to miss and had to be re-discovered per record; it's now a page header action, matching `HasDeskReferenceAction`'s exact shape (`Action::make(...)->modalContent(view(...))`, no `modalSubmitAction`) rather than a form-embedded accordion. `HasStatusWorkflow::getStatusWorkflowPipelineAction(): Action`:

```php
public static function getStatusWorkflowPipelineAction(): Action
{
    return Action::make('statusWorkflowPipeline')
        ->label(__('resources/general/strings.status_workflow.pipeline_label'))
        ->icon('heroicon-o-information-circle')
        ->color('gray')
        ->modalHeading(__('resources/general/strings.status_workflow.pipeline_label'))
        ->modalContent(fn (?Model $record) => view('filament.status-workflow.pipeline', [
            'pipeline' => static::statusWorkflowPipelineData($record),
        ]))
        ->modalSubmitAction(false)
        ->modalWidth('xl');
}
```

Wired as a header action on both the List page and the Edit page of all 6 consumers (`PurchaseRequestResource`, `RegisteredOrderResource`, `PurchaseOrderResource`, `PaymentResource`, `BankProfileResource`, `CorrespondenceResource`) — `Xxx::getStatusWorkflowPipelineAction()` added to each `ListXxx`/`EditXxx` page's `getHeaderActions()` array, alongside the existing `getDeskReferenceHeaderAction()`/`CreateAction`/`DeleteAction`/etc entries (no `array_filter()` needed — unlike the desk-reference action, this one is never conditionally `null`). `?Model $record` resolves via Filament's normal page-action dependency injection: `null` on a List page or a fresh Create, the real record on Edit — the trait's own data-building method already handles a `null` record (treats it as "new record, stage one" exactly as it did as a form `Placeholder`). The resource's `form()` method no longer calls the pipeline method at all — it was the only call site removed from `form()`; the status field itself (`getStatusIdField()`/`getStatusField()`) is unchanged.

`statusWorkflowPipelineData(?Model $record): array` (renamed from `statusWorkflowPipelineLines()`, which returned pre-formatted display strings) replaces the old string-building pair `statusWorkflowFormatOrderedLine()`/`statusWorkflowFormatUnorderedLine()` (both deleted) with a plain structured array, leaving all markup/direction concerns to the Blade view:

```php
['ordered' => [['icon' => '✅', 'order' => 1, 'name' => '...', 'responsible' => '...'], ...],
 'unordered' => [['icon' => '🔓', 'name' => '...', 'available_text' => '...'], ...]]
```

Built from the same `statusWorkflowStatuses(string $type): Collection` query as before (unchanged — shared with `availableStatusWorkflowIds()`), same icon rules (`statusWorkflowStageIcon()`) and same `{responsible}`/`{available_text}` text rules (`statusWorkflowResponsible()`, `statusWorkflowApprovers()`'s 0/1–3/>3-name branching) as before — only the OUTPUT SHAPE changed, not the data logic.

**The RTL fix is now genuinely markup/CSS-driven, not manual string reordering.** The prior fix (`statusWorkflowFormatOrderedLine()` branching on `app()->getLocale() === 'fa'` to emit `"{responsible} — {name} .{order} {icon}"` instead of `"{icon} {order}. {name} — {responsible}"`) was root-cause-wrong: it fought RTL by re-arranging content per locale in PHP, rather than letting the container's own `dir` do its job. Since the pipeline is now a real Blade view (`resources/views/filament/status-workflow/pipeline.blade.php`) instead of a `Placeholder`'s one-line-per-array-item string list, there is no more locale branching in PHP at all — ONE template renders both locales:

```blade
<div dir="{{ app()->getLocale() === 'fa' ? 'rtl' : 'ltr' }}" class="space-y-3">
    <div class="flex items-center gap-3">
        <span dir="ltr" class="flex shrink-0 items-center gap-1 text-sm font-semibold">
            <span>{{ $stage['icon'] }}</span><span>{{ $stage['order'] }}.</span>
        </span>
        <span class="text-sm"><strong>{{ $stage['name'] }}</strong> <span>— {{ $stage['responsible'] }}</span></span>
    </div>
    ...
</div>
```

The outer `dir` follows the document locale exactly like every other genuine RTL surface in this app (mirrors `filament/desk-reference/panel.blade.php`'s own `dir="{{ $isRtl ? 'rtl' : 'ltr' }}"` root, see `resources/views/viewsPattern.md`). The icon+order-number "bullet" is wrapped in its own `dir="ltr"` inline span (icons/digits are direction-agnostic, matching real design-system convention) so it always reads left-to-right as a self-contained unit regardless of the surrounding document direction, while the name/responsible text is a plain sibling span that flows in the CONTAINER's natural direction — in `fa`, the RTL container places that ltr-pinned bullet cluster at the visual right (the reading-order start for RTL) automatically, with zero PHP-side branching. The view does NOT append `attachments.revert_hint` or any other Supersede/Revert copy — that string belongs to the attachment status badge's own tooltip context, not this pipeline legend; an initial draft mistakenly rendered it here and was corrected the same day.

**`fa` RTL line composition — superseded 2026-09-26, kept here for history.** A prior fix (`statusWorkflowFormatOrderedLine()`/`statusWorkflowFormatUnorderedLine()`, `HasStatusWorkflowTraitTest::test_pipeline_lines_reorder_segments_for_fa_so_the_rtl_reading_order_stays_natural`) manually reordered segments per locale to work around the `Placeholder`'s plain-string rendering. That whole mechanism is deleted (see above) — do not reintroduce per-locale `sprintf` branching for this or any similar RTL display problem; use a `dir`-aware Blade view instead.

**Extended to the other 5 single-column consumers, 2026-09-26; relocated to the header-action-modal shape, 2026-09-26.** `RegisteredOrderResource`, `PurchaseOrderResource`, `PaymentResource`, `BankProfileResource`, and `CorrespondenceResource` each expose `static::getStatusWorkflowPipelineAction()` from both their List and Edit pages, mirroring PurchaseRequest exactly. Each resource's own `*ResourceTest.php` gained one `test_list_and_edit_pages_expose_the_status_workflow_pipeline_header_action` test (`Livewire::test(ListXxx::class)->assertActionExists('statusWorkflowPipeline')` + the same against `EditXxx::class` with a real factory record) — replacing the old `test_form_renders_the_status_workflow_pipeline_hint_after_the_status_field` `getComponent('status_workflow_pipeline', withHidden: true)` assertion, which no longer applies since the pipeline is not a form component anymore. `statusWorkflowPipelineData()`'s own data-building logic stays covered generically by `HasStatusWorkflowTraitTest` and PurchaseRequest's reflection-based assertions, now asserting the structured array shape instead of formatted strings.

**Attachment Supersede/Revert discoverability, 2026-09-26.** `SupersedeAttachmentAction`/`RevertAttachmentAction` (`app/Filament/Actions/`) already had `->label()` set (so they always carried a native `title`/`aria-label` via Filament's icon-button rendering) but no explicit `->tooltip()` — added on both, so hovering shows Filament's own styled tooltip (`x-tooltip`) instead of relying on the browser's plain `title` fallback. Both already had `->requiresConfirmation()` (confirmed, unchanged). Purchase Request's Edit FORM now also embeds the exact same `PurchaseRequestResource::viewAttachments()` `RepeatableEntry` (unchanged — the same method the View-modal infolist uses) directly in `form()`, right after `FormComponents::getAttachmentsField()`, guarded `->visible(fn (?Model $record): bool => (bool) $record)` so it only renders once a record exists (not on Create, before any attachment can exist). This is a deliberate, officially-supported Filament v4 capability — `Filament\Infolists\Components\Entry` and `Filament\Forms\Components\Field` both ultimately extend the same `Filament\Schemas\Components\Component`, so an Infolist entry (including its `suffixActions()`) can be composed directly inside a Form schema with zero adaptation; this project had no prior example of it, this is the first. The status badge gained a `->icon(fn (Attachment $record) => $record->isArchived() ? 'heroicon-m-lock-closed' : null)` (shared by both the Edit-form and View-modal renderings, since it's the same method) as the "clearly locked" treatment for an Archived attachment, alongside its pre-existing gray/warning/success color coding. The old View-modal/infolist `suffixActions()` wiring is left in place (low-cost, still correct) — the Edit-form version is additive, not a replacement.

### 1.13 Caching — `SmartCacheManager` + `DashboardStats` + `AnalyticsService`

```php
// App\Services\SmartCacheManager
public static function remember(string $model, array $filters, int $minutes, callable $callback): mixed
public static function invalidate(string $model): void
```

- Cache key: `smart_{strtolower(model)}_{md5(json_encode($filters))[0:16]}`
- Registry key per model: `smart_{strtolower(model)}_registry` (stored forever)
- `invalidate()` forgets every registered key + the registry + calls `clearNavigationCache($model)` which forgets `total_count_{strtolower(model)}`.

Navigation badge pattern (identical on PurchaseRequest / PurchaseOrder / Shipment):

```php
public static function getNavigationBadge(): ?string
{
    $count = SmartCacheManager::remember(
        '{ModelName}',
        ['user_id' => auth()->id(), 'type' => 'total_count'],
        150,                                  // 150-minute TTL
        fn() => static::getModel()::count()
    );
    return $count > 0 ? (string)$count : null; // null, not '0', so empty badges don't render
}
public static function getNavigationBadgeColor(): ?string { return 'info'; }
```

**GOTCHA:** the filter array includes `user_id` so the cache key is per-user, even though the callback counts ALL rows — the badge is per-user-cached but reflects the global count. Reproduce this exactly; do not "fix" it by removing `user_id`.

`getNavigationGroup(): ?string` must return the translated label. Do NOT set a static `$navigationGroup` property — that is dead code; the method wins.

```php
// App\Services\DashboardStats
public static function get(bool $fresh = false, int $ttlSeconds = 120): array
// cache key: "dashboard_counts:{userId}" (auth()->id() ?? 'guest')
// 120s TTL; returns 8 counts: payments, purchase_requests, proforma_invoices,
// bank_profiles, purchase_orders, registered_orders, shipments, customs
```

Used by the LandingPage. Bump `SmartCacheManager::invalidate({Model})` in any observer that mutates a count-bearing model.

**`AnalyticsService`** (dashboard analytics widgets): 6 static methods (`concentrationRisk`, `cycleTimeByStage`, `exposureAging`, `openCurrencyExposure`, `pipelineStalls`, `shipmentPunctuality`), each cross-model (spans PR/RO/Payment/Shipment/Customs/Currency), so each uses plain `Cache::remember('analytics:{key}', 300, ...)` — NOT `SmartCacheManager`, whose per-model registry doesn't fit data that isn't keyed to one model. Every method does its aggregation (`SUM`/`CASE WHEN`/`DATEDIFF`/HHI math) inside raw `DB::table()` queries, not PHP loops — MySQL 5.7 here has no window functions, so the one percentile metric (`cycleTimeByStage`) has SQL `ORDER BY duration` pre-sort the values and PHP only does an O(1) index pick, never a sort. Each `App\Filament\Widgets\*` widget is a thin `ChartWidget`/`StatsOverviewWidget`/`Widget` wrapper that calls exactly one `AnalyticsService` method — no query logic in the widget class. Raw `DB::table()` bypasses Eloquent's `SoftDeletingScope`, so every query manually adds `whereNull('{table}.deleted_at')` on every joined soft-deletable table.

### 1.14 General shared components

`App\Filament\Resources\General\`

- **`FormComponents::getAttachmentsField(): FileUpload`**

```php
FileUpload::make('attachments')
    ->multiple()->disk('public')->visibility('public')
    ->previewable()->openable()->live()->columnSpanFull()->downloadable()
    ->deletable(fn (?Model $record) => ! ($record?->status && StatusWorkflow::isTerminal($record->status)))
    ->hintIconTooltip(...)
    ->rules(new ValidAttachment())
    ->acceptedFileTypes(ValidAttachment::ALLOWED_TYPES)
    ->maxSize(ValidAttachment::MAX_SIZE_KB)
// saveUploadedFileUsing  → app(FileUploadManager::class)->storeTemporary($file)
// saveRelationshipsUsing → FileUploadManager->processTemporaryFiles($record, $state)->refreshComponent($record, $set)
// afterStateHydrated     → from $record?->attachments?->pluck('path')
```

**Attachment lifecycle — Uploaded / Superseded / Archived.** `App\Models\Attachment` carries `status_id` against the shared `Status` lookup (`english_type = Attachment::TYPE_ATTACHMENT`), with 3 states: `Uploaded` (default, set by `FileUploadManager` on every new file), `Superseded` (user-settable only — `App\Filament\Actions\SupersedeAttachmentAction`, visible when `Attachment::isUploaded()`), `Archived` (system-settable only — auto-applied to **every** attachment on a record the instant that record's own status hits its type's genuine ordered terminus; a user can only revert it back to `Uploaded` via `App\Filament\Actions\RevertAttachmentAction`, visible when `Attachment::isArchived()`). Both actions are plain `Filament\Actions\Action` factories wired as `TextEntry::suffixActions()` on the attachment's status badge inside `viewAttachments()` (currently wired on `PurchaseRequestResource` only — the one resource with an ordered status pipeline today; every other resource's own `viewAttachments()` copy is unaffected until it opts in the same way). The `getAttachmentsField()` field's `->deletable()` above is the record-level equivalent of "hidden only for Archived attachments": since the auto-archive step always archives a record's attachments as one bulk group, a terminal record's attachments are always 100% Archived, making the record-level gate exactly equivalent to a per-file one in the normal (forward-only) flow. The terminal check itself lives in `App\Services\StatusWorkflow::isTerminal(Status $status): bool`, and the actual archive-on-transition hook lives in `App\Models\Traits\General\TracksStatusHistory` (see `modelsPattern.md`) — both are no-ops for any model/type with no `stage_order` rows defined, so no per-module opt-in is required. Caveat for any future model with more than one status column (e.g. `Shipment`'s 5, `Custom`'s 3): `archiveAttachmentsIfTerminal()` archives on **any** tracked column reaching its own terminus, but the `->deletable()` gate above only inspects the model's `status()` relation — if a non-`status()` column (e.g. `Custom`'s `clearanceStatus()`/`bankGuaranteeStatus()`/`commitmentStatus()`, none of which is aliased as `status()`) is the one that goes terminal, attachments get archived server-side but the delete button stays enabled, and removing the file client-side still permanently `forceDelete()`s it via `FileUploadManager::syncAttachments()`. Currently dormant everywhere except Purchase Request — no other model's status types have `stage_order` rows populated yet — but must be re-checked before any other model's statuses get an ordered pipeline.

- **`TableComponents::show{Relation}(): TextColumn`** — `showProformaInvoices()`, `showPurchaseOrders()`, `showPurchaseRequests()`, `showRegisteredOrders()`. Pattern:

```php
TextColumn::make('{relation}')
    ->badge()->html()->default('-')->wrap()
    ->formatStateUsing(fn($state) => $state?->formatted_name_without_date ?? '-')
    ->searchable(query: fn(Builder $q, string $search) =>
        $q->whereHas('{relation}', fn($qq) => $qq->searchAll($search)),
        isIndividual: true)
    ->toggleable(isToggledHiddenByDefault: true)
```

- **`TableComponents::emptyState(Table $table): Table` / `::gatedEmptyState(Table $table): Table`** — every `table()` method project-wide (all 9 operational + 12 master root resources, all 25 RelationManagers) wraps its final chain in one of these two, e.g. `return TableComponents::emptyState($table->columns([...])-> ... ->defaultSort(...));`. `emptyState()` sets a generic translated icon/heading/description (`lang/*/resources/general/strings.php`'s `empty_state.*` keys); `gatedEmptyState()` sets a distinct "locked" icon/heading/description explaining the tab is status-dependent — use it on any RM whose header create/attach action is hidden behind an owner-status check (the same ~14 RMs documented in the create-action gate matrix below). Never hand-write `->emptyStateIcon()`/`->emptyStateHeading()`/`->emptyStateDescription()` inline — always go through one of these two so wording/iconography stays consistent and translated. Enforced by `RelationManagerIntegrityTest::test_tables_wrap_their_return_in_a_table_components_empty_state`, which scans both the 25 RelationManagers and every root `*Resource.php`.

  **`emptyState()` branches on active filters/search** (2026-09-25): `Filament\Tables\Table::getFilterIndicators()` (search indicator + column search indicators + filter indicators, the exact array driving Filament's own indicators bar above the table) is evaluated inside closures passed to `->emptyStateIcon()`/`->emptyStateHeading()`/`->emptyStateDescription()`, injected via `fn (Table $table) => ...` — when `filled($table->getFilterIndicators())`, the swapped-in copy is `empty_state.filtered_heading`/`filtered_description` (a magnifying-glass icon) instead of the generic `empty_state.heading`/`description`; a `->emptyStateActions([Action::make('resetFilteredEmptyState')->link()->visible(fn (Table $table) => filled($table->getFilterIndicators()))->action(fn ($livewire) => $livewire->removeTableFilters())])` reset-filters action is always registered but only visible in the filtered branch. `removeTableFilters()` (Filament's own `HasFilters` trait method) resets both filters AND search in one call — no bespoke reset logic needed. `gatedEmptyState()` is untouched — it has no filter/search context to branch on.

- **`InfoComponents::view{Relation}(): TextEntry`** — `viewProformaInvoices()`, etc.:

```php
TextEntry::make('{relation}.formatted_name')
    ->columnSpanFull()->listWithLineBreaks()->html()->wrap()
    ->visible(fn($record) => self::relationNotEmpty($record, '{relation}'))
```

Visible only when non-empty. Cross-resource badges are always toggleable and hidden by default in tables; always visible (when non-empty) in infolists.

### 1.15 `getEloquentQuery()` convention

Every resource overrides `getEloquentQuery()` to (a) eager-load `creator`, `updater`, `attachments`, `extraAttributes` + domain relations; (b) `->withCount([...])` for badge counts; (c) `->withoutGlobalScopes([SoftDeletingScope::class])`.

Verified — `PurchaseRequestResource.php:132`:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()
        ->with(['creator','updater','approver','attachments','extraAttributes','costCenter','department',
                'items','items.attachments','items.product','items.status',
                'proformaInvoices','registeredOrders','purchaseOrders','requester','status'])
        ->withCount(['proformaInvoices','registeredOrders','purchaseOrders'])
        ->withoutGlobalScopes([SoftDeletingScope::class]);
}
```

**Eager-load here, NOT in individual field/column definitions.** `getGlobalSearchEloquentQuery()` is a separate, lighter override — do not duplicate the full eager list there.

**Corollary — every `->unique()` form field on a `SoftDeletes` model must exclude trashed rows.** Because `getEloquentQuery()` deliberately keeps soft-deleted records queryable (so `RestoreAction` works from the list), a plain `->unique(ignoreRecord: true)` still matches a soft-deleted row via Filament's underlying `Rule::unique($table, $column)` (`vendor/filament/forms/src/Components/Concerns/CanBeValidated.php` builds this with no `deleted_at` awareness). Without a fix, deleting a record and then creating a new one that reuses its identifying value (name, code, email, or any `*_number`/`*_no`) is wrongly rejected as a duplicate. Always add `modifyRuleUsing: fn ($rule) => $rule->withoutTrashed()` (Laravel's `Illuminate\Validation\Rules\Unique::withoutTrashed()` is the purpose-built idiom for this — equivalent to `whereNull('deleted_at')` but self-documenting); if a field already has a `modifyRuleUsing` closure (e.g. `StatusResource`'s type-scoped uniqueness), chain `->withoutTrashed()` onto its existing return instead of replacing it. Every affected table was verified to actually carry a `deleted_at` column (no missing-column risk), and the fix was verified against a real DB transaction: a soft-deleted row's value now validates clean, while a genuinely active duplicate is still rejected. Enforced by `Tests\Unit\SoftDeleteUniqueValidationTest`, which scans every `{Name}Resource/Traits/Form.php` and fails if a `->unique()` call targets a `SoftDeletes` model without it.

### 1.16 Localization

All locale/key-structure, validation-message, wording, filter-localization, and RTL/emoji conventions moved to `lang/localizationPattern.md` — that file is now the exclusive, authoritative reference; do not maintain a second copy here.

Filament-schema-specific placement detail that stays here: every resource's `Filters` trait ends with `public static function getTrashedFilter(): TrashedFilter { return TrashedFilter::make(); }` — structural, not a wording convention. `maybeJalali()`/`->adaptive()` call sites are per-field, chained directly onto the `DatePicker::make(...)` in `Form.php` — see `lang/localizationPattern.md` §5/§7 for which helper to use where.

**Every date-range `Filter` (a `_from`/`_until` `DatePicker` pair) goes through `FilterComponents::dateRangeFilter()`** (`App\Filament\Resources\General\FilterComponents`, `dateRangeFilter(string $name, string $column, string $fromField, string $untilField, string $fromLabel, string $untilLabel): Filter`) instead of hand-building the `Filter::make(...)->schema([...])->query(...)` triplet inline — retrofitted 2026-09-26 across all 11 date-range filters project-wide, spanning 9 resources (RegisteredOrder/PurchaseRequest/PurchaseOrder/Payment/Custom/Correspondence `created_at`, BankProfile `created_at` + `payment_due_date`, Shipment `created_at` + `eta`, ProformaInvoice `invoice_date`). The helper chains `->columns(2)` onto the `Filter` itself — `Filament\Tables\Table\Concerns\HasFilters::getFiltersFormSchema()` wraps every filter's schema in a `Group::make()->columns($filter->getColumns())`, so this is what lays the two `DatePicker`s side-by-side in one row instead of Filament's stacked single-column default; no bespoke `Grid`/`Fieldset` needed, and it stays direction-aware for RTL with zero extra markup since it's plain Filament grid layout. A resource needing a custom `->indicateUsing()` (ProformaInvoice's `invoice_date` filter) chains it onto the helper's returned `Filter` — the helper itself stays generic.

### 1.17 Navigation groups (5, all `->collapsed()`)

| Key | Label |
|---|---|
| `operational_first` | 【1】 Purchase Requests Management |
| `operational_second` | 【2】 Order Registration Files |
| `operational_third` | 【3】 Files Financial Management |
| `operational_fourth` | 【4】 Logistics & Clearance |
| `base` | 【#】 Master Data Management |

Labels in `lang/en/resources/dashboard/strings.php`. Pipeline order: Purchase Request → Proforma Invoice → Registered Order → Purchase Order → Payment → Shipment → Customs. Resource group assignment MUST follow pipeline order, not alphabetical.

### 1.18 Operational vs Master split

The `Operational/` folder holds **10** resources: the canonical 8 two-tab/EAV resources plus 2 variants.

**Canonical 8 (two-tab form + EAV + totals):** PurchaseRequest, ProformaInvoice, RegisteredOrder, BankProfile, PurchaseOrder, Payment, Shipment, Custom.

**Variants:**
- **Correspondence** — full `List`/`Create`/`Edit` pages, `CorrespondenceExporter`, `Enums/Priority.php` + `Type.php`, NO EAV tab, NO totals, NO import (export-only, user-settled decision — see `importsPattern.md`). `CorrespondenceExporter` is a plain `write(Builder, string): int` class (converted from a native Filament `Exporter`, mirroring PR/PI/RO/BankProfile's shape — see `app/Services/Imports/importsPattern.md`'s "Rewiring `ExportBulkAction::make()->exporter(...)`" note), 17 columns (subject/body/type/priority/localized status/is_internal/is_private/resolved related-record+module/thread role+parent subject/recipients to-cc summary/creator+updater+timestamps), queued via `App\Jobs\ExportCorrespondences`. `Traits/Table::getExportBulkAction()` is wired into both `CorrespondenceResource::table()`'s own `toolbarActions()` and the sibling RelationManager below (`CorrespondenceResource::getExportBulkAction()`, explicit not `static::`, matching the project's cross-resource call convention). One RelationManager: `RegisteredOrderResource/RelationManagers/CorrespondenceRelationManager` (morphMany `correspondences`) with CreateAction/EditAction modals running the same recipient pipeline as the pages. **Recipient field-value contract (load-bearing):** `recipients_to` is an ID-keyed `Select` (hydrate with `pluck('id')`), `recipients_cc` is a name-based `TagsInput` (hydrate with `pluck('name')`; `HandlesRecipients::syncRecipients()` resolves CC names to IDs via `User::whereIn('name', …)` and 'to' wins over 'cc'). Enforced by `RelationManagerIntegrityTest::test_recipient_hydration_matches_field_value_types`. Has a read-receipts feature: a `CorrespondenceRecipient` pivot (`->using()` on both `Correspondence::recipients()` and `User::receivedCorrespondences()`, idempotent `markAsRead()` + `read_at` cast), `Correspondence::markReadBy(int $userId)` model method (guards non-recipients, idempotent), `Table::showReadStatus()` envelope icon column (filled=unread/open=read, blank for non-recipients), `Infolist::viewRecipients()` per-recipient read timestamp / "Unread" badge, and `Filters::getUnreadFilter()`; mark-read fires from `ViewAction::mutateRecordDataUsing(...)` (the read modal) + `EditCorrespondence::mutateFormDataBeforeFill()` (belt-and-suspenders).
- **Target** — operational placement, but uses the master-style single `ManageTargets` page (no `List`/`Create`/`Edit`). Has a full editable `Traits/Form.php`, `Enums/Status.php`, `Exports/TargetExporter.php`, NO RelationManagers, NO totals, NO EAV tab. This is the documented exception to the Operational=multi-page / Master=single-page dichotomy.

| | Operational — canonical 8 | Operational — variants (Correspondence, Target) | Master |
|---|---|---|---|
| Pages | List + Create + Edit + (View via modal) | Correspondence: List+Create+Edit; Target: single `ManageTargets` | single `ManageXxx` page |
| Form | full editable form in Tabs (General + EAV) | editable form, NO EAV tab | full editable `form()` for **11 of 12** resources (no EAV) — used by the header `CreateAction` and row `EditAction` (opens as a **modal**, no dedicated edit route). `StatusResource` is the one Master form that uses Tabs (General + Approval Workflow — see §1.12a), still without EAV. Only `EntityAttributeResource` is genuinely form-less/view-only — don't generalize "Master = no form" from that one case. |
| Header actions | Create button | Correspondence: Create; Target: Create | `CreateAction` in header for **11 of 12** Master resources. **`EntityAttributeResource` is the sole exception** — `getHeaderActions()` returns `[]` and it has no `form()` method at all. |
| Traits | `HasExtraAttributesManagement` + `Total{Name}Calculation` | neither EAV nor totals | `HandleActivation` (bulk activate/deactivate), no EAV, no totals |
| RelationManagers | yes (see §1.20) | no | no |
| Exporter | yes (see §1.23) | yes | varies |

Master resources: Bank, Category, Company, Currency, Department, EntityAttribute, NotificationSetting, Permission, Product, Role, Status, User. (CLAUDE.md's blanket "Master = no form, view-only via infolist" claim does not hold — see the Form row above; what's universal is no dedicated Create/Edit routes, not no form.)

**Department (added 2026-09-26, navSort 8; User/Role/Permission/NotificationSetting shifted to 9/10/11/12)** is the first Master resource with bulk import/export — the same flat `ImportAction` + plain importer wiring as Bank Profile (see `app/Services/Imports/importsPattern.md`'s flat-consumer sections). Its `is_active` toggle is the app-wide department/cost-center dropdown filter (consumer relations `->where('is_active', 1)`), so unlike Bank its activate/deactivate bulk actions carry `->authorize(canEditAny())` and the `is_active` ToggleColumn is `->disabled()` without `department.edit` — a deliberate one-module deviation, not yet applied to the other masters.

### 1.19 Custom Filament Page base classes (`App\Filament\Pages\*`)

Every List/Create/Edit/Manage page extends a project-local base, never Filament's directly. Each base adds an `#[On('calendar-toggled')] calendarToggled()` listener so the `CalendarToggle` Livewire component refreshes pages without per-page wiring. List/Manage keep it a no-op (soft re-render — table/infolist columns re-evaluate); **Create/Edit instead redirect** (`$this->redirect(request()->header('Referer'))`) — the jalali picker is a `wire:ignore`'d Alpine view Livewire cannot morph in place, so only a hard reload swaps the picker's calendar (helpersPattern.md §2). `ManageRecords` additionally `use PrefillsTableSearch`, whose `mount()` reads `?search=` and seeds `$this->tableSearch` (after `parent::mount()`) so deep links open the table already filtered.

```php
// app/Filament/Pages/ManageRecords.php
class ManageRecords extends BaseManageRecords
{
    use PrefillsTableSearch;

    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
```

`ListRecords`/`CreateRecord`/`EditRecord` follow the same shape minus `PrefillsTableSearch`. Every operational page (`ListPurchaseRequests extends ListRecords`, `CreatePurchaseRequest extends CreateRecord`, `EditPurchaseRequest extends EditRecord`, `EditShipment extends EditRecord`) and every master page (`ManageBanks extends ManageRecords`, `ManageEntityAttributes extends ManageRecords`) goes through these bases. Extending Filament's base directly silently drops the calendar refresh and the search prefill.

**`App\Filament\Pages\EditRecord` also overrides `authorizeAccess()`** to `abort_if($record->trashed(), 403)` after the parent's own `canEdit` check — every resource's `getEloquentQuery()` deliberately includes trashed records (via `withoutGlobalScopes([SoftDeletingScope::class])`, so Restore works from the list), which means a trashed record's `/edit/{id}` URL resolves and, without this guard, opens a fully editable, fully saveable form with no indication the record is archived. Since every RM's `EditAction` either explicitly `->url()`s to this same page or (via `$relatedResource`) auto-resolves to it, this one override covers direct top-level access and every RM edit link alike. It does not cover an `EditAction` that opens an in-table modal instead of navigating here (Correspondence's is the only one) — that path never touches this page class.

**App-wide friendly-error coverage — two complementary mechanisms, both backed by `App\Services\ExceptionPresenter`.** Filament has two independent action-execution paths, and each needed its own hook:

1. **`CreateRecord`/`EditRecord`'s dedicated save flow** (`App\Filament\Traits\HandlesSaveExceptions`) — they override `handleRecordCreation()`/`handleRecordUpdate()` to wrap `parent::` in a `try`/`catch (Throwable)`. On any exception that isn't Filament's own `Halt`, the trait's `reportSaveException()` presents the message as a persistent Filament danger notification, then `throw (new Halt)->rollBackDatabaseTransaction()` — the explicit `rollBackDatabaseTransaction()` call is load-bearing: `Halt` defaults that flag to `false` (commit), so omitting it would silently commit a transaction that just failed. Filament's own `create()`/`save()` catches `Halt` and rolls back quietly, so the generic Livewire "error loading page" fallback never fires.
2. **Every other Filament Action** — row actions (View/Edit/Delete/Restore), header actions, bulk actions, on any Page *or* RelationManager — flows through `Filament\Actions\Concerns\InteractsWithActions::callMountedAction()`, a completely separate mechanism from (1). `App\Filament\Traits\HandlesActionExceptions` overrides that same method, delegates to `parent::`, re-throws `ValidationException` untouched (Filament's own inline field-error UI must stay intact), and presents any other `Throwable` the same way — no `Halt` needed here since the vendor method's own `catch (Throwable)` already rolls back the transaction before re-throwing. `ListRecords`, `CreateRecord`, `EditRecord`, `ManageRecords`, and **all 25 RelationManagers** `use` this trait — genuinely app-wide, Operational and Master resources alike, nested tables included.

`ExceptionPresenter::present($e)` classifies the exception (QueryException by MySQL driver code — 1264 out-of-range, 1406 too-long, 1451/1452 FK violations, 1062 duplicate, etc. — plus `ModelNotFoundException`/`AuthorizationException`/`TokenMismatchException`/`PostTooLargeException`/generic fallback) into a translated `{title, body}` pair and logs the full exception to `laravel.log` tagged with a short reference code (`ERR-ymd-His-XXXX`) — the user sees the actionable message with that code, the admin greps the exact code in `laravel.log` for the full stack trace. Copy lives in `lang/{locale}/errors/strings.php`'s `notifications` group (see `localizationPattern.md`), not `resources/general/`, since it's error-page-adjacent content, not a resource-scoped or general UI string.

### 1.20 RelationManager conventions

**25 RelationManagers** exist (recounted from `app/Filament/Resources/**/RelationManagers/*RelationManager.php`), following one template. Verified against `PurchaseRequestResource/RelationManagers/ProformaInvoicesRelationManager.php`:

```php
class ProformaInvoicesRelationManager extends RelationManager
{
    use HandlesActionExceptions;   // App\Filament\Traits — see §1.19, app-wide friendly-error coverage
    use ProformaInvoiceTable;      // aliased Table trait from the sibling resource
    use ProformaInvoiceFilters;    // aliased Filters trait from the sibling resource

    protected static string $relationship = 'proformaInvoices';

    protected static ?string $relatedResource = ProformaInvoiceResource::class;

    public function infolist(Schema $schema): Schema
    {
        return ProformaInvoiceResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_no')
            ->columns([
                static::showID(), static::showInvoiceNo(), static::showSellerCompany(),
                static::showBuyerCompany(), static::showTotalAmount(), static::showInvoiceDate(),
                static::showCreator(), static::showUpdater(), static::showCreationTime(), static::showUpdateTime(),
            ])
            ->filters([
                static::getSellerCompanyFilter(), static::getBuyerCompanyFilter(), /* …more */
            ])
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('create')
                    ->label(__('resources/general/strings.actions.add_record'))
                    ->tooltip(__('resources/general/strings.actions.add_record_tooltip'))
                    ->visible(fn(): bool => in_array($this->getOwnerRecord()->status?->english_name, ['Authorized', 'Conditional']))
                    ->url(fn(): string => ProformaInvoiceResource::getUrl('create', ['purchase_request_id' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()->url(fn($record): string => ProformaInvoiceResource::getUrl('edit', ['record' => $record])),
                    DetachAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([BulkActionGroup::make([
                ExportBulkAction::make()->exporter(ProformaInvoiceExporter::class),
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
            ])])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('proforma_invoices.id', 'desc');
    }
}
```

Rules:
- Every RelationManager `use`s `App\Filament\Traits\HandlesActionExceptions` (see §1.19) — a save/action failure on a nested table gets the same translated notification as everywhere else in the app, not the generic Livewire fallback.
- **Every RM sets `protected static ?string $relatedResource = XxxResource::class;`** (the sibling resource, already imported). Without it, Filament's `InteractsWithRelationshipTable` falls back to the vendor's Policy-based default — which, in a project with no Policies and no `Gate::before()`, is `Response::allow()` — so RM Delete/Restore/bulk actions bypassed Spatie permissions entirely (same class of hole as §1.5's `get*AuthorizationResponse()` gap). With it: all RM action checks delegate to `$relatedResource::canX()`/`get*AuthorizationResponse()`, tab visibility maps to `canAccess()`, and bare `CreateAction`/`EditAction` auto-resolve `->url()` to the real resource pages (empty-modal bug for free). `Tests\Unit\ResourceAuthorizationIntegrityTest` fails if any RM lacks the property.
- `use` the related resource's `Table` and `Filters` traits (aliased) — reuse columns/filters verbatim; call the individual `show*()`/`get*Filter()` methods explicitly in `->columns([...])`/`->filters([...])`, do not redefine them inside the RM.
- `infolist()` delegates to the sibling resource's `infolist()`.
- The header create `Action` (and, on a pivot RM with no create flow, the header `AttachAction`) is gated by the OWNER record's status (`english_name` against an allowed list) — never unconditional. Verified gate matrix: PR-owned → `['Authorized', 'Conditional']`; RO-owned (PI/PO/PR links — the PR link via `AttachAction`, the rest via `create` — plus BankProfiles, Payments, Shipments, Customs) → `['Submitted']`; PO-owned (Payments, RegisteredOrders) → `['Approved']`; Shipment→Customs → `['Processing', 'Approved']`. PI-owned RMs (→PO, →RO) are the sole ungated ones — ProformaInvoice has no status column, so there is nothing to gate on. Enforced by `RelationManagerIntegrityTest::test_rm_create_actions_are_status_gated_on_status_bearing_owners` (auto-skips status-less owners like PI).
- The header create `Action` shares ONE app-wide translation key across all 15 auto-populate RM create buttons: `->label(__('resources/general/strings.actions.add_record'))` (`"˙⋆✮ Create New (Draft)"`) + `->tooltip(__('resources/general/strings.actions.add_record_tooltip'))`. The "(Draft)"/tooltip wording is deliberate: clicking it only opens a pre-filled Create page via a `PrepareXxxFromYyy` trait (see §1.21) — nothing is persisted until the user reviews and clicks that page's own Save. A single shared key means the wording is a one-file-per-locale change for all 15 call sites at once — don't hardcode a resource-specific label here. `PurchaseOrderResource/RelationManagers/PurchaseRequestsRelationManager.php` is the one RM that correctly omits both — it uses a header `AttachAction`, not this `create` Action.
- Record actions go in an `ActionGroup`: `ViewAction`, `EditAction` with `->url()`, `DetachAction`, `DeleteAction`, `RestoreAction` — for any RM whose related model uses `SoftDeletes` (all of them as of this writing), `RestoreAction` always follows `DeleteAction` at the end of the group, after `DetachAction`. **`DetachAction` belongs ONLY on pivot (`belongsToMany`) RMs — and on EVERY pivot RM** (an owned-relation RM — `belongsTo`/`hasMany`/`morphMany`: BankProfile, Shipment, Custom, Payment, Correspondence — must omit it, since the child row's existence doesn't survive the link). A pivot RM that links without a create flow (RO→PurchaseRequests) takes a header `AttachAction::make()` instead — the PR↔RO link was previously unmanageable from the RO side. Invariant enforced by `Tests\Unit\RelationManagerIntegrityTest::test_pivot_relation_managers_that_delete_also_offer_detach`: every pivot RM must offer `DetachAction` — deleting must never be the only way to unlink, and a link-only pivot must still be unlinked.
- Toolbar: `BulkActionGroup` with `ExportBulkAction->exporter(XxxExporter::class)` FIRST, then `DeleteBulkAction`, `RestoreBulkAction` — Export leads app-wide (root resources follow the same order: `ExportBulkAction` → Activate/Deactivate where present → `DeleteBulkAction` → `RestoreBulkAction`; standardized 2026-09-22 after Export's position was found inconsistent between resources). **Never `ForceDeleteBulkAction`/`ForceDeleteAction`** — permanent delete is banned app-wide by convention (no top-level resource offers it either); enforced by `RelationManagerIntegrityTest::test_force_delete_actions_are_not_used_anywhere`.
- Common tail: `->striped()->recordUrl(null)->defaultSort(...)`. `->searchDebounce('1000ms')->reorderableColumns()` appear on some RMs but are **not universal** — match the closest sibling RM instead.
- **Every RM eager-loads through the sibling resource**: `->modifyQueryUsing(fn ($query) => $query->with(RelatedResource::eagerRelations()))` as the first `table()` call — an RM does not inherit the sibling's `getEloquentQuery()`, so a reused `show*()` column that reads a relation (`status.name`, `buyerCompany.name`, the `TableComponents` badge columns) silently lazy-loads it per row (no `preventLazyLoading` is registered). `eagerRelations(): array` on each resource root is the single source of truth: `getEloquentQuery()` does `->with(static::eagerRelations())` and every RM references the same method, so a new eager dependency propagates from one line. RMs that render a `show*Count()` column or a `Source` badge additionally chain the matching `->withCount('rel')` calls (counts don't inherit either). Read-only variants with a custom `->query()` closure put the `->with()`/`->withCount()` there instead — **inside** the closure, chained onto `$this->getRelationship()`/the query it returns. Chaining them onto the `->query(fn () => ...)` call itself instead (i.e. after its closing `)`) silently calls them on the `Table` object, which has no such methods — a fatal "call to undefined method" the instant the tab renders, invisible to `php -l` and to a quick read. Enforced by `RelationManagerIntegrityTest::test_rm_queries_eager_load_the_sibling_resources_relations` (also catches a typo'd `eagerRelations()` class reference — verifies the `use` import for that exact short name resolves to a real class).
- **A RelationManager never gets bulk import (`getImportAction()`/`GroupedImportAction`), current or future modules alike — industry-standard, confirmed 2026-09-22, not a project-specific opinion.** `PurchaseRequestResource::getImportAction()` was wired into 3 RMs alongside `AttachAction` and then removed the same day: bulk import always matches/dedupes against the ENTIRE table (see `app/Services/Imports/importsPattern.md`'s `resolveRecord()` — global, no owner-record awareness anywhere in the import action classes), so a CSV row that happened to match a record belonging to a *different* parent silently updated it with zero connection to — and zero visible feedback in — the RM tab the import was triggered from. Real SaaS/ERP apps (Salesforce, NetSuite, HubSpot, QuickBooks, SAP) never scope bulk import to one parent's sub-tab either — it's always a top-level, module-wide operation from that module's own list page; where they support "import records related to a parent," it's via a parent-reference COLUMN inside the CSV itself, not page context, which is exactly the shape this app's importers already have. An RM gets `AttachAction` (link an existing record) and/or a `Prepares{Target}From{Parent}`-driven single auto-populate `create` Action (pre-fill a new record's form via URL query param, user still reviews and submits — see the `create` Action in the code block above) — never bulk import, unless the import pipeline is first given real owner-record-scoped matching + auto-attach, which does not exist today and is not a quick addition.

**Read-only variant**: drop Delete/Restore from `recordActions`/`toolbarActions`, keep only `ViewAction` + `EditAction->url()`. (Filament v3's `protected bool $canAssociate/$canCreate/$canDelete/$canDissociate/$canEdit = false` properties do nothing in v4 — nothing in the framework reads them — and have been removed from all read-only RMs; the structural omission of the actions themselves, plus the `$relatedResource` authorization delegation above, is what actually enforces read-only. Don't re-add them — `RelationManagerIntegrityTest::test_relation_managers_do_not_declare_dead_v3_can_properties` fails on any comeback.) Two flavors: polymorphic targetable-scoped (`PaymentResource/RelationManagers/{PurchaseOrder,RegisteredOrder}RelationManager`) additionally override `getRelationship()` to scope by `targetable_type === Xxx::class`; plain `belongsTo` (`CustomResource/RelationManagers/RegisteredOrderRelationManager`, `ShipmentResource/RelationManagers/RegisteredOrderRelationManager`, `BankProfileResource/RelationManagers/RegisteredOrdersRelationManager`) need no override — the owner's own FK already scopes it. Use this whenever an RM exposes a shared hub record (e.g. `RegisteredOrder`) that other pipeline records also point to — deleting/restoring it from a leaf tab would affect every other attached record, so those tabs stay view + edit-link only. Enforced by `RelationManagerIntegrityTest::test_hub_relation_managers_are_read_only`. The Payment RMs' `$relationship` values (`purchaseOrder`/`registeredOrder`) resolve against `Payment`'s bare `belongsTo(PurchaseOrder/RegisteredOrder::class, 'targetable_id')` hooks — those model methods exist solely so the RM contract can name a relationship; they are unscoped on `targetable_type` and must never be consumed directly or deleted (the RM `getRelationship()` override is the only real guard).

### 1.21 Cross-resource prefill — `PrepareXxxFromYyy` & `UpdatesFromXxx`

When the pipeline forks a child record from a parent via a `?{parent}_id=` query param, the child Create page `use`s one or more `PrepareXxxFromYyy` traits and overrides `afterFill()` to dispatch:

```php
// ProformaInvoiceResource/Pages/CreateProformaInvoice.php
use PreparesProformaFromPurchaseRequest,
    PreparesProformaFromRegisteredOrder,
    PreparesProformaFromPurchaseOrder;

public function afterFill(): void
{
    if (request()->has('purchase_request_id')) self::afterFillFromPurchaseRequest();
    if (request()->has('registered_order_id'))    self::afterFillFromRegisteredOrder();
    if (request()->has('purchase_order_id'))      self::afterFillFromPurchaseOrder();
}
```

Each `Prepare{Child}From{Parent}::afterFillFrom{Parent}()` reads the parent model, generates the child's code via `CodeGenerator::generate('{code_column}')`, and `$this->form->fill([...])`s the new record's defaults. Verified in `PrepareShipmentFromRegisteredOrder`, `PrepareBankProfileFromRegisteredOrder`.

The sibling `UpdatesFromXxx::populateFromXxx($state, Set $set)` aggregates items from selected parents into a child Repeater (uses `Set`, not `form->fill`): `UpdatesFromPurchaseRequests`, `UpdatesFromPurchaseOrders`, `UpdatesFromRegisteredOrders`, `UpdatesFromProformaInvoice`.

A new pipeline child must replicate a `Prepare{Child}From{Parent}` trait plus the `afterFill()` dispatcher reading `?{parent}_id=`.

**The 3 relation `Select` fields' `->visible()` must check for existing data, not just the `source_type` radio.** `RegisteredOrder`/`ProformaInvoice`/`PurchaseOrder` each have a virtual `Radio::make('source_type')` (no DB column — drives the auto-populate-items convenience) plus 3 `belongsToMany` relation `Select` fields (e.g. `purchaseRequests`, `proformaInvoices`, `purchaseOrders`). Two rules to replicate for any new resource adopting this pattern:

1. `getSourceTypeField()` needs `->afterStateHydrated()` deriving a pre-selected value from whichever relation has rows (priority order `pr` → `pi`/`ro` → `po`) — a bare `->default(null)` leaves the radio empty on edit (no column to hydrate it from), hiding all 3 relation fields even when pivot rows exist.
2. **A record can legitimately have pivot rows in more than one relation simultaneously.** A single `->visible(fn (Get $get) => $get('source_type') === 'xx')` can only ever reveal ONE of them. OR in a `filled()` check against the field's own (always-hydrated-regardless-of-visibility) state:

```php
->visible(fn (Get $get): bool => $get('source_type') === 'pr' || filled($get('purchaseRequests')))
```

The `afterStateHydrated` alone is not sufficient once multi-linked records exist — both fixes must be applied together.

**Known related gap, not yet fixed:** `source_type`'s `Radio` has no `afterStateUpdated` to clear the other two relation fields when the user switches type. Since Filament dehydrates hidden-but-populated fields by default, switching source types on a record that already has a different type's selections can save pivot rows into more than one relation at once. If strict single-source-type-per-record is the intended business rule, this needs an `afterStateUpdated` that clears the other two fields' state on switch.

### 1.22 Total/calculation traits

A static `updateTotal(Get $get, Set $set): void` reads the `items` Repeater state via `$get`, computes totals, and `$set`s derived fields. Wired from a Repeater field's `->afterStateUpdated` / `->live()`:

```php
public static function updateTotal(Get $get, Set $set): void
{
    $items = collect($get('items') ?? []);
    $set('total_quantity', $items->sum('qty'));
    $set('total_amount', $items->sum(fn($i) => ($i['qty'] ?? 0) * ($i['price'] ?? 0)));
}
```

Naming is inconsistent across resources — replicate the closest sibling:

| Trait | Method | Sets |
|---|---|---|
| `TotalCostCalculation` (PurchaseRequest) | `updateTotalCost(Get, Set)` | `total_estimated_cost` |
| `TotalCalculation` (PurchaseOrder, RegisteredOrder) | `updateTotal(Get, Set)` | `total_quantity` + `total_amount` |
| `Calculation` (BankProfile) | `updateComputations(Get, Set)` + private `compute*` helpers; `commission_input_mode` toggles direction | derived banking fields |
| `TotalAmountCalculation` / `ItemAmountCalculation` (ProformaInvoice) | per-line + total | line amounts + total |
| `ItemCalculation` (RegisteredOrder) | per-line | line amounts |

**Two separate layers must both exist for a resource's total_amount/total_quantity to actually display, and both must agree on the formula.** This form-side `TotalCalculation`/`updateTotal` trait only drives the live *edit-form* preview via `Get`/`Set` — it never touches the model. The *infolist*/read side calls `$record->total_amount` directly, which requires a model-layer accessor (`Traits\{Model}\HasComputedAttributes`, `modelsPattern.md` §4) plus `$appends`. `RegisteredOrder` had the form-side trait but was missing the model-side accessor until fixed 2026-09-25 — its infolist Total Amount/Total Quantity silently showed blank/0.00 regardless of real item data. When adding total fields to a new resource, both layers are required, not just the form trait — and the accessor's formula must match this trait's own `$shipping + $extra` terms, not just `quantity * unit_price` (a first pass of this exact fix copied `PurchaseOrder\Accessors`' simpler formula verbatim, which undercounts any `RegisteredOrderItem` carrying `shipping_cost`/`extra_cost` — caught by `claude-reviewer`, not the initial tests, because the tests only set `quantity`/`unit_price` too. The corrected accessor sums the item's own persisted `line_total` column instead of recomputing the arithmetic — matching `AnalyticsService`/`RegisteredOrderExporter`'s existing convention and staying correct even if the formula ever changes).

### 1.23 Exporter skeleton

Exporters follow one shape:

```php
class PurchaseRequestExporter extends Exporter
{
    use ExportDefaults;

    protected static ?string $model = PurchaseRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')->label('ID'),
            ExportColumn::make('pr_number'),
            ExportColumn::make('items')->state(fn($record) =>
                $record->items->map(fn($i) => $i->product?->name)->implode(' | ')),
        ];
    }
}
```

`ExportDefaults` provides `getCompletedNotificationBody`, `getFileName` (`{app}-{MODEL}-{His}`), and `getQuery()` (`parent::getQuery()->with([...eagerLoadRelations()])->limit(1000)`; override the protected `eagerLoadRelations(): array` hook rather than duplicating `getQuery()`). Array/relation columns use `->state(fn($record) => ...->implode(' | '))` for array-to-string export.

**`BankProfileExporter` and `CustomExporter` each override `getFileName(Export)`** with their own shape (e.g. `"CustomsClearance-{$export->getKey()}"`) instead of `ExportDefaults`'s `{app}-{MODEL}-{His}`. This is a legitimate per-exporter override, not a bug — the class method simply shadows the trait's. Match a sibling exporter's shape when adding a new one rather than assuming `ExportDefaults`'s format is universal. **`RegisteredOrderExporter` no longer belongs to this native-`Exporter`/`ExportDefaults` family** — converted 2026-09-25 to the plain `write(Builder, string): int` shape alongside `PurchaseRequestExporter`/`ProformaInvoiceExporter` (see §1.8b) as part of adding Registered Order's grouped bulk import/export; its `getFileName()` override no longer exists, superseded by `App\Jobs\ExportRegisteredOrders`'s own filename construction.

### 1.24 Page mutator & lifecycle hooks

Only `mutateFormDataBeforeFill` (InvoiceForm, §1.10) is covered above; the project uses a full set systematically:

| Hook | Use |
|---|---|
| `mutateFormDataBeforeCreate` | set creator/department from `auth()->user()` (`CreatePurchaseRequest`) |
| `mutateFormDataBeforeSave` | re-apply status mutation logic on edit (`EditPurchaseRequest`) |
| `mutateFormDataBeforeFill` | hydrate EAV-backed fields (InvoiceForm), hydrate recipients (`EditCorrespondence`), hydrate `doc_tracking` from `docs` JSON (`HandlesDocumentChecklistForm`) |
| `afterFill` | prefill from parent via query param (§1.21) |
| `afterCreate` / `afterSave` | sync side relations: `DocChecklistMatcher::sync($record)` (`SyncsDocumentChecklist`), `saveRecipientsToRecord` (`EditCorrespondence`) |

### 1.25 `DashboardPanelProvider` — panel config

`app/Providers/Filament/DashboardPanelProvider.php`. Beyond `discoverResources`, it wires panel-wide identity — login page, 5 collapsed nav groups, color palette, `fa`-conditional font, `spa()`, `Ctrl+K`/`Cmd+K` global search, dark-mode default, favicon/brand logos, `authMiddleware` (includes `EnsureUserIsActive::class`) — read the file directly for the full list. Touch panel-level config here, not in resources.

**Branding paths** (`config/app.php` → `'branding'`): `favicon`, `logo.light`, `logo.dark` — all relative to the public root, `img/logos/*`. Consumers resolve them one of two ways depending on whether they need the compiled asset pipeline: `Vite::asset('resources/'.config('app.branding.*'))` (used by the panel's `favicon()`/`brandLogo()`/`darkModeBrandLogo()`, since those paths are Vite source paths) vs plain `asset(config('app.branding.*'))` (used by `errors/layout.blade.php` and the landing-page `header.blade.php`, since `resources/img/*` is a Vite static-copy target served verbatim from `public/img/*` — no manifest lookup needed). Never hardcode a logo/favicon path literal at a new call site — add it to `config('app.branding.*')` instead.

### 1.26 Bootstrap — `AppServiceProvider`, Configurators, Macro provider, Observers

`AppServiceProvider::boot()` is the single Filament wiring point (via a private `configureFilament()`), delegating to five `App\Configurators\*` classes, and also registers observers (via a private `registerObservers()`):

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    $this->configureFilament();   // FilamentCustomLogin, LanguageSwitcher, FilamentAssets, FilamentRenderHooks, FilamentTableDefaults
    $this->registerObservers();
}
```

| Configurator | Job |
|---|---|
| `FilamentCustomLogin::configure($app)` | binds `LoginResponseContract` → `FilamentLoginResponse` (post-login redirect) |
| `LanguageSwitcher::configure()` | `LanguageSwitch::configureUsing(...)`, render hook `GLOBAL_SEARCH_BEFORE`, locales `config('language-switch.locales', ['fa','en','fr'])`, flags are 3 explicit `Vite::asset(...)` entries, `flagsOnly()` |
| `FilamentAssets::register()` | `FilamentAsset::register([Css::make('fi-custom-css', ...), Js::make('nav-dock-js', ...), Js::make('topbar-autohide-js', ...), Js::make('table-density-js', ...)])`. The `Js::make` entries are required for panel-wide behavior (bottom-dock nav, auto-hide topbar, table density toggle) — `resources/js/app.js`'s plain `@vite()` is landing-page-only, so any JS meant to run inside the panel itself MUST be registered here. |
| `FilamentRenderHooks::configure()` | **Six** render hooks: `SIMPLE_LAYOUT_START` → `filament.partials.login-hero` (scoped to `CustomLogin::class` only); `GLOBAL_SEARCH_AFTER` → `calendar-toggle`, `nav-dock-toggle`, `topbar-pin-toggle`, `table-density-toggle` (four separate registrations, panel-wide); `HEAD_END` → `filament.partials.meta`. The topbar auto-hide itself is pure CSS on `.fi-topbar-ctn` (a 14px always-visible sliver + `transform` slide) — no render hook needed for the hide/reveal mechanic, only for the pin toggle. |
| `FilamentTableDefaults::configure()` | `Table::configureUsing(fn (Table $table) => $table->paginated([25, 50, 100]))` — the single panel-wide pagination default (2026-09-25). Applies to every `Table` instance app-wide, RelationManagers included, since `Table::configureUsing()` is a genuinely global hook (no resource ever called `->paginated()`/`->defaultPaginationPageOption()` before this). `Filament\Tables\Table\Concerns\CanPaginateRecords::getDefaultPaginationPageOption()` falls back to `10` only if `10` is present in the options array; since `[25, 50, 100]` doesn't include `10`, the default becomes `Arr::first($options)` = `25` — no explicit `defaultPaginationPageOption()` call needed. |

The render-hook slot choice is intentional: switcher `GLOBAL_SEARCH_BEFORE`, calendar/nav-dock/topbar-pin `GLOBAL_SEARCH_AFTER`. Each configurator is a final class with a single public static entry (`configure()` / `register()`); add a new one by creating a file and calling it from `AppServiceProvider::boot()`.

`FilamentMacroServiceProvider::boot()` registers three macro families: `Field::macro('tooltip', ...)` (wraps `hintAction` with an info icon), `DatePicker::macro('adaptive', ...)` (returns `$this->jalali()` when the session calendar is Jalali — the in-chain sibling of the `maybeJalali()` helper, used by `Filters.php` DatePickers), and `TextColumn`/`TextEntry` `adaptiveDate()`/`adaptiveDateTime()` (display dates following the calendar toggle — no-format calls delegate to the `adaptiveDate()` helper, format-arg calls use the package's `jalaliDate($format)`/`date($format)` pair so Gregorian output stays byte-identical). Every Table/Infolist date column/entry uses the adaptive macros instead of raw `->date()`/`->dateTime()` — enforced by `tests/Unit/AdaptiveDateTest.php`.

Observers — three registration paths; do not mix them (a side-effect observer registered through the auto path won't run its side effects — it gets `NotificationDispatcher` instead — and a notification-only observer registered manually is redundant; match the path to the observer's job):
1. **Manual, side-effect** (`AppServiceProvider::registerObservers()`): `Category::observe(CategoryObserver::class)`, `PurchaseRequest::observe(PurchaseRequestObserver::class)`. Use for side-effect observers (closure-table sync, status cascade).
2. **Manual, code generation** (same method, looped): `AppServiceProvider::CODE_GENERATED_MODELS` (PurchaseRequest, ProformaInvoice, RegisteredOrder, PurchaseOrder, BankProfile, Payment, Shipment, Custom) each get `::observe(CodeGeneratingObserver::class)`. `CodeGeneratingObserver::creating()` calls `CodeGenerator::fieldsForModel(get_class($model))` and assigns each returned field via `CodeGenerator::generate($field)` — this is what auto-fills `PR-250612`-style codes on create. A new pipeline model needing an auto-generated code column must be added to this array (and to `CodeGenerator`'s own field/prefix map).
3. **Auto, notification-only** (`NotificationServiceProvider::boot()`): scans `app/Models/` for classes with a `SCANNABLE_TABLE` const and attaches `NotificationDispatcher` (which delegates to `NotificationEvaluator`). Declaring `SCANNABLE_TABLE` is enough, no `AppServiceProvider` edit.

### 1.27 `HasDeskReferenceAction` — optional per-module header Action opening a reference modal

`App\Filament\Traits\HasDeskReferenceAction::getDeskReferenceHeaderAction(): ?Action` adds an optional, content-driven "Desk Reference" header Action button to a module's List page (beside Create), opening a content-only modal. Fully config + lang driven — the trait itself holds no content.

Resolution chain:
1. `$key = Str::camel(class_basename(static::getModel()))` looks up `config("desk-reference.{$key}")`; returns `null` if the resource isn't in the registry.
2. Content gate, order matters: `Lang::has("deskReference/{$group}")` first (a missing group returns the literal key string from `trans()`, not an array), then requires at least one of `terms`/`process`/`dos`/`donts`/`tips` to be genuinely non-empty.
3. Read/unread comes from `DeskReference::where(['user_id'=>auth()->id(),'group_key'=>$group,'version'=>$version])->exists()`, tracked by **group, not resource** — acknowledging via one sibling module clears the reminder on every other resource sharing that group, since the content is byte-identical between siblings.
4. Returns an `Action` — `warning` color + pulsing `dr-unread` CSS ring while unread, `gray` once seen — with `modalContent(view('filament.desk-reference.panel', [...]))->modalSubmitAction(false)->modalWidth('4xl')`. The panel Blade splits Reference (text) and Media tabs via Alpine and self-acknowledges via a `fetch()` POST on mount.

Content lives in `lang/{locale}/deskReference/{group}.php` (fa+en+fr; `covers` holds resource-key identifiers, not display strings), registered in `config/desk-reference.php` (8 resources → 4 group keys: `request_approval`/`order_processing`/`procurement_payment`/`logistics`).

**Wiring a new resource (2 edits):**
1. Resource root — add `HasDeskReferenceAction` to the class `use` list.
2. List page `getHeaderActions()` — prepend `...array_filter([XxxResource::getDeskReferenceHeaderAction()])` before `CreateAction::make()` (the base `ListRecords` does not merge resource-level header actions, so this is per-page).

### 1.28 Self-referencing hierarchy resources — Category's cycle guard + delete-blocking pattern

`CategoryResource` is this project's one self-referencing-tree Master resource (`parent_id` → `categories.id`, a `category_closure` table maintained by `CategoryObserver`, see `app/Models/modelsPattern.md`). Two reusable mechanisms were added here, 2026-10-06, for any future self-referencing resource to copy:

**Cycle guard — both layers, not just one.** `App\Models\Traits\Category\Relationships::wouldCreateCycle(?int $recordId, ?int $parentId): bool` (plus its sibling `descendantIdsOf(int $categoryId): array`, both querying the closure table via the existing `descendants()` relation, no raw SQL) is the single source of truth, consumed twice: (1) `Traits\Form::getParentCategory()`'s `Select::make('parent_id')->relationship('parent', 'name', modifyQueryUsing: fn (Builder $query, ?Model $record) => $query->whereNotIn('id', [$id, ...Category::descendantIdsOf($id)]))` filters the record's own descendants OUT of the rendered options entirely — a user editing category A never even sees A's own children as selectable parents; (2) a `->rule(fn (?Model $record): Closure => function ($attribute, $value, $fail) use ($record) { if (Category::wouldCreateCycle($record?->id, (int) $value)) $fail(...); })` backstop on the same field, since `modifyQueryUsing` only narrows the browser-rendered dropdown — a bulk import or any other programmatic write bypassing the Select entirely still needs the same check. Both layers call the identical model-level helper; a future self-referencing resource reuses `wouldCreateCycle()`/`descendantIdsOf()` verbatim if it already has an ancestor/descendant closure relation to query, or writes an equivalent pair against its own hierarchy storage otherwise.

**`level`-suggestion is a convenience, never a lock.** The same `parent_id` Select is `->live()` with `->afterStateUpdated(fn (Set $set, $state) => $set('level', $state ? Category::find($state)?->level + 1 : 0))` — re-picking a parent re-suggests `level`, but the `level` `TextInput` itself stays fully editable/required with no `->disabled()` and no cross-field validation forcing agreement. This is deliberately asymmetric with the BULK IMPORT path (`CategoryImporter::afterFill()`), which derives `level` unconditionally server-side with no CSV column at all — a human editing one record at a time is an acceptable manual-override risk, a CSV row multiplies a bad value across many records silently, see `app/Services/Imports/importsPattern.md`'s Category section.

**Delete-blocking via `before()` + `$action->halt()` — the reusable shape for "don't delete a record something else still depends on."** `Traits\Table::getDeleteAction()`/`getDeleteBulkAction()` wrap the plain `DeleteAction`/`DeleteBulkAction` with a `->before(function (Model $record, DeleteAction $action) { ...; $action->halt(); })` / `->before(function (Collection $records, DeleteBulkAction $action) { ...; $action->halt(); })` closure that checks `$record->children->isNotEmpty() || $record->products->isNotEmpty()` (both already eager-loaded in `getEloquentQuery()` — zero extra queries), sends a `Notification::make()->danger()` naming the blocking count, then calls `$action->halt()` to cancel the delete before it runs. For the bulk variant this is a single check over the WHOLE selection, evaluated once before `action()`'s per-record loop — one blocked record cancels the entire batch, nothing partially deletes. This is a general Filament mechanism (`Filament\Actions\Concerns\HasLifecycleHooks::before()` + `Action::halt()`, confirmed in vendor `Action.php`), not Category-specific plumbing — any future resource needing "block delete while a dependent relation is non-empty" copies this exact shape.

## 2. Developer Decision Matrix

| When you need to… | Do this… | Why… |
|---|---|---|
| Add a new operational resource | Create `app/Filament/Resources/{Name}Resource.php` (namespace `App\Filament\Resources`) + `Operational/{Name}Resource/Traits/{Form,Table,Infolist,Filters,Total{Name}Calculation}.php` + `Pages/List{Name}.php`, `Create{Name}.php`, `Edit{Name}.php`. Root `use`s all traits + `HasResourcePermissions` + `HasExtraAttributesManagement`. | `discoverResources` only sees root-level classes; the `Operational/` subtree is imported, not auto-registered. |
| Add a new master resource | Same root-class placement, but `Master/{Name}Resource/Traits/{Table,Infolist,Filters,Form}.php` + single `Pages/Manage{Name}.php`. Root `use`s `HandleActivation` (not `HasExtraAttributesManagement`). Header actions include `CreateAction::make()` (only `EntityAttributeResource` returns `[]`); edit happens via the row `EditAction` opening as a modal. | Master resources have no dedicated create/edit **routes/pages** and no EAV tab (see §1.18 re: `form()` still existing for 11 of 12). |
| Add a form field | Add `getXxxField()` to the resource's `Form` trait; call it from `form()`. Translate the label in all 3 locale files. | Field internals live in traits, not in the root `form()` method. |
| Add a table column | Add `showXxx()` to `Table` trait; call from `table()`. | Same trait-driven composition. |
| Add an infolist entry | Add `viewXxx()` to `Infolist` trait; call from `infolist()`. | Same trait-driven composition. |
| Add a filter | Add `getXxxFilter()` to `Filters` trait; call from `table()`. | Same trait-driven composition. |
| Add a form tab | Insert a `Tab::make(__('…form.tab_xxx'))` in the root `Tabs::make(...)`, BEFORE `static::getExtraAttributesFormTab()`. Add `tab_xxx` to all 3 locale files. | The Extra Attributes tab is always last; `tab_*` keys must exist in all locales. |
| Persist EAV from a form tab | Mark every field `->dehydrated(false)`; persist via a save action in the Edit page's `getFormActions()` (reads `$this->data`, calls a `persistXxxToEav(array $formData, $record)` that writes ONE `EntityAttribute` row) — NOT a schema-level `Section::footerActions()`/`Get`. Hydrate in the Edit page's `mutateFormDataBeforeFill`. | The canonical virtual-tab pattern (see §1.10, InvoiceForm). Keeps EAV state out of the Eloquent model's save cycle; page-level placement keeps the save action next to the record's own Save button instead of buried in a schema footer. |
| Show a count on a tab label | `Tab::make(fn($record) => tabBadge(__('…tab_xxx'), $record?->relation->count() ?? 0, 'info'))` | `tabBadge` is the global helper; `.tb-badge` CSS already exists. |
| Cache an expensive query | `SmartCacheManager::remember('{ModelName}', $filters, $minutes, fn() => …)`; bust with `SmartCacheManager::invalidate('{ModelName}')` in the relevant observer. | Model-keyed registry enables bulk invalidation + navigation cache clear. |
| Add a navigation badge | Override `getNavigationBadge(): ?string` returning `null` when count is 0 (NOT `'0'`), 150-minute TTL, per-user `user_id` in filters. `getNavigationBadgeColor(): ?string` → `'info'`. | Empty badges must not render; per-user cache key reflects global count (intentional). |
| Eager-load relations | Add `->with([...])` and `->withCount([...])` to `getEloquentQuery()` only. | Avoids N+1; keeps field/column defs free of query concerns. |
| Gate an action | Rely on `HasResourcePermissions` — do not write a new Policy. Permission string is `{snake_singular_model}.{action}`. | The trait is what every Filament resource actually uses for its `can*` checks (see §1.5 — `app/Policies/` doesn't exist). |
| Add a date picker | Chain `->adaptive()` — the convention everywhere except Shipment's `InvoiceForm.php` and Custom's `Form.php`, which wrap with `maybeJalali($component)` instead. | Without either, fa users get Gregorian regardless of session. |
| Cross-resource relation badge in a table | Use `TableComponents::show{Relation}()` — do not reinvent. | Shared component; toggleable, hidden by default, search-all wired. |
| Cross-resource relation entry in infolist | Use `InfoComponents::view{Relation}()` — visible only when non-empty. | Shared component; avoids duplication across 8 resources. |
| Attachments field | Use `General\FormComponents::getAttachmentsField()` — never write a `FileUpload` from scratch. | Handles temp→permanent pipeline via `FileUploadManager`. |
| Auto-generate a code column | Add the model to `AppServiceProvider::CODE_GENERATED_MODELS` and `CodeGenerator`'s field/prefix map. | `CodeGeneratingObserver::creating()` only runs for models in that list. |

## 3. Absolute Anti-Patterns (Do Not Do This)

- ❌ **Creating a Policy class for a Filament-managed model.**
  - Why: `HasResourcePermissions` is the access-control system Filament resources actually use; `app/Policies/` doesn't exist. If a Policy IS ever registered/auto-discovered, Filament would resolve it ahead of the trait's `can*` methods, silently changing access semantics.

- ❌ **Setting a static `$navigationGroup` property on a resource.**
  - Why: it is dead code; `getNavigationGroup(): ?string` (the method) always wins. Set the method, returning the translated `__('resources/dashboard/strings.general.navigation_group')`.

- ❌ **Returning `'0'` from `getNavigationBadge()`.**
  - Why: Filament renders the badge for any non-null string. Return `null` so empty badges don't render.

- ❌ **Putting `->columns(3)` on the root Schema of a form.**
  - Why: breaks the 3/1 column split inside the General tab. `->columns(3)` belongs on the `Tab`; the root Schema has no column setting; `->columnSpanFull()` is on the `Tabs` container.

- ❌ **Eager-loading inside a field/column definition.**
  - Why: causes N+1 and duplicates work. All eager-loading lives in `getEloquentQuery()`.

- ❌ **Using `if ($x) $query->where(…)` outside a query chain.**
  - Why: violates the conditional-query convention. Use `->when($x, fn($q) => $q->where(…))` / `->unless(...)`.

- ❌ **Calling `dd()`, `dump()`, or `var_dump()`.**
  - Why: ships debug output to production. Use telescope/logs if you must introspect.

- ❌ **Hardcoding status strings in resource code.**
  - Why: `Status` is polymorphic and locale-keyed. Always resolve via `Status::findBy(Model::TYPE_X, 'EnglishName')`.

- ❌ **Calling bare `->sortable()` on a `TextColumn::make('relation.localized_name')` (or any other accessor-only dot-path).**
  - Why: **reproduced live 2026-09-22** — `localized_name` (`Localization` trait) is a PHP accessor (`getLocalizedNameAttribute()`), not a database column. Filament's automatic relationship-sort builds `ORDER BY <table>.<attribute>` straight from the dot-path, so sorting crashes the whole page with `SQLSTATE[42S22]: Column not found`. This broke `PurchaseRequestResource`'s Department/Cost Center columns in production use, not just in a test. Pass an explicit `->sortable(query: fn (Builder $query, string $direction) => $query->orderBy(RelatedModel::select(app()->getLocale() === 'fa' ? 'name' : 'english_name')->whereColumn('related_table.id', 'this_table.fk_id'), $direction))` instead — see `PurchaseRequestResource\Traits\Table.php`'s `sortByRelatedDepartmentName()` for the reference implementation. Any OTHER resource with a `.localized_name` (or similarly accessor-only) sortable column carries the same latent crash — check before assuming `->sortable()` is safe on a dot-path column. For a **polymorphic** relation (more than one possible related model, e.g. `MorphTo`), a single correlated subquery isn't enough — see `BankProfileResource\Traits\Table.php`'s `sortByTargetableName()`, which builds one correlated scalar subquery per possible `targetable_type` branch (Product, Category) and combines them with `COALESCE(...)`, filtered so each branch only matches its own type.

- ❌ **A nested closure inside a `->searchable(query: fn ($query, $search) => $query->orWhereHas(...))` callback, e.g. `fn ($q) => fn ($q) => $q->searchByName($search)`.**
  - Why: **reproduced live 2026-09-26** — the outer closure just RETURNS the inner closure object instead of calling it; `whereHas`'s constraint closure receives `$q` but never mutates it, so the relation existence check silently degrades to "has any related row." For a column whose FK is always set (e.g. a required `status_id`), that's true for every record. Filament's global search wraps every globally-searchable column's constraint into ONE `where(function () {...})` OR-group (`CanSearchRecords::applyGlobalSearchToTableQuery()`) — one always-true clause in that OR-group defeats search for the ENTIRE table, not just that column, with no error and no visible symptom besides "search does nothing." Found in `BankProfileResource\Traits\Table::showStatus()`. Any `->searchable(query: ...)` callback with a nested `fn (...) => fn (...) => ...` shape is this bug — check the closure actually calls the inner query, don't just check it compiles.

- ❌ **An unqualified `->searchable('id')` / `where('id', ...)` on a `showID()` (or similar) Table-trait column that gets reused inside a RelationManager built over a pivot with its own `id()` column.**
  - Why: **reproduced live 2026-09-25** — `PurchaseRequestResource\Traits\Table::showID()` crashed with `SQLSTATE[42S22]` ambiguous `id` when reused (for column parity, an established pattern) inside `RegisteredOrderResource\RelationManagers\PurchaseRequestsRelationManager`, a `belongsToMany` over `registered_order_purchase_request` — a Shape-C pivot (`modelsPattern.md` §10) with its own `$table->id()`, so the pivot's `id` collides with the base table's `id` once joined. Fixed by qualifying to `purchase_requests.id`. `PurchaseOrderResource`, `BankProfileResource`, `RegisteredOrderResource`, and `ProformaInvoiceResource`'s `showID()` are all qualified the same way now. Any new `showID()`/unqualified-`id` reference is only safe today against a Shape A/B pivot (no `id()`) — qualify defensively regardless, since a future migration could add one.

- ❌ **Using `->as()` to alias the `customAttributes` / `extraAttributes` morphMany into one relation.**
  - Why: the double-declaration (two methods, same morphMany, no `->as()`) is intentional — it prevents closure conflicts between `ManageCustomAttributesAction` and the `HasExtraAttributesManagement` Repeater. Collapsing them breaks one consumer.

- ❌ **Omitting `formatStateUsing` on an `extraAttributes` Repeater value field or infolist entry.**
  - Why: `EntityAttribute.value` is JSON-cast; without it, Eloquent returns arrays/scalars that crash the `Textarea` / `TextEntry` renderer.

- ❌ **Persisting EAV-backed form fields with `->dehydrated(true)`.**
  - Why: those fields would attempt to write to the Eloquent model's columns. The virtual-tab pattern requires `->dehydrated(false)` on every EAV-backed field + explicit footer-action save.

- ❌ **Inserting a tab between General and Extra Attributes (other than Shipment's `getInvoiceFormTab()`).**
  - Why: Shipment's mid-tab is the single sanctioned exception. Any other mid-tab must amend this doc first.

- ❌ **Writing a `FileUpload` for attachments from scratch.**
  - Why: `General\FormComponents::getAttachmentsField()` already wires the temp→permanent pipeline through `FileUploadManager`. Re-implementing drops `storeTemporary` / `processTemporaryFiles`.

- ❌ **Omitting `->adaptive()` (or `maybeJalali()` in the two files that use it) on a date picker.**
  - Why: `fa` users who selected Jalali in the CalendarToggle get Gregorian calendars otherwise.

- ❌ **Adding a `tab_*` key to only one or two locale files.**
  - Why: missing locales render the raw key. All three (`en`, `fa`, `fr`) must receive the key simultaneously.

- ❌ **Removing `user_id` from the navigation-badge `SmartCacheManager::remember` filter array.**
  - Why: the per-user cache key is intentional even though the callback counts all rows. "Fixing" this causes cache-key collisions across users.

- ❌ **Hardcoding colors that have a `--custom-*` or `--google-*` CSS variable.**
  - Why: the design-system tokens are the single source of truth; hardcodes drift.

- ❌ **Re-inventing glassmorphism / 3D / shimmer utilities inline.**
  - Why: landing-page CSS owns these; duplicates rot. The enterprise redesign removed most of them from the landing page — do not reintroduce them there either.

- ❌ **Extending Filament's `ListRecords` / `CreateRecord` / `EditRecord` / `ManageRecords` directly.**
  - Why: the project bases (`App\Filament\Pages\*`) add the `#[On('calendar-toggled')]` refresh and `PrefillsTableSearch` deep-link prefill. Extending Filament's base directly silently drops both.

- ❌ **Declaring `mutateFormDataBeforeFill()` on a page that also `use`s a trait declaring it.**
  - Why: PHP resolves the class method over the trait, so the trait's fill hydration is silently skipped. Live hazard, confirmed: `EditShipment` declares its own `mutateFormDataBeforeFill` for commercial-invoice hydration AND `use`s `HandlesDocumentChecklistForm` which also declares it, so the doc-checklist fill is skipped. Consolidate into ONE place when touching this file.

- ❌ **Using `ForceDeleteAction` / `ForceDeleteBulkAction` anywhere in the app.**
  - Why: permanent delete is banned app-wide by convention (product decision) — soft-delete + `RestoreAction` covers recovery, and no RM/page may destroy records beyond recall. Enforced by `Tests\Unit\RelationManagerIntegrityTest::test_force_delete_actions_are_not_used_anywhere`. (`HasResourcePermissions`' `canForceDelete*` → `delete` mapping stays — it only denies harder, it never adds a UI action.)

- ❌ **Adding `->unique()` to a form field on a `SoftDeletes` model without `modifyRuleUsing: fn ($rule) => $rule->withoutTrashed()`.**
  - Why: `getEloquentQuery()` intentionally keeps trashed records queryable app-wide (see §1.15, §1.18), but Filament's `->unique()` still matches them by default — a soft-deleted record silently blocks creating a new one that reuses its value. See §1.15's corollary. Enforced by `Tests\Unit\SoftDeleteUniqueValidationTest`.

- ❌ **Chaining `->validationMessages()` / `->validationAttribute()` directly on a `MorphToSelect`.**
  - Why: in this Filament v4 build `MorphToSelect` extends `Filament\Schemas\Components\Component` (not `Field`) and does not mix in `HasValidationConcerns` — so neither method exists and the call throws `BadMethodCallException: Method MorphToSelect::validationMessages does not exist` on render. (`->required()` is fine: `MorphToSelect` defines that method itself, so it is not sourced from `HasValidationConcerns`.) `MorphToSelect` builds two inner `Select` fields internally and exposes `modifyTypeSelectUsing` / `modifyKeySelectUsing` to customize them — route the localized validation onto those inner selects instead. Canonical pattern (the 3 resources that define `getTargetableField(): MorphToSelect` — BankProfile, Target, Payment):
    ```php
    return MorphToSelect::make('targetable')
        ->label(...)
        ->types([Type::make(...)->..., ...])
        ->searchable()
        ->required()
        ->modifyTypeSelectUsing(fn (Select $select): Select => $select
            ->validationAttribute(__('resources/xxx/strings.form.targetable'))
            ->validationMessages(['required' => __('resources/xxx/strings.form.validation_required')]))
        ->modifyKeySelectUsing(fn (Select $select): Select => $select
            ->validationAttribute(__('resources/xxx/strings.form.targetable'))
            ->validationMessages(['required' => __('resources/xxx/strings.form.validation_required')]));
    ```
  - Do not replace the `modify*SelectUsing` form with a bare `->required()`; that drops the translated "required" message.

## 4. Naming conventions

- **Resource root class**: `{Name}Resource` at `app/Filament/Resources/{Name}Resource.php`, `namespace App\Filament\Resources`.
- **Per-resource traits**: `app/Filament/Resources/Operational/{Name}Resource/Traits/{Form,Table,Infolist,Filters}.php`; `Total{Name}Calculation.php` (PurchaseRequest uses `TotalCostCalculation`).
- **Trait method prefixes**: `getXxxField()` (form field) · `showXxx()` (table column) · `viewXxx()` (infolist entry) · `getXxxFilter()` (filter).
- **Pages**: `List{Name}.php` / `Create{Name}.php` / `Edit{Name}.php` (Operational); `Manage{Name}.php` (Master).
- **Exporters**: `app/Filament/Resources/Operational/{Name}Resource/Exports/{Name}Exporter.php`, `use ExportDefaults`.
- **Enums**: `app/Filament/Resources/Operational/{Name}Resource/Enums/Status.php`.
- **Shared components**: `App\Filament\Resources\General\{FormComponents,InfoComponents,TableComponents,FilterComponents}` — methods `getAttachmentsField()`, `show{Relation}()`, `view{Relation}()`, `dateRangeFilter()`.
- **Shared traits**: `App\Filament\Traits\{HasResourcePermissions,HasExtraAttributesManagement,HandleActivation,ExportDefaults,HasDeskReferenceAction,HasStatusWorkflow}`.
- **Permission strings**: `{snake_singular_model}.{view|create|edit|delete}`.
- **Locale keys**: `lang/{locale}/resources/{camelCaseResource}/strings.php` with top-level groups `general` / `form` / `table` / `filters` / `infolist`; tab labels prefixed `tab_`.
- **EAV virtual-tab fields**: prefix with `_inv_` (InvoiceForm precedent); never collide with real Eloquent columns.
- **Navigation group keys**: `operational_first` / `operational_second` / `operational_third` / `operational_fourth` / `base`.
- **Page bases**: `App\Filament\Pages\{ListRecords, CreateRecord, EditRecord, ManageRecords}` — required parents for every resource page; never extend Filament's base directly.
- **RelationManagers**: `app/Filament/Resources/{Operational,Master}/{Name}Resource/RelationManagers/{Related}RelationManager.php`, `extends RelationManager`, `protected static string $relationship`.
- **Cross-resource prefill traits**: `Prepare{Child}From{Parent}` (Create-page `afterFill` dispatcher) and `UpdatesFrom{Parent}` (`populateFrom{Parent}($state, Set $set)` for Repeater aggregation), in the child resource's `Traits/`.
- **Total/calculation traits**: `Total{Name}Calculation` (canonical), with `Calculation` / `ItemCalculation` / `TotalAmountCalculation` / `ItemAmountCalculation` as named siblings; method `updateTotal(Get, Set)` or `updateTotalCost` / `updateComputations`.
- **"Lapsed" date badge columns**: `is{Concept}Stale($record): bool` (or `isValidityLapsed`) + `show{DateField}()` pair on the resource's `Table.php` trait — true when a nullable date column `isPast()` AND a sibling `withCount()` alias on the SAME record is still `0` (i.e. the record hasn't progressed to its own next pipeline stage). `showXxx()` swaps the plain date for a translated `..._expired` badge (`danger` color) via `formatStateUsing`/`badge()`/`color()`, all keyed off the SAME boolean helper — never duplicate the condition inline in more than one closure. Two live instances: `ProformaInvoiceResource\Traits\Table::isQuoteStale()` (keyed off `registered_orders_count`) and `RegisteredOrderResource\Traits\Table::isValidityLapsed()` (keyed off `purchase_orders_count`) — copy whichever is closer when adding a third.
- **Compact one-row-per-item infolist display**: the `view{Relation}Items()`/`viewPurchaseItems()`/`viewInvoiceItems()`-style `RepeatableEntry` on an operational resource's `Infolist.php` should show ONLY the item's core transactional fields (product, quantity, unit, unit price, and one total/amount-shaped column — 5-6 entries) laid out via `->columns(N)` sized so they fit ONE visual row (`N` = sum of each entry's own span, `columnSpan(2)` on the product name entry, `1` implicit on the rest — verify against Filament's actual grid math, don't guess). Secondary/detail fields (origin, HS code, weights, free-text notes/description) do NOT belong in this compact row — either drop them from the infolist entirely or (if genuinely needed) surface them elsewhere, never as a `columnSpanFull()` entry inside this same `RepeatableEntry`, since that forces a line-wrap per item and breaks the one-row look. Pair with `->extraAttributes(['class' => 'fi-in-repeatable-spaced'])` on the `RepeatableEntry` itself (`stylesPattern.md` §2) for inter-row breathing room. Three live instances, all shaped this way: `RegisteredOrderResource\Traits\Infolist::viewItemProduct()`+siblings, `ProformaInvoiceResource\Traits\Infolist::viewInvoiceItems()`, `PurchaseRequestResource\Traits\Infolist::viewPurchaseItems()`.
- **Filament macros**: `Field::macro('tooltip', ...)`, `DatePicker::macro('adaptive', ...)` — registered in `FilamentMacroServiceProvider::boot()`.
- **Configurators**: `app/Configurators/{FilamentCustomLogin, LanguageSwitcher, FilamentAssets, FilamentRenderHooks, FilamentTableDefaults}.php` — each a final class with one static entry, wired from `AppServiceProvider::boot()`.

## 5. Design rules

- Methods are single-responsibility and short (~20 lines max). Extract when exceeded.
- No `dd()`, `dump()`, `var_dump()` in shipped code.
- All DB access via Eloquent. Raw `DB::` only when Eloquent cannot express it. Conditional query building uses `->when()` / `->unless()` — never `if ($x) $query->where(…)` outside query chains.
- Eager-load in `getEloquentQuery()` only — never in field/column definitions.
- Cache expensive queries via `SmartCacheManager`. Bust with `SmartCacheManager::invalidate({Model})` in the relevant observer.
- Tabs over nested Sections for forms with 5+ fields. The canonical 8 operational resources follow the two-tab pattern (General + Extra Attributes, with Shipment's Invoice tab as the sole sanctioned mid-tab). Correspondence and Target are variants — see §1.18.
- `->columnSpanFull()` on the `Tabs` container; `->columns(3)` on the `Tab`; the root Schema has no column setting.
- `static::getExtraAttributesFormTab()` is always the last form tab; `static::getExtraAttributesInfolistTab()` is always the last infolist tab.
- Translate every user-facing string. No hardcoded English in `form()` / `table()` / `infolist()`.
- When adding a tab, add `tab_*` keys to all three locale files simultaneously. Tab labels always carry the `tab_` prefix.
- Every date picker passes through `->adaptive()` (or `maybeJalali($component)` — only Shipment/Custom's invoice forms use this variant).
- `getNavigationBadge()` returns `null` when the count is 0 — never `'0'`.
- `getNavigationGroup()` is a method returning the translated label, never a static `$navigationGroup` property.
- Every resource `use`s `HasResourcePermissions` for its own authorization — `app/Policies/` doesn't exist in this project (see §1.5). **`NotificationSettingResource` is the sole exception** — it doesn't `use` the trait, so its create/edit/delete/restore actions fall through to Filament's vendor default (`Response::allow()` for any authenticated user, per the §1.5 gotcha), and no `notification_setting.*` permissions are seeded anywhere. Do not add the trait to this resource without first seeding those permissions — doing so would flip it from allow-all to deny-all for every user, not fix anything.
- EAV-backed form tabs use the virtual-tab pattern: `->dehydrated(false)` on every field + explicit footer-action save + page-mutator hydration in `mutateFormDataBeforeFill`.
- The `customAttributes()` / `extraAttributes()` double-declaration on `HasCustomAttributes` is intentional. Do not collapse with `->as()`.
- `formatStateUsing` is mandatory on any `extraAttributes` Repeater value field and on any infolist `value` entry.
- Models with `SoftDeletes` require `->withoutGlobalScopes([SoftDeletingScope::class])` in `getEloquentQuery()`.
- Every `->unique()` field on a `SoftDeletes` model adds `modifyRuleUsing: fn ($rule) => $rule->withoutTrashed()` (merged into any existing `modifyRuleUsing` closure) — see §1.15 corollary.
- Use design-system CSS tokens (`--custom-*`, `--google-*`) — never hardcode colors that already have a variable.
- Do not re-invent landing-page utilities (`.glass`, `.card-3d`, `.shimmer-effect`, etc.) inline — the enterprise redesign removed most of them from the landing page; do not reintroduce them there.
- Table pagination page-size options are set exactly once, panel-wide, via `FilamentTableDefaults::configure()` (`Table::configureUsing(...)`) — never call `->paginated([...])`/`->defaultPaginationPageOption(...)` on an individual resource's `Table.php` trait; that would fork the panel-wide default per resource.
- A table-level "reset filters" empty-state action always routes through Filament's own `$livewire->removeTableFilters()` (resets filters AND search in one call) — never hand-roll a bespoke reset closure per resource.
- The row-density toggle (`html.table-density-compact`) and its two-static-variant button (`filament/partials/table-density-toggle.blade.php`) mirror the topbar-pin/nav-dock toggle shape exactly (`Alpine.store()` + `localStorage` + `livewire:navigated` re-apply) — see `resources/js/scriptPattern.md` §10 for the shared pattern and the tooltip-reliability lesson it's built on. Do not build a third bespoke shape for a future pure-client-side panel-wide toggle.
