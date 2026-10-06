Verified against source on branch `master` (2026-07-25). Where this doc conflicts with CLAUDE.md, this doc is authoritative for the model + migration layer. `app/Filament/filamentPattern.md` is authoritative for the Filament-consumer side; this doc covers the model layer end-to-end plus the migration skeleton for those models.

# BMS-CM Model & Migration Pattern

Every new business model — and the migration that creates its table — must be composed exactly as documented here. The model layer is **trait-composition**: a cross-cutting set of `General` traits shared across models, plus a per-domain trait folder that mirrors the model 1:1. The single load-bearing rule is the **aliasing convention** (§2) — get it wrong and the class fatals on load. No base model classes, no inheritance, no presenters — the traits ARE the behavior. Constants (`SCANNABLE_TABLE`, `SCANNABLE_IDENTIFIER`, `TYPE_*`) live on the owning model, never on a shared base.

## Recommended structure

```
app/Models/
    {Model}.php                                ← root model, namespace App\Models
    Traits/
        General/                               ← 13 cross-cutting traits (shared by many models)
            Relationships.php  UserStamps.php  HasCustomAttributes.php
            Localization.php  HasScope.php  SellerEntity.php  HasSlug.php
            HasNameSearch.php  HasLocalizedAttributes.php  HasProductCategoryFormatting.php
            SearchTargetable.php  ModelInspector.php  TracksStatusHistory.php
        {Model}/                               ← per-domain folder, 1:1 with the model
            Relationships.php                  ← imported `as ExclusiveRelationships`
            HasSearchableRelations.php         ← scopeSearchAll()
            HasFormattedName.php               ← formatted_name / formatted_name_without_date
            HasComputedAttributes.php          ← BankProfile / Payment / RegisteredOrder
        Status/
            StatusFinder.php  HasSearchableRelations.php  Relationships.php
    EntityAttribute.php                        ← the EAV table (NOT a consumer of HasCustomAttributes)
    Status.php                                 ← composes Status\StatusFinder + the General kit
database/migrations/
    {active migrations}                         ← recent
    migrated/                                   ← archived operational schemas
```

## 1. Canonical model skeleton

Verified in `app/Models/PurchaseRequest.php`:

```php
use App\Models\Traits\General\HasCustomAttributes;
use App\Models\Traits\General\Relationships;
use App\Models\Traits\General\UserStamps;
use App\Models\Traits\PurchaseRequest\HasFormattedName;
use App\Models\Traits\PurchaseRequest\HasSearchableRelations;
use App\Models\Traits\PurchaseRequest\Relationships as ExclusiveRelationships;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseRequest extends Model
{
    use SoftDeletes,
        Relationships,
        ExclusiveRelationships,
        HasCustomAttributes,
        UserStamps,
        HasFormattedName,
        HasSearchableRelations;

    public const SCANNABLE_TABLE = 'purchase_requests';
    public const SCANNABLE_IDENTIFIER = 'pr_number';

    public const TYPE_PURCHASE_REQUEST = 'Purchase Request Status';

    protected $fillable = [
        'pr_number', 'requester_id', 'department_id', 'cost_center_id',
        'required_by_date', 'total_estimated_cost',
        // ...
        'user_id', 'updated_by_id',
    ];

    protected $casts = [
        'required_by_date' => 'date',
        'approval_date' => 'datetime',
        'total_estimated_cost' => 'decimal:5',
    ];
}
```

Three near-universal conventions:
- `$fillable` ends with `'user_id', 'updated_by_id'` — the audit-stamp pair driven by `General\Relationships` + `UserStamps`.
- `General\Relationships` is imported unaliased; the per-domain `Relationships` is imported **`as ExclusiveRelationships`**. Mandatory (§2).
- The per-domain folder name matches the model class name 1:1 (`Traits\PurchaseRequest\` for `PurchaseRequest`).

Verified by grep across all 30 model files: **17** models import a per-domain `Relationships` aliased against `General\Relationships` (15 as `ExclusiveRelationships`; `Payment` drifts to the singular `ExclusiveRelationship` — see §13 Naming conventions; `Department` joined this group 2026-09-26 when promoted to the master-resource audit kit). The remaining 13 fall into three groups:
- **No trait kit at all** — `Permission`, `Role` (extend Spatie base classes), `CorrespondenceRecipient` (extends `Illuminate\Database\Eloquent\Relations\Pivot`), `DeskReference` (plain `Model` + `HasFactory`, no audit/EAV traits).
- **Single, unaliased `Relationships`** — `User` uses only its own per-domain `Relationships` (no `General\Relationships`, no `UserStamps`, no collision so no alias needed). `ProformaInvoiceItem`, `PurchaseOrderItem`, `PurchaseRequestItem`, `RegisteredOrderItem` are the same shape (own `Relationships` + `SoftDeletes` only — no audit-stamp traits).
- **General kit without a colliding per-domain `Relationships`** — `NotificationSetting`, `EntityAttribute` (§5) compose `General\Relationships` + `UserStamps` directly with no `Traits/{Model}/Relationships.php` to collide with. (`Bank` moved out of this bucket in master since this paragraph's counts were last verified — it now has its own `Traits/Bank/Relationships.php` aliased `as ExclusiveRelationships`, added for `HasUsageGuard`'s inverse relations, same shape as `Company` below. The **17**/**15**/**13** counts above predate that and several other sibling-module merges (`Currency`, `Category`, and others already alias a per-domain `Relationships` too) and need a full re-grep across all current model files, not a one-line patch — flagged here rather than guessed at.)

`Company` has its own `Traits/Company/` folder (`HasCustomSorts`, `HasSearchableRelations`, `TypeScopes`, plus `Relationships` since the `HasUsageGuard` build — aliased `as ExclusiveRelationships` per §2, ten `hasMany` inverse relations for the operational models that reference a Company by FK: `proformaInvoicesAs{Seller,Buyer}`, `purchaseOrdersAs{Seller,Buyer}`, `registeredOrdersAs{Seller,Buyer}`, `paymentsAs{Payor,Payee}`, `bankProfiles`, `shipments` — these exist solely so `CompanyResource::usageRelations()` (see `filamentPattern.md`'s `HasUsageGuard` section) can count live references before a delete/deactivate).

Treat the skeleton in this section as the pattern for **operational, EAV-backed resources** (the 8 models in CLAUDE.md's operational groups) — accurate for all 8. Not a claim about every model in `app/Models/`.

## 2. The aliasing rule (load-bearing)

Two traits named `Relationships` are composed on the same model: `General\Relationships` (the audit `creator()`/`updater()`) and the per-domain `Relationships` (the domain relations). Importing two traits with the same short name without aliasing is a PHP fatal. The alias `as ExclusiveRelationships` is the resolution.

The same pattern repeats wherever a per-domain trait would collide with a General one — `Product` imports `Product\HasScope as HasExclusiveScope` alongside `General\HasScope`. A new model that imports two same-named traits without aliasing fatals on class load with "Trait method collision". Replicate the alias exactly; do not rename the per-domain trait.

## 3. General traits inventory (`App\Models\Traits\General\*`)

All 13 verified:

| Trait | Effect |
|---|---|
| `Relationships` | `creator(): BelongsTo` (User, `user_id`) + `updater(): BelongsTo` (User, `updated_by_id`) |
| `UserStamps` | `bootUserStamps`: `creating` → `user_id = auth()->id()`; `updating` → `updated_by_id = auth()->id()` (both guarded by `auth()->check()`, updating also by `isDirty()`) |
| `HasCustomAttributes` | EAV `morphMany` double-alias: `customAttributes()` + `extraAttributes()`, both `morphMany(EntityAttribute::class, 'entity')` (no `->as()`); `getCustomAttributesMap(): array` plucks `value,key`, JSON-encoding non-strings |
| `Localization` | `getLocalizedNameAttribute(): string` → `$this->{$this->localeColumn()} ?? ''` (empty-string fallback, not null); `localeColumn()` → `'name'` when `fa`, else `'english_name'`. Also overrides `newQuery()` with a commented-out `->orderBy($this->localeColumn())` line preserved verbatim — do not delete that commented line |
| `HasScope` | `scopeActive($query)` → `where('is_active', true)` |
| `SellerEntity` | three `belongsTo(Company::class, 'seller_id')` variants scoped by company type + `is_active=1`: `manufacturerCompanyExclusive()`, `sellerCompanyExclusive()`, `supplierCompanyExclusive()` |
| `HasSlug` | `bootHasSlug`: `saving` → builds `slug` from `english_name` via `Str::slug` with a numeric collision suffix (skips when `english_name` empty or unchanged on existing rows) |
| `HasNameSearch` | `scopeSearchByName(Builder, string)` over `name` / `english_name` |
| `HasLocalizedAttributes` | `getLocalizedAttribute(string)` resolves a column via the model's `$localizedAttributesMap[$base][$locale]` (fallback `en`, fallback to the base name); `__get` magic intercepts any `localized_*` key |
| `HasProductCategoryFormatting` | `getTargetableFormatted(string $format = 'table'): string` formats a polymorphic `targetable` (Product uses `customized_label` + an emoji; Category uses the localized name) for table/export contexts |
| `SearchTargetable` | `scopeSearchTargetable(Builder, string)` → `orWhereHasMorph('targetable', [Category::class, Product::class], …)` over `name`/`english_name` (+ `code` for Product) |
| `ModelInspector` | static introspection helpers for the notification/filter UI: `getAvailableColumns`, `getAvailableModels` (scans `app/Models` for `SCANNABLE_TABLE`), `getColumnValuesForSelectedColumns`, `getColumnsForSelectedTables`, plus `protected static resolveModelClass` and `private static findBestRelationForColumn`/`guessRelationships` |
| `HasReliableCodeGeneration` | added 2026-10-03, fixes the `CodeGenerator` race condition — see `servicesPattern.md`'s `CodeGenerator` section for the full mechanism. Overrides `save()`: wraps a NEW record's save in a real `DB::transaction($closure, 3)` (skipped if already updating, or already inside an ambient transaction) so the existing `lockForUpdate()` scan inside `generate()` finally has a transaction to lock within, plus an auto-retry for the one case (first code of a given day) where InnoDB only takes a non-blocking gap lock. Composed on the 8 models with a `CodeGenerator`-mapped column: `PurchaseRequest`, `ProformaInvoice`, `RegisteredOrder`, `PurchaseOrder`, `BankProfile`, `Payment`, `Shipment`, `Custom` — not Department, which deliberately has no DB-level uniqueness on `code` at all (§9/import-only generation). |

`filamentPattern.md` §1.11 tables the first six; this doc is the complete inventory.

## 4. Per-domain trait folder convention

Every domain model that has relations gets a `Traits\{Model}\` folder. The recurring members:

- **`Relationships.php`** — the domain's `BelongsTo`/`HasMany`/`BelongsToMany`/`MorphMany` relations. Verified `PurchaseRequest\Relationships` defines `approver`, `attachments` (morphMany `Attachment`, `attachable`), `costCenter`, `department`, `items`, `proformaInvoices`/`purchaseOrders`/`registeredOrders` (belongsToMany over the named pivot tables), `requester`, `status` (scoped — see §6).
- **`HasSearchableRelations.php`** — `scopeSearchAll($query, string $term)` aggregating `where` over the model's own columns + `orWhereRelation` over each searchable relation, both `name` and `english_name`:

```php
public function scopeSearchAll($query, string $term)
{
    return $query->where(fn($q) => $q
        ->orWhere('purchase_requests.pr_number', 'like', "%{$term}%")
        ->orWhereRelation('requester', 'name', 'like', "%{$term}%")
        // ...
    );
}
```

Column names are prefixed with the full table name (e.g. `purchase_requests.id`) to stay unambiguous in joined queries. This scope is the contract `TableComponents::show{Relation}()` searches through (`filamentPattern.md` §1.14).

- **`HasFormattedName.php`** — `getFormattedNameAttribute()` + `getFormattedNameWithoutDateAttribute()` (6 domains: Custom, ProformaInvoice, PurchaseOrder, PurchaseRequest, RegisteredOrder, Shipment). Verified `PurchaseRequest\HasFormattedName` builds a locale-aware string: `fa` puts the requester name first, others put the id first; a status emoji prefix for `Authorized`/`Conditional`; the cost-center/department localized name in parens; the date variant appends created + required-by dates via the `toPersianDate`/`toGregorianDate` helpers. This is the contract `TableComponents::show{Relation}()` renders via `$state?->formatted_name_without_date`.
- **`HasComputedAttributes.php`** — BankProfile, Payment, and RegisteredOrder; exposes `Attribute::make(get: fn() => …)` accessors listed in the model's `$appends`. (`PurchaseOrder` has the identical `total_amount`/`total_quantity` shape but under an undocumented drifted name, `Traits\PurchaseOrder\Accessors` — a pre-existing inconsistency, not a second convention; new models should use `HasComputedAttributes`.)

## 5. EAV model side

`HasCustomAttributes` (General) is the EAV entry point on the owning model. The double-declaration — two methods, both `morphMany(EntityAttribute::class, 'entity')`, no `->as()` — is intentional and prevents closure conflicts between `ManageCustomAttributesAction` and the `HasExtraAttributesManagement` Repeater (see `filamentPattern.md` §1.9). Do not collapse with `->as()`.

**`syncCustomAttributes(array $keyValueMap, ?int $userId = null): void`** is the only sanctioned way to write EAV rows — both `ManageCustomAttributesAction` and `HasExtraAttributesManagement`'s Repeater (via a `->saveRelationshipsUsing()` override) call it instead of touching `customAttributes()`/`create()`/`updateOrCreate()` directly. `entity_attributes` carries a real DB-level `UNIQUE(entity_type, entity_id, key)` index, and `EntityAttribute` is `SoftDeletes` — removing a key only soft-deletes its row, it never vanishes from that index. A naive `updateOrCreate(['key' => $key], [...])` (the default Eloquent query excludes trashed rows) or Filament's own relationship-Repeater `create()` path can't see the trashed row, so re-adding a previously-removed key attempts a raw `INSERT` and throws `SQLSTATE[23000]: 1062 Duplicate entry` on the composite index — reproduced live against MySQL. `syncCustomAttributes()` fixes this by looking the key up `withTrashed()` first: if a trashed row matches, `restore()` it and update the value; only a genuinely absent key gets a fresh `create()`. Do not reintroduce a direct `updateOrCreate`/Repeater-default write path against `customAttributes()`/`extraAttributes()` — it reopens this exact collision.

`EntityAttribute` is the EAV table itself — it does **not** consume `HasCustomAttributes`:

```php
class EntityAttribute extends Model
{
    use HasFactory, Relationships, SoftDeletes, UserStamps;

    protected $fillable = ['entity_type','entity_id','key','value','user_id','updated_by_id'];
    protected $casts = ['value' => 'json'];

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
```

The `value` column is JSON-cast. On read, Eloquent returns arrays/scalars (not strings) — this is why every Filament-side `extraAttributes` field needs the mandatory `formatStateUsing` (`filamentPattern.md` §1.6).

**`reservedCustomAttributeKeys(): array`** (default `[]`, override per-model) protects `EntityAttribute` keys that are written by a *different* mechanism than the Extra Attributes Repeater, from that Repeater's own sync. `syncCustomAttributes()`'s delete-sweep (`whereNotIn('key', ...)`) only ever knew about its own Repeater's key map — any other system-managed key on the same polymorphic `entity_id` (e.g. Shipment's `commercial_invoice`, written independently by `InvoiceForm`'s `persistInvoiceToEav()`) got silently deleted the instant a user added one real custom attribute and saved, because the sweep doesn't know that key belongs to someone else. Reproduced live 2026-10-05: 0 custom attributes present → the other key survives untouched (sweep has nothing to delete); 1 real custom attribute present → the other key gets wiped. Fixed by excluding `reservedCustomAttributeKeys()` from both the delete-sweep and the write-loop in `syncCustomAttributes()`, and from the map in `getCustomAttributesMap()`. `Shipment::reservedCustomAttributeKeys()` returns `['commercial_invoice']`. When adding a new system-managed EAV key on any model that also uses `HasExtraAttributesManagement`'s Repeater, add it to that model's override — don't assume a key written outside the Repeater is safe from it.

## 6. Status + `StatusFinder` + `TYPE_*` constants

`Status` is a shared polymorphic lookup (`type` / `english_type` + `name` / `english_name`). The `Status` model composes `Status\StatusFinder` + `Status\HasSearchableRelations` + the General kit — it has its own per-domain folder `Traits\Status\`.

The resolver lives in `App\Models\Traits\Status\StatusFinder`:

```php
public static function findBy(string $type, ?string $name = null): static|Collection|null
{
    $query = static::where('english_type', $type);
    if ($name) { $query->where('english_name', $name); return $query->first(); }
    return $query->get();
}
```

`TYPE_*` constants live on the OWNING models, never on `Status` — verified `PurchaseRequest::TYPE_PURCHASE_REQUEST = 'Purchase Request Status'`. The constant value is matched against `Status.english_type`, and the owning model's `status()` relation is itself scoped by the constant (verified in `PurchaseRequest\Relationships`):

```php
public function status(): BelongsTo
{
    return $this->belongsTo(Status::class)
        ->where('english_type', static::TYPE_PURCHASE_REQUEST);
}
```

Never hardcode status strings in model code — always `Status::findBy(Model::TYPE_X, 'SomeStatus')`.

## 6b. `StatusHistory` + `TracksStatusHistory` — append-only status-change audit log

`status_histories` is a generic, polymorphic (`morphs('statusable')`) log table capturing who changed a record's status column, from what `Status` id to what, and when. It is intentionally decoupled from any approval/permission logic — this is a pure audit trail, not a workflow gate.

`App\Models\StatusHistory` — a plain, non-`SoftDeletes` model (`const UPDATED_AT = null;`, Eloquent still manages `created_at` alone): `statusable()` (`MorphTo`), `status()` (`BelongsTo(Status::class, 'to_status_id')`), `fromStatus()` (`BelongsTo(Status::class, 'from_status_id')`), `user()` (`BelongsTo(User::class)` — the actor). `$fillable` covers `statusable_type`, `statusable_id`, `field`, `from_status_id`, `to_status_id`, `user_id`, plus a nullable `reason` column (added 2026-09-26, `text`) populated only by the "Return for Revision"/reject-style actions of the stage-driven approval workflow (`app/Services/servicesPattern.md`'s `StatusWorkflow`) — `NULL` for every ordinary forward transition logged by `TracksStatusHistory` below, which never sets it.

`statuses` itself carries two workflow-gate columns as of the same date: nullable `stage_order` (`unsignedSmallInteger`, a status's position within its own `english_type`; `NULL` = unordered/free, the default) and nullable `approval_permission` (`string`, a Spatie permission name gating who may set that status; `NULL` = ungated). Both are read exclusively by `StatusWorkflow`, never by `StatusFinder`/`TracksStatusHistory` — a `Status` row with both `NULL` behaves exactly as before this addition.

`App\Models\Traits\General\TracksStatusHistory` — the composing trait, one of three General traits (alongside `UserStamps`/`HasSlug`) allowed a `boot*` method (§8's exception is deliberate here, since the whole point of this trait is a lifecycle-driven side effect):

```php
public static function statusHistoryColumns(): array   // override per model — the status FK column name(s) to track
public static function withStatusHistoryReason(?string $reason): void   // sets a pending reason for the NEXT created/updated status-history row(s) this class writes
public function statusHistories(): MorphMany           // morphMany(StatusHistory::class, 'statusable')
protected static function bootTracksStatusHistory(): void
    // static::created(...)  logs an initial from-null row for each non-null tracked column
    // static::updated(...)  logs a from→to row for each tracked column that `wasChanged()`
```

Default `statusHistoryColumns()` is `['status_id']` — correct for `PurchaseRequest`, `RegisteredOrder`, `PurchaseOrder`, `Payment`, `Correspondence`, `BankProfile`. `Shipment` (5 status columns) and `Custom` (3 status columns) override it to return their full real column-name lists. `ProformaInvoice` has zero status columns and does not compose this trait. The actor is `auth()->id()` — `null` on a system/console-driven change, same convention as `UserStamps`.

**`withStatusHistoryReason()` (added 2026-09-26) is a `protected static ?string $pendingStatusHistoryReason` set on the trait itself** — since PHP gives each class that composes a trait its own independent copy of that trait's static properties, this is safely per-model, not a cross-model leak, without any extra bookkeeping (same spirit as `HandlesRecipients::$storedRecipients` in the Correspondence Filament module, but as a static rather than instance property since the caller here is a plain Action class, not a stateful Livewire page). A caller sets it immediately before the `save()`/`update()` call that will trigger the history row (e.g. `App\Filament\Actions\ReturnForRevisionAction`: `$record::withStatusHistoryReason($data['reason']); $record->update([...]);`), and both the `created` and `updated` boot closures read `static::$pendingStatusHistoryReason` into the new `StatusHistory` row's `reason` column, then reset it to `null` unconditionally at the end of the closure — so a reason never survives past the single write it was set for, and an ordinary status edit that never calls the setter always logs `reason: null` exactly as before this addition.

**`archiveAttachmentsIfTerminal()` (added 2026-09-26)** is a `protected static` helper on the same trait, called from the tail of `bootTracksStatusHistory()`'s `updated` closure for every tracked column that `wasChanged()`. It re-fetches the new `Status` row and asks `App\Services\StatusWorkflow::isTerminal($status)` (true only for a genuine ordered terminus — `stage_order` set AND `nextFor()` returns null); if true, it bulk-updates `$model->attachments()->update(['status_id' => $archivedId])` in a single `UPDATE ... WHERE` query — never a per-attachment loop. This is the shared, cross-module attachment-lifecycle hook (`App\Models\Attachment`'s `Uploaded`/`Superseded`/`Archived` states, see `filamentPattern.md` §1.14) — it fires for every model composing `TracksStatusHistory`, but is a true no-op for any model/`english_type` with no `stage_order` rows defined (currently everything except `PurchaseRequest`), so no per-module opt-in code is needed as new pipelines gain ordered stages. `method_exists($model, 'attachments')` guards models that might compose this trait without ever having attachments.

Consumed by the shared infolist tab `App\Filament\Resources\General\InfoComponents::getStatusHistoryTab()` — see `filamentPattern.md`'s EAV-tab section for the sibling convention. **Do not add `'statusHistories.*'` to a composing resource's `eagerRelations()`** — that array backs both list-page table queries and single-record view/edit queries, and `statusHistories` grows unbounded over a record's lifetime, so a blanket eager load would load full history on every list-page row for a relation only ever shown on this one infolist tab. The tab instead scope-loads it itself for the single record being viewed (`$record->statusHistories()->with(['status', 'fromStatus', 'user'])->latest()->get()`), keeping list pages free of the cost entirely.

## 7. `SCANNABLE_TABLE` / `SCANNABLE_IDENTIFIER` constants

Verified `PurchaseRequest` declares both. `SCANNABLE_TABLE` is the model's table name; it is the marker `NotificationServiceProvider::boot()` scans `app/Models/` for to auto-attach a notification dispatcher (also read by `ModelInspector::getAvailableModels()` for the filter UI). `SCANNABLE_IDENTIFIER` is the human identifier column, read by `BaseModelEventNotification` for notification copy. A model that should raise model-event notifications declares `SCANNABLE_TABLE`; one that should not, omits it (and gets no auto-wired observer).

## 8. Boot-only lifecycle rule

Only three General traits define `boot*` methods: `UserStamps` (`bootUserStamps` → creating/updating), `HasSlug` (`bootHasSlug` → saving), and `TracksStatusHistory` (`bootTracksStatusHistory` → created/updated, §6b). No other trait boots lifecycle hooks, and models do not define `boot()` themselves. Side effects (cascade status, closure-table sync) live in `app/Observers/` and are registered manually in `AppServiceProvider::boot()` — see `filamentPattern.md` §1.26 for the manual-vs-`SCANNABLE_TABLE`-auto split. Keep new behavior in scopes/accessors or observers, not in model `boot`.

## 8b. Numeric precision on price/quantity/rate/weight columns

Any column carrying real calculation precision (price, quantity, amount, rate, weight) is `decimal(65,5)` at the migration layer — MySQL's max integer-digit capacity at a fixed 5-decimal scale, chosen so no realistic figure can overflow — and `'decimal:5'` in `$casts` — never `decimal(x,2)`/`'decimal:2'`. Full standard (including the "Tables may round for display, nothing else may" rule and the `preciseNumber()` display helper) lives in `app/Utils/helpersPattern.md` §1b — don't duplicate it here.

## 9. Migration skeleton (operational tables)

Verified `database/migrations/migrated/2025_07_24_150031_create_purchase_requests_table.php`:

```php
Schema::create('purchase_requests', function (Blueprint $table) {
    $table->id();
    $table->string('pr_number')->unique();
    $table->foreignId('requester_id')->constrained('users');
    $table->foreignId('status_id')->nullable()->constrained('statuses');
    $table->unsignedBigInteger('user_id')->nullable();
    // ...
    $table->timestamps();
    $table->softDeletes();
    $table->index(['requester_id', 'deleted_at']);
});
```

Rules for every operational table:
- `$table->id()` PK → a business identifier (`pr_number`, `po_number`, `bp_number`, `shipment_no`). **Do NOT add `->unique()` at the migration/DB level on any column belonging to a `SoftDeletes` model.** MySQL's `UNIQUE` index has no concept of `deleted_at` — it still matches trashed rows, so soft-deleting a record and then creating a new one that reuses its identifying value throws a raw `SQLSTATE[23000]: 1062 Duplicate entry`, even though the app-level check (Filament's `->unique()` field validation, always required per `filamentPattern.md` §1.15 corollary) correctly allows it. Reproduced live and fixed 2026-09-19: 12 such DB-level constraints (`users.email`, `products.code`, `purchase_requests.pr_number`, `shipments.shipment_no`, `purchase_orders.po_number`, `proforma_invoices.invoice_no`, `registered_orders.ro_number` + `.official_registration_no`, `payments.payment_no`, `customs.custom_no`, `bank_profiles.bp_number`, `statuses(type, name)`) were dropped from both the live database and their migration source. Uniqueness for these columns is enforced **application-level only** now (Filament's `->unique(..., modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())`) — this trades a theoretical two-simultaneous-creates race condition (negligible for this internal admin tool, and most of these columns are auto-generated codes, not user-typed) for correctness against the far more common soft-delete-then-recreate path. Do not re-add a DB-level `->unique()` to a `SoftDeletes` model's column without also solving the soft-delete collision (e.g. a generated soft-delete-aware guard column) — adding it back as a bare `->unique()` reopens this exact bug.
- Foreign keys via `foreignId('x_id')->constrained('table')`. An explicit delete policy is the exception, not the rule, on non-pivot tables — the `purchase_requests` example above has none. `->cascadeOnDelete()` is the norm only on **pivot** FKs (§10); on regular operational FKs it's added ad hoc where the domain requires it. Nullable FKs use `->nullable()->constrained()` (status FK is always nullable).
- Money/quantity/weight: `decimal('...', 65, 5)`. Rates/percentages: `decimal('...', 65, 5)` (see §8b — both share the same 5-decimal-scale standard, at MySQL's max integer-digit capacity).
- **`user_id`/`updated_by_id` FK-or-not is genuinely split, verified against production (2026-09-17 sync)** — two coexisting shapes, both intentional: `purchase_requests`, `proforma_invoices`, `categories`, `targets` use bare `unsignedBigInteger(...)->nullable()`/`foreignId(...)->nullable()` with **no** `.constrained()`, letting a `User` be hard-deleted without touching stamped records. `payments`, `shipments`, `customs`, `bank_profiles`, `correspondences`, `purchase_orders`, `registered_orders` DO carry a real `.constrained('users')` (paired with `->nullOnDelete()` on `updated_by_id`, `->restrictOnDelete()`/`->nullOnDelete()` on `user_id` depending on the table). Match whichever shape the table already uses — do not blanket-assume "no FK" is universal, and do not add `.constrained('users')` to the no-FK group without confirming first (it's a real access/behavior change, not a formatting fix).
- `timestamps()` + `softDeletes()` on every operational table.
- A composite index `['{fk}_id', 'deleted_at']` for every foreign key used in filtered/sorted queries; standalone `->index('{fk}_id')` for FKs not paired with `deleted_at`. Two more composite shapes joined this convention 2026-09-17, evidence-backed against real query patterns in `AnalyticsService`/Filament filters rather than added speculatively: `['deleted_at', 'created_at']` on every operational table (every resource's date-range Filament filter combines with the ever-present soft-delete scope) and ad hoc date-pair composites where two nullable date columns are checked together in the same query (e.g. `payments(payment_date, payment_deadline)`, `purchase_requests(approval_date, required_by_date)`, `shipments(deleted_at, eta, exit_date)`) — add one of these only when you can point to the actual multi-column WHERE/ORDER BY in the code, not preemptively.
- `->comment('...')` on every non-obvious column.
- `down()` is always `Schema::dropIfExists('table_name')`.

**Plain (non-unique) indexes added 2026-09-22** on all 9 `CodeGenerator`-mapped business-identifier columns (`purchase_requests.pr_number`, `proforma_invoices.invoice_no`, `registered_orders.ro_number` + `.contract_no`, `purchase_orders.po_number`, `bank_profiles.bp_number`, `payments.payment_no`, `shipments.shipment_no`, `customs.custom_no`) via `database/migrations/2026_09_22_092707_add_business_identifier_indexes.php` — a plain `$table->index($column)`, deliberately **not** `->unique()` (see the bullet above; a unique constraint on these was already removed once for the exact soft-delete collision this would reopen). Turns `CodeGenerator::generate()`'s `WHERE {field} LIKE '...%' ... FOR UPDATE` from a full-table exclusive-lock scan into an index-range lock — load-bearing for bulk-import chunk concurrency (`filamentPattern.md` §1.8a), but speeds up every `generate()` call.

## 10. Pivot conventions

Six pivot tables connect the operational models: `proforma_invoice_purchase_request`, `proforma_invoice_purchase_order`, `proforma_invoice_registered_order`, `registered_order_purchase_request`, `registered_order_purchase_order`, `purchase_order_purchase_request`. Three shapes coexist (verified against all six migration files):

**Shape A — composite primary key, no `id()`, no timestamps, no `unique()`** (verified `purchase_order_purchase_request`, `proforma_invoice_purchase_order`):

```php
Schema::create('purchase_order_purchase_request', function (Blueprint $table) {
    $table->primary(['purchase_order_id', 'purchase_request_id']);
    $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
});
```

**Shape B — no `id()`, no primary key, `foreignId`×2 + `timestamps()` + `unique()`** (verified `proforma_invoice_purchase_request`, `proforma_invoice_registered_order`):

```php
Schema::create('proforma_invoice_purchase_request', function (Blueprint $table) {
    $table->foreignId('proforma_invoice_id')->constrained()->onDelete('cascade');
    $table->foreignId('purchase_request_id')->constrained()->onDelete('cascade');
    $table->timestamps();
    $table->unique(['proforma_invoice_id', 'purchase_request_id'], 'uidx_pi_pr');
});
```

**Shape C — `id()` + `foreignId`×2 + `timestamps()` + `unique()`** (verified `registered_order_purchase_request`, `registered_order_purchase_order`):

```php
Schema::create('registered_order_purchase_request', function (Blueprint $table) {
    $table->id();
    $table->foreignId('registered_order_id')->constrained('registered_orders')->cascadeOnDelete();
    $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['registered_order_id', 'purchase_request_id'], 'ro_pr_unique');
});
```

Only Shape C pivots (`registered_order_purchase_request`, `registered_order_purchase_order`) carry their own `id` column — any `Table` trait reused inside a RelationManager built over one of THOSE two pivots (e.g. `RegisteredOrderResource`'s PR/PO RelationManagers reusing `PurchaseRequestResource`/`PurchaseOrderResource`'s `showID()`) must qualify the column (`purchase_requests.id`, not bare `id`) or the join produces `SQLSTATE[42S22]` ambiguous-column; Shape A/B pivots have no `id()` so the same reuse is currently safe there, but qualify defensively anyway since a future migration could add one (`filamentPattern.md`'s anti-patterns list covers the Filament-side fix and full crash history).

**Convention for new pivots: use Shape C** (`$table->id()`, two `foreignId->cascadeOnDelete`, `timestamps()`, ONE `unique([...], '{a}_{b}_unique')`, plus `$table->index('{second_fk}')` if the second FK is queried standalone). It's the most complete shape and matches half of the existing pivots. Name the index `{modelA}_{modelB}_unique` in snake_case.

## 11. Developer Decision Matrix

| When you need to… | Do this… | Why… |
|---|---|---|
| Add a new business model | Create `app/Models/{Name}.php` + `Traits/{Name}/Relationships.php` imported `as ExclusiveRelationships`. `use SoftDeletes, Relationships, ExclusiveRelationships, UserStamps` + `HasCustomAttributes` (if EAV-backed) + per-domain `HasSearchableRelations` + `HasFormattedName`. End `$fillable` with `user_id`, `updated_by_id`. | The aliasing rule (§2) is mandatory; missing it fatals on load. |
| Add a status-bearing model | Declare `public const TYPE_{NAME} = '{Name} Status';` on the owning model; scope its `status()` relation by `->where('english_type', static::TYPE_{NAME})`. Resolve via `Status::findBy(static::TYPE_{NAME}, 'EnglishName')`. | `Status` is polymorphic + locale-keyed; the constant scopes the lookup. |
| Add a model that raises notifications | Declare `public const SCANNABLE_TABLE = '{table}';` (+ `SCANNABLE_IDENTIFIER`). No `AppServiceProvider` edit. | `NotificationServiceProvider` auto-wires a dispatcher for any model with the constant. |
| Add a side-effect observer | Create `app/Observers/{Name}Observer.php` + register `Model::observe(...)` in `AppServiceProvider::boot()`. Do NOT add `SCANNABLE_TABLE` for this. | The auto path attaches a notification dispatcher, not a side-effect observer. |
| Add a searchable column | Extend `scopeSearchAll` in the per-domain `HasSearchableRelations` (add the column or an `orWhereRelation`). | `TableComponents::show{Relation}()` searches through this scope. |
| Add a computed display attribute | Add `HasFormattedName` (or a `HasComputedAttributes` accessor in `$appends`) in the per-domain folder. | `TableComponents::show{Relation}()` renders `formatted_name_without_date`. |
| Add an EAV-backed model | `use HasCustomAttributes`; the Filament side then gets the `extraAttributes` tab via `HasExtraAttributesManagement`. | The double-alias is the contract; see `filamentPattern.md` §1.6/1.9. |
| Add an operational migration | Follow §9: `id` → `*_number unique`, `foreignId->constrained`, nullable status FK, `decimal(15,2)` money, `unsignedBigInteger` `user_id`/`updated_by_id` (no FK), `timestamps`+`softDeletes`, composite `[...,deleted_at]` indexes, `->comment()`. | The skeleton is uniform across all operational tables; deviating breaks the resource's eager-load + filter assumptions. |
| Add a pivot table | Use Shape C (§10): `id()`, two `foreignId->cascadeOnDelete`, `timestamps()`, one `unique([...], '{a}_{b}_unique')`. | Most complete of the three coexisting shapes; matches half the existing pivots. |
| Add a General trait | Keep it side-effect-free except via `boot{TraitName}`; if it adds relations, consumers MUST alias it to avoid the `Relationships` collision. | The aliasing rule (§2) is the only guard against trait-name collisions. |

## 12. Absolute Anti-Patterns (Do Not Do This)

- ❌ **Importing two traits with the same short name without `as` aliasing.** — PHP fatal trait collision (see §2).
- ❌ **Putting `TYPE_*` / `SCANNABLE_*` constants on `Status` or a shared base.** — They belong on the owning model, not a shared base (see §6, §7).
- ❌ **Adding `->constrained('users')` to `user_id` / `updated_by_id` in a migration.** — Deliberately unconstrained so User deletion doesn't cascade (see §9).
- ❌ **Booting lifecycle hooks in a model or a new trait instead of using an Observer.** — Only `UserStamps`/`HasSlug` boot; side effects belong in `app/Observers/` (see §8).
- ❌ **Collapsing the `customAttributes()` / `extraAttributes()` double-declaration with `->as()`.** — Breaks one of the two EAV consumers (see §5, `filamentPattern.md` §1.9).
- ❌ **Hardcoding status strings in model code.** — Use `Status::findBy(Model::TYPE_X, 'EnglishName')` (see §6).
- ❌ **Adding a DB-level `->unique()` to a column on a `SoftDeletes` model.** — MySQL's `UNIQUE` index ignores `deleted_at`; a soft-deleted row still blocks a new one reusing its value with a raw SQL 1062 error, even after the app-level Filament check is fixed. See §9.
- ❌ **Prefixing `scopeSearchAll` columns without the full table name.** — Ambiguous inside joined queries (see §4).
- ❌ **Deleting the commented-out `->orderBy($this->localeColumn())` line in `Localization::newQuery()`.** — Intentionally preserved (see §3).
- ❌ **Deleting or directly consuming `Payment::purchaseOrder()` / `Payment::registeredOrder()`.** — These bare `belongsTo(…, 'targetable_id')` hooks in `Payment\Relationships` are unscoped on `targetable_type` and exist solely as the Filament RM `$relationship` contract anchors (see `filamentPattern.md` §1.20 read-only variant); the RMs' `getRelationship()` overrides are the only real guard. Deleting them breaks the Payment RMs (a deletion once took down 3 tests).

## 13. Naming conventions

- **Model root**: `{Name}` at `app/Models/{Name}.php`, `namespace App\Models`.
- **General traits**: `app/Models/Traits/General/{TraitName}.php`, `namespace App\Models\Traits\General`, `trait {TraitName}`.
- **Per-domain traits**: `app/Models/Traits/{Name}/{TraitName}.php`, `namespace App\Models\Traits\{Name}`.
- **Per-domain `Relationships`**: always imported `as ExclusiveRelationships` at the model; the trait itself is named `Relationships`. One real-world drift: `Payment.php` aliases it as the singular `ExclusiveRelationship` — a naming inconsistency in the current codebase, not a convention; new models should use the plural.
- **Scopes**: `scopeSearchAll($query, string $term)` (per-domain `HasSearchableRelations`), `scopeActive($query)` (`HasScope`), `scopeSearchByName(Builder, string)` (`HasNameSearch`), `scopeSearchTargetable(Builder, string)` (`SearchTargetable`).
- **Accessors**: `getFormattedNameAttribute` / `getFormattedNameWithoutDateAttribute` (`HasFormattedName`), `getLocalizedNameAttribute` (`Localization`), `getTargetableFormatted(string)` (`HasProductCategoryFormatting`).
- **Boot methods**: `bootUserStamps` (`UserStamps`), `bootHasSlug` (`HasSlug`) — the only two.
- **Constants**: `SCANNABLE_TABLE` (table name, marker for auto-observer), `SCANNABLE_IDENTIFIER` (human id column), `TYPE_{NAME}` (Status `english_type` value, on the owning model).
- **Migrations**: `database/migrations/{timestamp}_create_{snake_plural}_table.php`; `down()` → `Schema::dropIfExists('{snake_plural}')`. Active migrations live in `migrations/`, archived operational schemas in `migrations/migrated/`.
- **Pivots**: `{modelA}_{modelB}` snake_case table; Shape C with index name `{a}_{b}_unique`.
