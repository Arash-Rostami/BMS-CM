Verified against source on branch `master` (2026-07-25). Where this doc conflicts with CLAUDE.md, this doc is authoritative for the model + migration layer. `app/Filament/filamentPattern.md` is authoritative for the Filament-consumer side; this doc covers the model layer end-to-end plus the migration skeleton for those models.

# BMS-CM Model & Migration Pattern

Every new business model — and the migration that creates its table — must be composed exactly as documented here. The model layer is **trait-composition**: a cross-cutting set of `General` traits shared across models, plus a per-domain trait folder that mirrors the model 1:1. The single load-bearing rule is the **aliasing convention** (§2) — get it wrong and the class fatals on load. No base model classes, no inheritance, no presenters — the traits ARE the behavior. Constants (`SCANNABLE_TABLE`, `SCANNABLE_IDENTIFIER`, `TYPE_*`) live on the owning model, never on a shared base.

## Recommended structure

```
app/Models/
    {Model}.php                                ← root model, namespace App\Models
    Traits/
        General/                               ← 12 cross-cutting traits (shared by many models)
            Relationships.php  UserStamps.php  HasCustomAttributes.php
            Localization.php  HasScope.php  SellerEntity.php  HasSlug.php
            HasNameSearch.php  HasLocalizedAttributes.php  HasProductCategoryFormatting.php
            SearchTargetable.php  ModelInspector.php
        {Model}/                               ← per-domain folder, 1:1 with the model
            Relationships.php                  ← imported `as ExclusiveRelationships`
            HasSearchableRelations.php         ← scopeSearchAll()
            HasFormattedName.php               ← formatted_name / formatted_name_without_date
            HasComputedAttributes.php          ← BankProfile / Payment only
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
        'total_estimated_cost' => 'decimal:2',
    ];
}
```

Three near-universal conventions:
- `$fillable` ends with `'user_id', 'updated_by_id'` — the audit-stamp pair driven by `General\Relationships` + `UserStamps`.
- `General\Relationships` is imported unaliased; the per-domain `Relationships` is imported **`as ExclusiveRelationships`**. Mandatory (§2).
- The per-domain folder name matches the model class name 1:1 (`Traits\PurchaseRequest\` for `PurchaseRequest`).

Verified by grep across all 30 model files: **16** models import a per-domain `Relationships` aliased against `General\Relationships` (15 as `ExclusiveRelationships`; `Payment` drifts to the singular `ExclusiveRelationship` — see §13 Naming conventions). The remaining 14 fall into three groups:
- **No trait kit at all** — `Permission`, `Role` (extend Spatie base classes), `CorrespondenceRecipient` (extends `Illuminate\Database\Eloquent\Relations\Pivot`), `DeskReference` (plain `Model` + `HasFactory`, no audit/EAV traits).
- **Single, unaliased `Relationships`** — `Department`, `User` use only their own per-domain `Relationships` (no `General\Relationships`, no `UserStamps`, no collision so no alias needed). `ProformaInvoiceItem`, `PurchaseOrderItem`, `PurchaseRequestItem`, `RegisteredOrderItem` are the same shape (own `Relationships` + `SoftDeletes` only — no audit-stamp traits).
- **General kit without a colliding per-domain `Relationships`** — `Bank`, `NotificationSetting`, `EntityAttribute` (§5) compose `General\Relationships` + `UserStamps` directly with no `Traits/{Model}/Relationships.php` to collide with. `Company` has its own `Traits/Company/` folder (`HasCustomSorts`, `HasSearchableRelations`, `TypeScopes`) but no `Relationships.php` inside it, so still no collision.

Treat the skeleton in this section as the pattern for **operational, EAV-backed resources** (the 8 models in CLAUDE.md's operational groups) — accurate for all 8. Not a claim about every model in `app/Models/`.

## 2. The aliasing rule (load-bearing)

Two traits named `Relationships` are composed on the same model: `General\Relationships` (the audit `creator()`/`updater()`) and the per-domain `Relationships` (the domain relations). Importing two traits with the same short name without aliasing is a PHP fatal. The alias `as ExclusiveRelationships` is the resolution.

The same pattern repeats wherever a per-domain trait would collide with a General one — `Product` imports `Product\HasScope as HasExclusiveScope` alongside `General\HasScope`. A new model that imports two same-named traits without aliasing fatals on class load with "Trait method collision". Replicate the alias exactly; do not rename the per-domain trait.

## 3. General traits inventory (`App\Models\Traits\General\*`)

All 12 verified:

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
- **`HasComputedAttributes.php`** — BankProfile and Payment only; exposes `Attribute::make(get: fn() => …)` accessors listed in the model's `$appends`.

## 5. EAV model side

`HasCustomAttributes` (General) is the EAV entry point on the owning model. The double-declaration — two methods, both `morphMany(EntityAttribute::class, 'entity')`, no `->as()` — is intentional and prevents closure conflicts between `ManageCustomAttributesAction` and the `HasExtraAttributesManagement` Repeater (see `filamentPattern.md` §1.9). Do not collapse with `->as()`.

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

## 7. `SCANNABLE_TABLE` / `SCANNABLE_IDENTIFIER` constants

Verified `PurchaseRequest` declares both. `SCANNABLE_TABLE` is the model's table name; it is the marker `NotificationServiceProvider::boot()` scans `app/Models/` for to auto-attach a notification dispatcher (also read by `ModelInspector::getAvailableModels()` for the filter UI). `SCANNABLE_IDENTIFIER` is the human identifier column, read by `BaseModelEventNotification` for notification copy. A model that should raise model-event notifications declares `SCANNABLE_TABLE`; one that should not, omits it (and gets no auto-wired observer).

## 8. Boot-only lifecycle rule

Only two General traits define `boot*` methods: `UserStamps` (`bootUserStamps` → creating/updating) and `HasSlug` (`bootHasSlug` → saving). No other trait boots lifecycle hooks, and models do not define `boot()` themselves. Side effects (cascade status, closure-table sync) live in `app/Observers/` and are registered manually in `AppServiceProvider::boot()` — see `filamentPattern.md` §1.26 for the manual-vs-`SCANNABLE_TABLE`-auto split. Keep new behavior in scopes/accessors or observers, not in model `boot`.

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
- `$table->id()` PK → a business identifier (`pr_number`, `po_number`, `bp_number`, `shipment_no`) as `->unique()`.
- Foreign keys via `foreignId('x_id')->constrained('table')`. An explicit delete policy is the exception, not the rule, on non-pivot tables — the `purchase_requests` example above has none. `->cascadeOnDelete()` is the norm only on **pivot** FKs (§10); on regular operational FKs it's added ad hoc where the domain requires it. Nullable FKs use `->nullable()->constrained()` (status FK is always nullable).
- Money: `decimal('...', 15, 2)`. Rates/percentages: `decimal('...', 15, 5)` (or `(5,5)`).
- **`user_id` and `updated_by_id` are `unsignedBigInteger(...)->nullable()` with NO foreign-key constraint** — not `foreignId`. Deliberate: it lets a `User` be deleted without cascading into every stamped record. Reproduce exactly; do not "fix" by adding `->constrained('users')`.
- `timestamps()` + `softDeletes()` on every operational table.
- A composite index `['{fk}_id', 'deleted_at']` for every foreign key used in filtered/sorted queries; standalone `->index('{fk}_id')` for FKs not paired with `deleted_at`.
- `->comment('...')` on every non-obvious column.
- `down()` is always `Schema::dropIfExists('table_name')`.

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
- ❌ **Prefixing `scopeSearchAll` columns without the full table name.** — Ambiguous inside joined queries (see §4).
- ❌ **Deleting the commented-out `->orderBy($this->localeColumn())` line in `Localization::newQuery()`.** — Intentionally preserved (see §3).

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
