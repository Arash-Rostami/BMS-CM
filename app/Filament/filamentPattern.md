Verified against source on branch `feature/landing-page-enterprise-redesign` (2026-07-18). Where this doc conflicts with CLAUDE.md, this doc is authoritative for Filament resource architecture.

# BMS-CM Filament Resource Pattern

This is the replicable pattern for BMS-CM's Filament v4 resources. Every new resource — Operational or Master — must be composed exactly as documented here: trait-based schema composition, two-tab form/infolist, `getEloquentQuery()` eager-loads, `SmartCacheManager` navigation badges, and (where applicable) the EAV `extraAttributes` tab. Future AI agents must reproduce this layout verbatim; deviations are bugs.

## Core idea

**Trait-based schema composition.** Each root Resource class composes its form, table, infolist, filters, permissions, and EAV behavior through a single `use` list of traits on the root class itself. There are no dedicated presenter/action classes per resource; the traits ARE the presenters. This is the deliberate tradeoff vs. the presenter-class alternative (one `XxxResourceForm` object injected into the resource) — it keeps every resource's full surface area visible in one file at the cost of trait method-name uniqueness, enforced by prefixing (`getXxxField` / `showXxx` / `viewXxx` / `getXxxFilter`).

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
    HandleActivation.php  ExportDefaults.php
```

`DashboardPanelProvider` registers resources via `->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')`. Only root-level classes that extend `Resource` are registered. The `Operational/` and `Master/` subdirectories hold Traits/Pages/RelationManagers/Enums/Exports imported by the root file — they are NOT auto-registered as resources. **A new resource MUST create a top-level `app/Filament/Resources/{Name}Resource.php` with `namespace App\Filament\Resources`.**

## 1. Responsibility of each part

### 1.1 Root resource class — the composer

The root class declares `namespace App\Filament\Resources`, extends `Resource`, and `use`s the trait list. It owns the four `static` Filament hooks (`form`, `infolist`, `table`, `getEloquentQuery`), plus `getPages`, `getRelations`, `getNavigationGroup`, `getNavigationBadge`. The trait methods are called from inside these hooks.

Verified `use` lines:

```php
// PurchaseRequestResource.php:52
use PurchaseRequestForm, TotalCostCalculation, PurchaseRequestTable,
    PurchaseRequestFilters, PurchaseRequestInfolist,
    HasResourcePermissions, HasExtraAttributesManagement;

// PurchaseOrderResource.php:54
use TotalCalculation, PurchaseOrderForm, PurchaseOrderTable,
    PurchaseOrderFilters, PurchaseOrderInfolist,
    HasResourcePermissions, HasExtraAttributesManagement;

// ShipmentResource.php:48 — Shipment inserts InvoiceForm, the only sanctioned mid-tab
use ShipmentForm, ShipmentTable, ShipmentFilters, ShipmentInfolist,
    ShipmentInvoiceForm, HasResourcePermissions, HasExtraAttributesManagement;

// BankResource.php:33 — master: no EAV, no totals, uses HandleActivation
use BankForm, BankTable, BankInfolist, BankFilters,
    HandleActivation, HasResourcePermissions;
```

Trait namespaces:
- Per-resource: `App\Filament\Resources\Operational\{Name}Resource\Traits\{Form|Table|Infolist|Filters}` (Form aliased `as {Name}Form`), plus `…\Traits\TotalCostCalculation` (PurchaseRequest) or `…\Traits\TotalCalculation` (PurchaseOrder / RegisteredOrder). Shipment uses `App\Filament\Resources\Operational\ShipmentResource\Traits\InvoiceForm`.
- Shared root-level: `App\Filament\Traits\{HasResourcePermissions, HasExtraAttributesManagement, HandleActivation, ExportDefaults}`.

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

`App\Filament\Traits\HasResourcePermissions` is what every Filament resource relies on for its `can*` authorization checks. Derives the permission prefix from the model basename via `Str::snake()`. Permission strings follow `{snake_singular_model}.{view|create|edit|delete}`.

Verified — actual trait body (`app/Filament/Traits/HasResourcePermissions.php`):

```php
public static function getPermissionPrefix(): string
private static function allows(string $action): bool
// + canViewAny / canView / canCreate / canEdit / canDelete / canDeleteAny
//   canForceDelete / canForceDeleteAny / canRestore (maps to 'edit') / canRestoreAny
```

There is no `canCreateAny` or `canEditAny` on this trait — do not assume Filament's full `can*` surface is overridden; only the methods listed above exist. `canRestore` and `canRestoreAny` map to the `edit` permission; `canForceDelete` and `canForceDeleteAny` map to `delete`. Every resource `use`s this trait for its own CRUD gating — there is no opt-out.

**Correction — `app/Policies/` is not actually empty.** `app/Policies/CorrespondencePolicy.php` exists, defining `view()` and `forceDelete()` for the `Correspondence` model. It is not registered in any service provider and nothing in the codebase calls `Gate::allows`, `->can(`, `@can`, or `authorize()` against it — it appears to be dead/unwired code (Laravel's policy auto-discovery convention would pick it up by name if something ever did call `$user->can('view', $correspondence)`, but no such call was found). `HasResourcePermissions` remains the trait every Filament resource actually relies on for its own `can*` checks; treat the "no Policies" claim below as "no Policies are wired into Filament resource authorization," not "the directory is empty." Flag for human review if `CorrespondencePolicy` should be deleted or genuinely wired up.

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

Only the `value` field/entry needs it (`key` is always a plain string, so `TextInput::make('key')` has no `formatStateUsing`):

```php
->formatStateUsing(fn($state) => is_string($state) ? $state : json_encode($state, JSON_UNESCAPED_UNICODE))
```

**This `formatStateUsing` on `value` is mandatory.** `EntityAttribute.value` is JSON-cast, so on read Eloquent returns arrays/scalars, not strings. Without it, `Textarea::make('value')` and `TextEntry::make('value')` try to render an array and throw.

Operational resources use the **Tab** variant (`getExtraAttributesFormTab`); the `Section` variant exists only for back-compat and must not be used in new resources.

### 1.7 `HandleActivation` — master `is_active` bulk toggle

```php
protected static function getActivateBulkAction(): BulkAction
protected static function getDeactivateBulkAction(): BulkAction
```

Both execute `static::getModel()::whereIn('id', $records->pluck('id'))->update(['is_active' => 1|0])` and call `deselectRecordsAfterCompletion()`. Master resources only.

### 1.8 `ExportDefaults` — exporter classes (not resources)

```php
getFileName(Export): string  // "{app}-{MODEL}-{His}"
getQuery(): Builder          // parent::getQuery()->limit(1000)
getCompletedNotificationBody(Export)
```

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

1. **`ManageCustomAttributesAction::make(): Action`** (`App\Filament\Actions`) — `KeyValue::make('attributes')` modal. `fillForm` from `$record->getCustomAttributesMap()`. Syncs via `$record->customAttributes()->whereNotIn('key', …)->delete()` + `updateOrCreate(['key'=>…],['value'=>…])`. Operates on `customAttributes()`.
2. **`HasExtraAttributesManagement` Repeater** — inline form tab bound to `extraAttributes()` via `->relationship()`.

Both write to the same rows; the alias separation prevents closure binding conflicts. Do not collapse them into one.

### 1.10 Virtual-tab `->dehydrated(false)` EAV pattern (InvoiceForm)

The canonical pattern for any EAV-backed form tab. Verified in `ShipmentInvoiceForm`:

- The whole invoice tab (`getInvoiceFormTab()`) is built from `_inv_*` fields that ALL carry `->dehydrated(false)`. None touch the Shipment Eloquent model on save — they are scratch UI state.
- Persistence is explicit via `Section::footerActions()` (Save Invoice + Download PDF) calling `static::persistInvoiceToEav(Get $get, $record)`.
- `persistInvoiceToEav()` packs all `_inv_*` state into a structured array and writes it as a SINGLE `EntityAttribute` row with `key='commercial_invoice'`:

```php
EntityAttribute::where('entity_type', Shipment::class)
    ->where('entity_id', $record->id)
    ->where('key', 'commercial_invoice')
    ->first();
// then ->update(['value'=>$data,'updated_by_id'=>auth()->id()]) OR ->create([...])
```

- Hydration lives in the Edit page's `mutateFormDataBeforeFill`, NOT in the form.

**Recipe for any new EAV-backed form tab:** `->dehydrated(false)` on every field + explicit footer-action save + page-mutator hydration. Do not mix `->dehydrated(true)` EAV fields with the model's own columns in the same tab.

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

### 1.13 Caching — `SmartCacheManager` + `DashboardStats`

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

### 1.14 General shared components

`App\Filament\Resources\General\`

- **`FormComponents::getAttachmentsField(): FileUpload`**

```php
FileUpload::make('attachments')
    ->multiple()->disk('public')->visibility('public')
    ->previewable()->openable()->live()->columnSpanFull()->downloadable()
    ->hintIconTooltip(...)
    ->rules(new ValidAttachment())
    ->acceptedFileTypes(ValidAttachment::ALLOWED_TYPES)
    ->maxSize(ValidAttachment::MAX_SIZE_KB)
// saveUploadedFileUsing  → app(FileUploadManager::class)->storeTemporary($file)
// saveRelationshipsUsing → FileUploadManager->processTemporaryFiles($record, $state)->refreshComponent($record, $set)
// afterStateHydrated     → from $record?->attachments?->pluck('path')
```

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

### 1.16 Localization

Three locales only: `en`, `fa` (RTL), `fr`. Path `lang/{locale}/resources/{camelCaseResource}/strings.php`. Top-level key structure:

```
general   → model_label, plural_model_label, navigation_group, enum labels
form      → field labels, validation, helpers; tab labels tab_general / tab_items
table     → column labels
filters   → filter labels
infolist  → entry labels; tab labels tab_general / tab_items / tab_documents
```

Referenced in code as `__('resources/{camelCaseResource}/strings.{section}.{key}')`. Tab labels always carry the `tab_` prefix.

**RULE: when adding a new form tab, add the `tab_*` key to ALL THREE locale files simultaneously.**

`maybeJalali($component)` helper (`app/Utils/helpers.php`) wraps any `DatePicker`; enables Jalali mode only when `session('calendar_type', app()->isLocale('fa') ? 'jalali' : 'gregorian') === 'jalali'`. Omitting it gives Gregorian calendars to `fa` users who selected Jalali — every date picker on every operational resource must pass through `maybeJalali()`.

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
- **Correspondence** — full `List`/`Create`/`Edit` pages, `CorrespondenceExporter`, `Enums/Priority.php` + `Type.php`, but NO EAV tab, NO totals, NO RelationManagers.
- **Target** — operational placement, but uses the master-style single `ManageTargets` page (no `List`/`Create`/`Edit`). Has a full editable `Traits/Form.php`, `Enums/Status.php`, `Exports/TargetExporter.php`, NO RelationManagers, NO totals, NO EAV tab. This is the documented exception to the Operational=multi-page / Master=single-page dichotomy.

| | Operational — canonical 8 | Operational — variants (Correspondence, Target) | Master |
|---|---|---|---|
| Pages | List + Create + Edit + (View via modal) | Correspondence: List+Create+Edit; Target: single `ManageTargets` | single `ManageXxx` page |
| Form | full editable form in Tabs (General + EAV) | editable form, NO EAV tab | full editable `form()`, no Tabs/EAV — used by the header `CreateAction` and by the row `EditAction` (which opens as a **modal**, since there is no dedicated edit route) |
| Header actions | Create button | Correspondence: Create; Target: Create (`ManageTargets::getHeaderActions()` returns `[CreateAction::make()]`, same as Correspondence) | `CreateAction` in header for **10 of 11** Master resources — verified in `ManageBanks`, `ManageCategories`, `ManageCompanies`, `ManageCurrencies`, `ManageNotificationSettings`, `ManagePermissions`, `ManageProducts`, `ManageRoles`, `ManageStatuses`, `ManageUsers`. **`EntityAttributeResource` is the sole exception** — `ManageEntityAttributes::getHeaderActions()` returns `[]` and `EntityAttributeResource` has no `form()` method at all (genuinely view-only via infolist). Do not generalize "Master = no header actions" from that one case. |
| Traits | `HasExtraAttributesManagement` + `Total{Name}Calculation` | neither EAV nor totals | `HandleActivation` (bulk activate/deactivate), no EAV, no totals |
| RelationManagers | yes (see §1.20) | no | no |
| Exporter | yes (see §1.23) | yes | varies |

Master resources: Bank, Category, Company, Currency, EntityAttribute, NotificationSetting, Permission, Product, Role, Status, User.

**Correction — Master ≠ "no form."** The blanket claim "Master resources: no form — view-only via infolist" (also stated in `CLAUDE.md`) does not hold for 10 of the 11 Master resources. Only `EntityAttributeResource` is truly form-less/view-only. Every other Master resource (verified: `BankResource`, `CategoryResource`, `ProductResource`, `UserResource`) defines a real `form(Schema $schema): Schema` method, wired to a header `CreateAction` and a row `EditAction`. What Master resources genuinely lack is dedicated `Create`/`Edit` **pages/routes** — creation and editing both happen through Filament's built-in modal, driven by the single `ManageXxx` page's `getHeaderActions()` / `recordActions()`. "No form" and "no create/edit routes" are different claims; only the latter is universally true of Master resources.

### 1.19 Custom Filament Page base classes (`App\Filament\Pages\*`)

Every List/Create/Edit/Manage page extends a project-local base, never Filament's directly. Each base adds a no-op `#[On('calendar-toggled')] calendarToggled()` listener so the `CalendarToggle` Livewire component refreshes pages without per-page wiring. `ManageRecords` additionally `use PrefillsTableSearch`.

```php
// app/Filament/Pages/EditRecord.php
class EditRecord extends BaseEditRecord
{
    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}

// app/Filament/Pages/ManageRecords.php
class ManageRecords extends BaseManageRecords
{
    use PrefillsTableSearch;

    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
```

`PrefillsTableSearch::mount()` reads the `?search=` query param and seeds `$this->tableSearch`, so deep links open the table already filtered:

```php
// app/Filament/Traits/PrefillsTableSearch.php
public function mount(): void
{
    parent::mount();
    if ($term = request()->query('search')) {
        $this->tableSearch = $term;
    }
}
```

Every operational page (`ListPurchaseRequests extends ListRecords`, `CreatePurchaseRequest extends CreateRecord`, `EditPurchaseRequest extends EditRecord`, `EditShipment extends EditRecord`) and every master page (`ManageBanks extends ManageRecords`, `ManageEntityAttributes extends ManageRecords`) goes through these bases. Extending Filament's base directly silently drops the calendar refresh and the search prefill.

### 1.20 RelationManager conventions

**25 RelationManagers** exist (not 14 — recounted directly from `app/Filament/Resources/**/RelationManagers/*RelationManager.php`), following one template. Verified against the actual `PurchaseRequestResource/RelationManagers/ProformaInvoicesRelationManager.php`:

```php
class ProformaInvoicesRelationManager extends RelationManager
{
    use ProformaInvoiceTable;      // aliased Table trait from the sibling resource
    use ProformaInvoiceFilters;    // aliased Filters trait from the sibling resource

    protected static string $relationship = 'proformaInvoices';

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
                    ->visible(fn(): bool => in_array($this->getOwnerRecord()->status?->english_name, ['Authorized', 'Conditional']))
                    ->url(fn(): string => ProformaInvoiceResource::getUrl('create', ['purchase_request_id' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()->url(fn($record): string => ProformaInvoiceResource::getUrl('edit', ['record' => $record])),
                    DetachAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([BulkActionGroup::make([
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
                ExportBulkAction::make()->exporter(ProformaInvoiceExporter::class),
            ])])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('proforma_invoices.id', 'desc');
    }
}
```

Rules:
- `use` the related resource's `Table` and `Filters` traits (aliased) — reuse columns/filters verbatim; call the individual `show*()`/`get*Filter()` methods explicitly in `->columns([...])`/`->filters([...])`, do not redefine them inside the RM.
- `infolist()` delegates to the sibling resource's `infolist()`.
- The header create `Action` is gated by the OWNER record's status (`english_name` against an allowed list) — never unconditional.
- Record actions go in an `ActionGroup`: `ViewAction`, `EditAction` with `->url()`, `DetachAction`, `DeleteAction`.
- Toolbar: `BulkActionGroup` with `DeleteBulkAction`, `RestoreBulkAction`, `ExportBulkAction->exporter(XxxExporter::class)`.
- Common tail: `->striped()->recordUrl(null)->defaultSort(...)`. `->searchDebounce('1000ms')->reorderableColumns()` appear on some RMs (verified in `PaymentResource/RelationManagers/PurchaseOrderRelationManager`, `ShipmentResource`'s and `CustomResource`'s RMs) but are **not universal** — `ProformaInvoicesRelationManager` above doesn't use them. Don't treat that pairing as mandatory; match the closest sibling RM instead.

**Read-only variant** (`PaymentResource/RelationManagers/PurchaseOrderRelationManager`): set `protected bool $canAssociate/$canCreate/$canDelete/$canDissociate/$canEdit = false` and override `getRelationship()` to scope by `targetable_type === PurchaseOrder::class` for polymorphic targetables.

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

### 1.23 Exporter skeleton

9 exporters follow one shape:

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

`ExportDefaults` provides `getCompletedNotificationBody`, `getFileName` (`{app}-{MODEL}-{His}`), and `getQuery()` (`parent::getQuery()->limit(1000)`). Array/relation columns use `->state(fn($record) => ...->implode(' | '))` for array-to-string export.

**Gotcha:** `PurchaseRequestExporter` overrides `getFileName(Export)` with its own `"PurchaseRequests-{key}"` shape, conflicting with `ExportDefaults::getFileName`. Pick one shape per exporter — do not leave both active.

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

`app/Providers/Filament/DashboardPanelProvider.php`. Beyond `discoverResources`, it wires: `CustomLogin::class`; 5 navigation groups all `->collapsed()`; color palette (Rose/Blue/Slate/Emerald/Orange); `IranYekan` font for `fa` else `Roboto` via `LocalFontProvider` + Vite; `databaseNotifications()` with 30s polling; `maxContentWidth(Width::Full)`; `spa()`; globalSearch with `Ctrl+K`/`Cmd+K` keybindings; `sidebarCollapsibleOnDesktop()`; `authMiddleware` includes `EnsureUserIsActive::class`; `defaultThemeMode(ThemeMode::Dark)`; favicon + brandLogo via Vite. Touch panel-level config here, not in resources.

### 1.26 Bootstrap — `AppServiceProvider`, Configurators, Macro provider, Observers

`AppServiceProvider::boot()` is the single Filament wiring point, delegating to four `App\Configurators\*` classes:

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    FilamentCustomLogin::configure($this->app);
    LanguageSwitcher::configure();
    FilamentAssets::register();
    FilamentRenderHooks::configure();

    Category::observe(CategoryObserver::class);
    PurchaseRequest::observe(PurchaseRequestObserver::class);
}
```

| Configurator | Job |
|---|---|
| `FilamentCustomLogin::configure($app)` | binds `LoginResponseContract` → `FilamentLoginResponse` (post-login redirect) |
| `LanguageSwitcher::configure()` | `LanguageSwitch::configureUsing(...)`, render hook `GLOBAL_SEARCH_BEFORE`, locales `config('language-switch.locales', ['fa','en','fr'])`, flags are 3 explicit `Vite::asset(...)` entries (`flags/iran.svg`, `flags/usa.svg`, `flags/france.svg`), `flagsOnly()` |
| `FilamentAssets::register()` | `FilamentAsset::register([Css::make('fi-custom-css', Vite::asset('resources/css/fi-custom.css'))])` |
| `FilamentRenderHooks::configure()` | `FilamentView::registerRenderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER, fn() => view('filament.partials.calendar-toggle'))` |

The render-hook slot choice is intentional: switcher `GLOBAL_SEARCH_BEFORE`, calendar `GLOBAL_SEARCH_AFTER`. Each configurator is a final class with a single public static entry (`configure()` / `register()`); add a new one by creating a file and calling it from `AppServiceProvider::boot()` in this same block.

`FilamentMacroServiceProvider::boot()` registers two Filament macros: `Field::macro('tooltip', ...)` (wraps `hintAction` with an info icon) and `DatePicker::macro('adaptive', ...)` (returns `$this->jalali()` when the session calendar is Jalali — the in-chain sibling of the `maybeJalali()` helper, used by `Filters.php` DatePickers).

Observers — two registration paths, do not mix:
1. **Manual** (`AppServiceProvider::boot()`): `Category::observe(CategoryObserver::class)`, `PurchaseRequest::observe(PurchaseRequestObserver::class)`. Use for side-effect observers (closure-table sync, status cascade).
2. **Auto** (`NotificationServiceProvider::boot()`): scans `app/Models/` for classes with a `SCANNABLE_TABLE` const and attaches `NotificationDispatcher` (which delegates to `NotificationEvaluator`). 8 models auto-wired: `BankProfile`, `Custom`, `Payment`, `ProformaInvoice`, `PurchaseOrder`, `PurchaseRequest`, `RegisteredOrder`, `Shipment`. Use for notification-only observers — declaring `SCANNABLE_TABLE` is enough, no `AppServiceProvider` edit.

A side-effect observer registered through the auto path will not run its side effects (it gets `NotificationDispatcher` instead); a notification-only observer registered manually is redundant. Match the path to the observer's job.

## 2. Developer Decision Matrix

| When you need to… | Do this… | Why… |
|---|---|---|
| Add a new operational resource | Create `app/Filament/Resources/{Name}Resource.php` (namespace `App\Filament\Resources`) + `Operational/{Name}Resource/Traits/{Form,Table,Infolist,Filters,Total{Name}Calculation}.php` + `Pages/List{Name}.php`, `Create{Name}.php`, `Edit{Name}.php`. Root `use`s all traits + `HasResourcePermissions` + `HasExtraAttributesManagement`. | `discoverResources` only sees root-level classes; the `Operational/` subtree is imported, not auto-registered. |
| Add a new master resource | Same root-class placement, but `Master/{Name}Resource/Traits/{Table,Infolist,Filters,Form}.php` + single `Pages/Manage{Name}.php`. Root `use`s `HandleActivation` (not `HasExtraAttributesManagement`). Header actions include `CreateAction::make()` (only `EntityAttributeResource` returns `[]`); edit happens via the row `EditAction` opening as a modal. | Master resources have no dedicated create/edit **routes/pages** (all CRUD happens through the single `ManageXxx` page's modals) and no EAV tab — but they do have a real `form()` unless the resource is truly view-only like `EntityAttributeResource`. |
| Add a form field | Add `getXxxField()` to the resource's `Form` trait; call it from `form()`. Translate the label in all 3 locale files. | Field internals live in traits, not in the root `form()` method. |
| Add a table column | Add `showXxx()` to `Table` trait; call from `table()`. | Same trait-driven composition. |
| Add an infolist entry | Add `viewXxx()` to `Infolist` trait; call from `infolist()`. | Same trait-driven composition. |
| Add a filter | Add `getXxxFilter()` to `Filters` trait; call from `table()`. | Same trait-driven composition. |
| Add a form tab | Insert a `Tab::make(__('…form.tab_xxx'))` in the root `Tabs::make(...)`, BEFORE `static::getExtraAttributesFormTab()`. Add `tab_xxx` to all 3 locale files. | The Extra Attributes tab is always last; `tab_*` keys must exist in all locales. |
| Persist EAV from a form tab | Mark every field `->dehydrated(false)`; persist via `Section::footerActions()` calling a `persistXxxToEav(Get $get, $record)` that writes ONE `EntityAttribute` row. Hydrate in the Edit page's `mutateFormDataBeforeFill`. | The canonical virtual-tab pattern (see InvoiceForm). Keeps EAV state out of the Eloquent model's save cycle. |
| Show a count on a tab label | `Tab::make(fn($record) => tabBadge(__('…tab_xxx'), $record?->relation->count() ?? 0, 'info'))` | `tabBadge` is the global helper; `.tb-badge` CSS already exists. |
| Cache an expensive query | `SmartCacheManager::remember('{ModelName}', $filters, $minutes, fn() => …)`; bust with `SmartCacheManager::invalidate('{ModelName}')` in the relevant observer. | Model-keyed registry enables bulk invalidation + navigation cache clear. |
| Add a navigation badge | Override `getNavigationBadge(): ?string` returning `null` when count is 0 (NOT `'0'`), 150-minute TTL, per-user `user_id` in filters. `getNavigationBadgeColor(): ?string` → `'info'`. | Empty badges must not render; per-user cache key reflects global count (intentional). |
| Eager-load relations | Add `->with([...])` and `->withCount([...])` to `getEloquentQuery()` only. | Avoids N+1; keeps field/column defs free of query concerns. |
| Gate an action | Rely on `HasResourcePermissions` — do not write a new Policy. Permission string is `{snake_singular_model}.{action}`. | The trait is what every Filament resource actually uses for its `can*` checks; the one existing Policy (`CorrespondencePolicy`) is unwired and should not be treated as precedent. |
| Add a date picker | Wrap with `maybeJalali($component)` so `fa` users with Jalali session get the Jalali calendar. | Without it, fa users get Gregorian regardless of session. |
| Cross-resource relation badge in a table | Use `TableComponents::show{Relation}()` — do not reinvent. | Shared component; toggleable, hidden by default, search-all wired. |
| Cross-resource relation entry in infolist | Use `InfoComponents::view{Relation}()` — visible only when non-empty. | Shared component; avoids duplication across 8 resources. |
| Attachments field | Use `General\FormComponents::getAttachmentsField()` — never write a `FileUpload` from scratch. | Handles temp→permanent pipeline via `FileUploadManager`. |

## 3. Absolute Anti-Patterns (Do Not Do This)

- ❌ **Creating a Policy class for a Filament-managed model.** `HasResourcePermissions` is the access-control system Filament resources actually use. Note: `app/Policies/CorrespondencePolicy.php` already exists as an exception — it is unregistered and unreferenced anywhere (no `Gate::`, `->can(`, `@can`, or `authorize()` call was found against it), so it appears to be dead code rather than an active second system. Don't add to it without wiring it up deliberately, and don't assume `app/Policies/` is empty.
  - Why: if a Policy for a Filament-resource model IS ever registered/auto-discovered, Filament would resolve it ahead of the trait's `can*` methods, silently changing access semantics.

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

- ❌ **Omitting `maybeJalali()` on a date picker.**
  - Why: `fa` users who selected Jalali in the CalendarToggle get Gregorian calendars otherwise.

- ❌ **Adding a `tab_*` key to only one or two locale files.**
  - Why: missing locales render the raw key. All three (`en`, `fa`, `fr`) must receive the key simultaneously.

- ❌ **Removing `user_id` from the navigation-badge `SmartCacheManager::remember` filter array.**
  - Why: the per-user cache key is intentional even though the callback counts all rows. "Fixing" this causes cache-key collisions across users.

- ❌ **Hardcoding colors that have a `--custom-*` or `--google-*` CSS variable.**
  - Why: the design-system tokens are the single source of truth; hardcodes drift.

- ❌ **Re-inventing glassmorphism / 3D / shimmer utilities inline.**
  - Why: landing-page CSS owns these; duplicates rot. (Note: the enterprise redesign removed most of these from the landing page — do not reintroduce them there either.)

- ❌ **Extending Filament's `ListRecords` / `CreateRecord` / `EditRecord` / `ManageRecords` directly.**
  - Why: the project bases (`App\Filament\Pages\*`) add the `#[On('calendar-toggled')]` refresh and `PrefillsTableSearch` deep-link prefill. Extending Filament's base directly silently drops both.

- ❌ **Declaring `mutateFormDataBeforeFill()` on a page that also `use`s a trait declaring it.**
  - Why: PHP resolves the class method over the trait, so the trait's fill hydration is silently skipped. Verified hazard — `EditShipment` declares its own `mutateFormDataBeforeFill` for commercial-invoice hydration AND `use`s `HandlesDocumentChecklistForm` which also declares it, so the doc-checklist fill is skipped. Consolidate into ONE place.

- ❌ **Overriding `getFileName()` on an exporter that `use`s `ExportDefaults` without dropping the trait's `getFileName`.**
  - Why: both shapes collide. Pick one per exporter — either rely on `ExportDefaults::getFileName` (`{app}-{MODEL}-{His}`) or override cleanly and document why.

## 4. Naming conventions

- **Resource root class**: `{Name}Resource` at `app/Filament/Resources/{Name}Resource.php`, `namespace App\Filament\Resources`.
- **Per-resource traits**: `app/Filament/Resources/Operational/{Name}Resource/Traits/{Form,Table,Infolist,Filters}.php`; `Total{Name}Calculation.php` (PurchaseRequest uses `TotalCostCalculation`).
- **Trait method prefixes**: `getXxxField()` (form field) · `showXxx()` (table column) · `viewXxx()` (infolist entry) · `getXxxFilter()` (filter).
- **Pages**: `List{Name}.php` / `Create{Name}.php` / `Edit{Name}.php` (Operational); `Manage{Name}.php` (Master).
- **Exporters**: `app/Filament/Resources/Operational/{Name}Resource/Exports/{Name}Exporter.php`, `use ExportDefaults`.
- **Enums**: `app/Filament/Resources/Operational/{Name}Resource/Enums/Status.php`.
- **Shared components**: `App\Filament\Resources\General\{FormComponents,InfoComponents,TableComponents}` — methods `getAttachmentsField()`, `show{Relation}()`, `view{Relation}()`.
- **Shared traits**: `App\Filament\Traits\{HasResourcePermissions,HasExtraAttributesManagement,HandleActivation,ExportDefaults}`.
- **Permission strings**: `{snake_singular_model}.{view|create|edit|delete}`.
- **Locale keys**: `lang/{locale}/resources/{camelCaseResource}/strings.php` with top-level groups `general` / `form` / `table` / `filters` / `infolist`; tab labels prefixed `tab_`.
- **EAV virtual-tab fields**: prefix with `_inv_` (InvoiceForm precedent); never collide with real Eloquent columns.
- **Navigation group keys**: `operational_first` / `operational_second` / `operational_third` / `operational_fourth` / `base`.
- **Page bases**: `App\Filament\Pages\{ListRecords, CreateRecord, EditRecord, ManageRecords}` — required parents for every resource page; never extend Filament's base directly.
- **RelationManagers**: `app/Filament/Resources/{Operational,Master}/{Name}Resource/RelationManagers/{Related}RelationManager.php`, `extends RelationManager`, `protected static string $relationship`.
- **Cross-resource prefill traits**: `Prepare{Child}From{Parent}` (Create-page `afterFill` dispatcher) and `UpdatesFrom{Parent}` (`populateFrom{Parent}($state, Set $set)` for Repeater aggregation), in the child resource's `Traits/`.
- **Total/calculation traits**: `Total{Name}Calculation` (canonical), with `Calculation` / `ItemCalculation` / `TotalAmountCalculation` / `ItemAmountCalculation` as named siblings; method `updateTotal(Get, Set)` or `updateTotalCost` / `updateComputations`.
- **Filament macros**: `Field::macro('tooltip', ...)`, `DatePicker::macro('adaptive', ...)` — registered in `FilamentMacroServiceProvider::boot()`.
- **Configurators**: `app/Configurators/{FilamentCustomLogin, LanguageSwitcher, FilamentAssets, FilamentRenderHooks}.php` — each a final class with one static entry, wired from `AppServiceProvider::boot()`.

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
- Every date picker passes through `maybeJalali($component)`.
- `getNavigationBadge()` returns `null` when the count is 0 — never `'0'`.
- `getNavigationGroup()` is a method returning the translated label, never a static `$navigationGroup` property.
- Every resource `use`s `HasResourcePermissions` for its own authorization. `app/Policies/CorrespondencePolicy.php` exists but is unwired/unreferenced — treat it as legacy/dead code, not a parallel access-control system, unless you deliberately wire it up.
- EAV-backed form tabs use the virtual-tab pattern: `->dehydrated(false)` on every field + explicit footer-action save + page-mutator hydration in `mutateFormDataBeforeFill`.
- The `customAttributes()` / `extraAttributes()` double-declaration on `HasCustomAttributes` is intentional. Do not collapse with `->as()`.
- `formatStateUsing` is mandatory on any `extraAttributes` Repeater value field and on any infolist `value` entry.
- Models with `SoftDeletes` require `->withoutGlobalScopes([SoftDeletingScope::class])` in `getEloquentQuery()`.
- Use design-system CSS tokens (`--custom-*`, `--google-*`) — never hardcode colors that already have a variable.
- Do not re-invent landing-page utilities (`.glass`, `.card-3d`, `.shimmer-effect`, etc.) inline — and note that the enterprise redesign removed most of them from the landing page; do not reintroduce them there.