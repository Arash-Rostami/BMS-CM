Verified against source on branch `feature/landing-page-enterprise-redesign` (2026-07-18). Where this doc conflicts with CLAUDE.md, this doc is authoritative for the model + migration layer. `app/Filament/filamentPattern.md` remains authoritative for the Filament-consumer side; this doc covers the model layer end-to-end plus the database migration skeleton for those models.

# BMS-CM Model & Migration Pattern

Every new business model — and the migration that creates its table — must be composed exactly as documented here. The model layer is **trait-composition**: a cross-cutting set of `General` traits shared across models, plus a per-domain trait folder that mirrors the model 1:1. The single load-bearing rule is the **aliasing convention** (§2) — get it wrong and the class fatals on load. Future AI agents must reproduce this layout verbatim; deviations are bugs.

## Core idea

**Per-domain trait folder composed with cross-cutting General traits.** Each model `use`s a small `use` list: the shared audit/localization kit from `App\Models\Traits\General\*`, the EAV trait where applicable, and a per-domain `App\Models\Traits\{Model}\*` folder whose `Relationships` trait is imported **`as ExclusiveRelationships`** to avoid the trait-name collision with `General\Relationships`. There are no base model classes, no inheritance, no presenters — the traits ARE the behavior. Constants (`SCANNABLE_TABLE`, `SCANNABLE_IDENTIFIER`, `TYPE_*`) live on the owning model, never on a shared base.

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
    {active migrations}                         ← ~6 recent
    migrated/                                   ← ~45 archived operational schemas
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
        'required_by_date', 'total_estimated_cost', 'urgency_level',
        'status_id', 'approver_id', 'approval_date', 'rejection_reason', 'notes',
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
- `General\Relationships` is imported unaliased; the per-domain `Relationships` is imported **`as ExclusiveRelationships`**. This aliasing is mandatory (§2).
- The per-domain folder name matches the model class name 1:1 (`Traits\PurchaseRequest\` for `PurchaseRequest`).

27 of 28 model classes use this kit (only `Permission` does not).

## 2. The aliasing rule (load-bearing)

Two traits named `Relationships` are composed on the same model: `General\Relationships` (the audit `creator()`/`updater()`) and the per-domain `Relationships` (the domain relations). Importing two traits with the same short name without aliasing is a PHP fatal. The alias `as ExclusiveRelationships` is the resolution:

```php
use App\Models\Traits\General\Relationships;
use App\Models\Traits\PurchaseRequest\Relationships as ExclusiveRelationships;
```

The same pattern repeats wherever a per-domain trait would collide with a General one — `Product` imports `Product\HasScope as HasExclusiveScope` alongside `General\HasScope`. A new model that imports two same-named traits without aliasing fatals on class load with "Trait method collision". Replicate the alias exactly; do not rename the per-domain trait.

## 3. General traits inventory (`App\Models\Traits\General\*`)

All 12 verified:

| Trait | Effect |
|---|---|
| `Relationships` | `creator(): BelongsTo` (User, `user_id`) + `updater(): BelongsTo` (User, `updated_by_id`) |
| `UserStamps` | `bootUserStamps`: `creating` → `user_id = auth()->id()`; `updating` → `updated_by_id = auth()->id()` (both guarded by `auth()->check()`, updating also by `isDirty()`) |
| `HasCustomAttributes` | EAV `morphMany` double-alias: `customAttributes()` + `extraAttributes()`, both `morphMany(EntityAttribute::class, 'entity')` (no `->as()`); `getCustomAttributesMap(): array` plucks `value,key`, JSON-encoding non-strings |
| `Localization` | `getLocalizedNameAttribute(): string` → `$this->{$this->localeColumn()}`; `localeColumn()` → `'name'` when `fa`, else `'english_name'`. Also overrides `newQuery()` with a commented-out `->orderBy($this->localeColumn())` line preserved verbatim — do not delete that commented line |
| `HasScope` | `scopeActive($query)` → `where('is_active', true)` |
| `SellerEntity` | three `belongsTo(Company::class, 'seller_id')` variants scoped by company type + `is_active=1`: `manufacturerCompanyExclusive()`, `sellerCompanyExclusive()`, `supplierCompanyExclusive()` |
| `HasSlug` | `bootHasSlug`: `saving` → builds `slug` from `english_name` via `Str::slug` with a numeric collision suffix (skips when `english_name` empty or unchanged on existing rows) |
| `HasNameSearch` | `scopeSearchByName(Builder, string)` over `name` / `english_name` |
| `HasLocalizedAttributes` | `getLocalizedAttribute(string)` resolves a column via the model's `$localizedAttributesMap[$base][$locale]` (fallback `en`, fallback to the base name); `__get` magic intercepts any `localized_*` key |
| `HasProductCategoryFormatting` | `getTargetableFormatted(string $format = 'table'): string` formats a polymorphic `targetable` (Product uses `customized_label` + an emoji; Category uses the localized name) for table/export contexts |
| `SearchTargetable` | `scopeSearchTargetable(Builder, string)` → `orWhereHasMorph('targetable', [Category::class, Product::class], …)` over `name`/`english_name` (+ `code` for Product) |
| `ModelInspector` | static introspection helpers used by the notification/filter UI: `getAvailableColumns`, `getAvailableModels` (scans `app/Models` for `SCANNABLE_TABLE`), `getColumnValuesForSelectedColumns`, `getColumnsForSelectedTables`, plus private `resolveModelClass`/`findBestRelationForColumn`/`guessRelationships` |

`filamentPattern.md` §1.11 tables the first six; this doc is the complete inventory.

## 4. Per-domain trait folder convention

Every domain model that has relations gets a `Traits\{Model}\` folder. The recurring members:

- **`Relationships.php`** — the domain's `BelongsTo`/`HasMany`/`BelongsToMany`/`MorphMany` relations. Verified `PurchaseRequest\Relationships` defines `approver`, `attachments` (morphMany `Attachment`, `attachable`), `costCenter`, `department`, `items`, `proformaInvoices`/`purchaseOrders`/`registeredOrders` (belongsToMany over the named pivot tables), `requester`, `status` (scoped — see §6).
- **`HasSearchableRelations.php`** — `scopeSearchAll($query, string $term)` aggregating `where` over the model's own columns + `orWhereRelation` over each searchable relation, both `name` and `english_name`. Verified shape (11 domains):

```php
public function scopeSearchAll($query, string $term)
{
    $term = trim($term);
    if ($term === '') return $query;

    return $query->where(fn($q) => $q
        ->where('purchase_requests.id', 'like', "%{$term}%")
        ->orWhere('purchase_requests.pr_number', 'like', "%{$term}%")
        ->orWhereRelation('requester', 'name', 'like', "%{$term}%")
        ->orWhereRelation('costCenter', 'name', 'like', "%{$term}%")
        ->orWhereRelation('costCenter', 'english_name', 'like', "%{$term}%")
        ->orWhereRelation('department', 'name', 'like', "%{$term}%")
        ->orWhereRelation('department', 'english_name', 'like', "%{$term}%")
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
    use SoftDeletes, Relationships, UserStamps;

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

```php
// App\Models\Traits\Status\StatusFinder
public static function findBy(string $type, ?string $name = null): static|Collection|null
{
    $query = static::where('english_type', $type);
    if ($name) { $query->where('english_name', $name); return $query->first(); }
    return $query->get();
}
```

`TYPE_*` constants live on the OWNING models, never on `Status` — verified `PurchaseRequest::TYPE_PURCHASE_REQUEST = 'Purchase Request Status'`. The constant value is matched against `Status.english_type`. The owning model's `status()` relation is itself scoped by the constant:

```php
// PurchaseRequest\Relationships
public function status(): BelongsTo
{
    return $this->belongsTo(Status::class)
        ->where('english_type', static::TYPE_PURCHASE_REQUEST);
}
```

Never hardcode status strings in model code — always `Status::findBy(Model::TYPE_X, 'SomeStatus')`.

## 7. `SCANNABLE_TABLE` / `SCANNABLE_IDENTIFIER` constants

Verified `PurchaseRequest` declares both. `SCANNABLE_TABLE` is the model's table name; it is the marker `NotificationServiceProvider::boot()` scans `app/Models/` for to auto-attach `NotificationDispatcher` (see `filamentPattern.md` §1.26). `SCANNABLE_IDENTIFIER` is the human identifier column used by the notification evaluator. A model that should raise model-event notifications declares `SCANNABLE_TABLE`; one that should not, omits it (and gets no auto-wired observer).

## 8. Boot-only lifecycle rule

Only two General traits define `boot*` methods: `UserStamps` (`bootUserStamps` → creating/updating) and `HasSlug` (`bootHasSlug` → saving). No other trait boots lifecycle hooks, and models do not define `boot()` themselves. Side effects (cascade status, closure-table sync) live in `app/Observers/` and are registered manually in `AppServiceProvider::boot()` — see `filamentPattern.md` §1.26 for the manual-vs-`SCANNABLE_TABLE`-auto split. Keep new behavior in scopes/accessors or observers, not in model `boot`.

## 9. Migration skeleton (operational tables)

Verified `database/migrations/migrated/2025_07_24_150031_create_purchase_requests_table.php`:

```php
Schema::create('purchase_requests', function (Blueprint $table) {
    $table->id();
    $table->string('pr_number')->unique();
    $table->foreignId('requester_id')->constrained('users');
    $table->foreignId('department_id')->constrained('departments');
    $table->unsignedBigInteger('cost_center_id')->nullable();
    $table->date('required_by_date')->nullable()->comment('Date by which items are needed');
    $table->decimal('total_estimated_cost', 15, 2)->default(0);
    $table->string('urgency_level')->default('low')->comment('Urgency: low, medium, high');
    $table->foreignId('status_id')->nullable()->constrained('statuses');
    $table->foreignId('approver_id')->nullable()->constrained('users');
    $table->timestamp('approval_date')->nullable()->comment('Timestamp of approval');
    $table->text('rejection_reason')->nullable();
    $table->text('notes')->nullable()->comment('Additional notes');
    $table->unsignedBigInteger('user_id')->nullable();
    $table->unsignedBigInteger('updated_by_id')->nullable();
    $table->timestamps();
    $table->softDeletes();

    $table->index(['department_id', 'deleted_at']);
    $table->index(['status_id', 'deleted_at']);
    $table->index(['requester_id', 'deleted_at']);
    $table->index('approver_id');
});
```

Rules for every operational table:
- `$table->id()` PK → a business identifier (`pr_number`, `po_number`, `bp_number`, `shipment_no`) as `->unique()`.
- Foreign keys via `foreignId('x_id')->constrained('table')` with an explicit delete policy (`->cascadeOnDelete()` / `->nullOnDelete()`); nullable FKs use `->nullable()->constrained()` (status FK is always nullable).
- Money: `decimal('...', 15, 2)`. Rates/percentages: `decimal('...', 15, 5)` (or `(5,5)`).
- **`user_id` and `updated_by_id` are `unsignedBigInteger(...)->nullable()` with NO foreign-key constraint** — not `foreignId`. This is deliberate: it lets a `User` be deleted without cascading into every stamped record. Reproduce exactly; do not "fix" by adding `->constrained('users')`.
- `timestamps()` + `softDeletes()` on every operational table.
- A composite index `['{fk}_id', 'deleted_at']` for every foreign key used in filtered/sorted queries; standalone `->index('{fk}_id')` for FKs not paired with `deleted_at`.
- `->comment('...')` on every non-obvious column.
- `down()` is always `Schema::dropIfExists('table_name')`.

## 10. Pivot conventions + known divergence

Five pivot tables exist (`proforma_invoice_purchase_request`, `proforma_invoice_purchase_order`, `proforma_invoice_registered_order`, `registered_order_purchase_request`, `registered_order_purchase_order`, `purchase_order_purchase_request`). The convention is **not yet stable** — two shapes coexist:

**Shape A — composite primary key, no `id()`, no timestamps** (verified `purchase_order_purchase_request`):

```php
Schema::create('purchase_order_purchase_request', function (Blueprint $table) {
    $table->primary(['purchase_order_id', 'purchase_request_id']);
    $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
    $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();

    table->unique(['purchase_order_id', 'purchase_request_id'], 'uidx_po_pr');
    $table->index('purchase_request_id');
});
```

**Shape B — `id()` + `unique([...])` + timestamps** (verified `registered_order_purchase_request`, `registered_order_purchase_order`):

```php
Schema::create('registered_order_purchase_request', function (Blueprint $table) {
    $table->id();
    $table->foreignId('registered_order_id')->constrained('registered_orders')->cascadeOnDelete();
    $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['registered_order_id', 'purchase_request_id'], 'ro_pr_unique');
    $table->index('purchase_request_id');
});
```

**Two live bugs to fix, not replicate:**
- `purchase_order_purchase_request` line 19: `table->unique(...)` is missing the `$` — a fatal typo (`table` is an undefined constant). That file is in `migrated/` and must be corrected before it can run anywhere.
- `registered_order_purchase_order` lines 23–25: declares `unique(['registered_order_id','purchase_order_id'], 'ro_po_unique')` AND `unique(['registered_order_id','purchase_order_id'], 'uidx_ro_po')` on the same column pair — the second is redundant dead weight; drop it.

**Convention for new pivots: use Shape B** (`$table->id()`, two `foreignId->cascadeOnDelete`, `timestamps()`, ONE `unique([...], '{a}_{b}_unique')`, plus `$table->index('{second_fk}')`). It matches the majority of existing pivots and avoids both bugs. Name the index `{modelA}_{modelB}_unique` in snake_case.

## 11. Developer Decision Matrix

| When you need to… | Do this… | Why… |
|---|---|---|
| Add a new business model | Create `app/Models/{Name}.php` + `Traits/{Name}/Relationships.php` imported `as ExclusiveRelationships`. `use SoftDeletes, Relationships, ExclusiveRelationships, UserStamps` + `HasCustomAttributes` (if EAV-backed) + per-domain `HasSearchableRelations` + `HasFormattedName`. End `$fillable` with `user_id`, `updated_by_id`. | The aliasing rule (§2) is mandatory; missing it fatals on load. |
| Add a status-bearing model | Declare `public const TYPE_{NAME} = '{Name} Status';` on the owning model; scope its `status()` relation by `->where('english_type', static::TYPE_{NAME})`. Resolve via `Status::findBy(static::TYPE_{NAME}, 'EnglishName')`. | `Status` is polymorphic + locale-keyed; the constant scopes the lookup. |
| Add a model that raises notifications | Declare `public const SCANNABLE_TABLE = '{table}';` (+ `SCANNABLE_IDENTIFIER`). No `AppServiceProvider` edit. | `NotificationServiceProvider` auto-wires `NotificationDispatcher` for any model with the constant. |
| Add a side-effect observer | Create `app/Observers/{Name}Observer.php` + register `Model::observe(...)` in `AppServiceProvider::boot()`. Do NOT add `SCANNABLE_TABLE` for this. | The auto path attaches `NotificationDispatcher`, not a side-effect observer. |
| Add a searchable column | Extend `scopeSearchAll` in the per-domain `HasSearchableRelations` (add the column or an `orWhereRelation`). | `TableComponents::show{Relation}()` searches through this scope. |
| Add a computed display attribute | Add `HasFormattedName` (or a `HasComputedAttributes` accessor in `$appends`) in the per-domain folder. | `TableComponents::show{Relation}()` renders `formatted_name_without_date`. |
| Add an EAV-backed model | `use HasCustomAttributes`; the Filament side then gets the `extraAttributes` tab via `HasExtraAttributesManagement`. | The double-alias is the contract; see `filamentPattern.md` §1.6/1.9. |
| Add an operational migration | Follow §9: `id` → `*_number unique`, `foreignId->constrained`, nullable status FK, `decimal(15,2)` money, `unsignedBigInteger` `user_id`/`updated_by_id` (no FK), `timestamps`+`softDeletes`, composite `[...,deleted_at]` indexes, `->comment()`. | The skeleton is uniform across all operational tables; deviating breaks the resource's eager-load + filter assumptions. |
| Add a pivot table | Use Shape B (§10): `id()`, two `foreignId->cascadeOnDelete`, `timestamps()`, one `unique([...], '{a}_{b}_unique')`, `index('{second_fk}')`. | Shape A has a fatal typo and is the minority; Shape B is clean and matches the majority. |
| Add a General trait | Keep it side-effect-free except via `boot{TraitName}`; if it adds relations, consumers MUST alias it to avoid the `Relationships` collision. | The aliasing rule (§2) is the only guard against trait-name collisions. |

## 12. Absolute Anti-Patterns (Do Not Do This)

- ❌ **Importing two traits with the same short name without `as` aliasing.**
  - Why: PHP fatal — trait-name collision. The General `Relationships` and the per-domain `Relationships` MUST coexist via `as ExclusiveRelationships`.

- ❌ **Putting `TYPE_*` / `SCANNABLE_*` constants on `Status` or a shared base.**
  - Why: they live on the OWNING model; `Status::findBy` + `NotificationServiceProvider` resolve them there. A base-class constant would scope every consumer to the same type/table.

- ❌ **Adding `->constrained('users')` to `user_id` / `updated_by_id` in a migration.**
  - Why: those columns are deliberately unconstrained `unsignedBigInteger` so deleting a User does not cascade into every stamped record. The audit-stamp columns are soft references, not FKs.

- ❌ **Booting lifecycle hooks in a model or a new trait instead of using an Observer.**
  - Why: only `UserStamps` and `HasSlug` boot; all other side effects live in `app/Observers/` registered in `AppServiceProvider::boot()`. Putting `creating`/`updating` in a model breaks the convention and hides the side effect from the observer registry.

- ❌ **Collapsing the `customAttributes()` / `extraAttributes()` double-declaration with `->as()`.**
  - Why: it breaks one of the two EAV consumers (`filamentPattern.md` §1.9).

- ❌ **Replicating pivot Shape A's `table->unique(...)` typo or the duplicate `unique` index.**
  - Why: the first is a fatal `table` undefined-constant; the second is redundant. Use Shape B with a single named `unique`.

- ❌ **Hardcoding status strings in model code.**
  - Why: `Status` is polymorphic and locale-keyed. Always `Status::findBy(Model::TYPE_X, 'EnglishName')`.

- ❌ **Prefixing `scopeSearchAll` columns without the full table name.**
  - Why: the scope runs inside joined queries; an un-prefixed `id`/`name` is ambiguous. Use `purchase_requests.pr_number`, not `pr_number`.

- ❌ **Deleting the commented-out `->orderBy($this->localeColumn())` line in `Localization::newQuery()`.**
  - Why: it is an intentional preserved line (a disabled default-order experiment). Leave it as-is.

## 13. Naming conventions

- **Model root**: `{Name}` at `app/Models/{Name}.php`, `namespace App\Models`.
- **General traits**: `app/Models/Traits/General/{TraitName}.php`, `namespace App\Models\Traits\General`, `trait {TraitName}`.
- **Per-domain traits**: `app/Models/Traits/{Name}/{TraitName}.php`, `namespace App\Models\Traits\{Name}`.
- **Per-domain `Relationships`**: always imported `as ExclusiveRelationships` at the model; the trait itself is named `Relationships`.
- **Scopes**: `scopeSearchAll($query, string $term)` (per-domain `HasSearchableRelations`), `scopeActive($query)` (`HasScope`), `scopeSearchByName(Builder, string)` (`HasNameSearch`), `scopeSearchTargetable(Builder, string)` (`SearchTargetable`).
- **Accessors**: `getFormattedNameAttribute` / `getFormattedNameWithoutDateAttribute` (`HasFormattedName`), `getLocalizedNameAttribute` (`Localization`), `getTargetableFormatted(string)` (`HasProductCategoryFormatting`).
- **Boot methods**: `bootUserStamps` (`UserStamps`), `bootHasSlug` (`HasSlug`) — the only two.
- **Constants**: `SCANNABLE_TABLE` (table name, marker for auto-observer), `SCANNABLE_IDENTIFIER` (human id column), `TYPE_{NAME}` (Status `english_type` value, on the owning model).
- **Migrations**: `database/migrations/{timestamp}_create_{snake_plural}_table.php`; `down()` → `Schema::dropIfExists('{snake_plural}')`. Active migrations live in `migrations/`, archived operational schemas in `migrations/migrated/`.
- **Pivots**: `{modelA}_{modelB}` snake_case table; Shape B with index name `{a}_{b}_unique`.