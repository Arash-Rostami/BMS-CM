<?php

namespace Tests\Feature\Traits;

use App\Models\Attributes\Indirect;
use App\Models\Bank;
use App\Models\BankProfile;
use App\Models\Company;
use App\Models\PurchaseRequest;
use App\Models\Status;
use App\Models\Traits\General\Relationships;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphOneOrMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Tests\TestCase;

/**
 * App\Models\Traits\General\Relationships is the audit creator()/updater() pair,
 * composed (unaliased) on 17+ unrelated models (Bank, Company, Currency, Department,
 * Correspondence, PurchaseRequest, ProformaInvoice, RegisteredOrder, PurchaseOrder,
 * Payment, Shipment, Custom, BankProfile, Product, EntityAttribute, Status, Target,
 * NotificationSetting, Specification, Attachment, …) — a genuine cross-cutting
 * mechanism per testPattern.md, so it gets its own file rather than folding into
 * any one model's test. Verified against a representative, unrelated sample
 * (Operational + Master) rather than every composing model, since the trait body
 * itself has no per-model branching — same `belongsTo` definitions everywhere.
 */
class RelationshipsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function useMysql(): void
    {
        $env = base_path('.env');
        if (is_file($env)) {
            $vals = [];
            foreach (explode("\n", (string) file_get_contents($env)) as $line) {
                if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $line, $m)) {
                    $vals[$m[1]] = trim(preg_replace('/\s+#.*$/', '', trim($m[2])), "\"' \t");
                }
            }
            $map = ['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'];
            foreach ($map as $envKey => $cfgKey) {
                if (isset($vals[$envKey])) {
                    config(['database.connections.mysql.'.$cfgKey => $vals[$envKey]]);
                }
            }
        }
        DB::purge('mysql');
        config(['database.default' => 'mysql']);
    }

    public function test_bank_resolves_creator_and_updater_via_the_general_trait(): void
    {
        $this->assertContains(Relationships::class, class_uses_recursive(Bank::class));

        $creator = User::factory()->create();
        $updater = User::factory()->create();
        $bank = Bank::factory()->create(['user_id' => $creator->id, 'updated_by_id' => $updater->id]);

        $this->assertTrue($bank->creator()->is($creator));
        $this->assertTrue($bank->updater()->is($updater));
    }

    public function test_company_resolves_creator_and_updater_via_the_general_trait(): void
    {
        $this->assertContains(Relationships::class, class_uses_recursive(Company::class));

        $creator = User::factory()->create();
        $company = Company::factory()->create(['user_id' => $creator->id, 'updated_by_id' => null]);

        $this->assertTrue($company->creator()->is($creator));
        $this->assertNull($company->updater);
    }

    public function test_purchase_request_resolves_creator_and_updater_via_the_general_trait_alongside_its_own_domain_relationships(): void
    {
        $this->assertContains(Relationships::class, class_uses_recursive(PurchaseRequest::class));

        $creator = User::factory()->create();
        $request = PurchaseRequest::factory()->create(['user_id' => $creator->id]);

        $this->assertTrue($request->creator()->is($creator));
        $this->assertSame('user_id', $request->creator()->getForeignKeyName());
        $this->assertSame('updated_by_id', $request->updater()->getForeignKeyName());
    }

    private const ONE_WAY_BELONGS_TO = [
        'Payment::purchaseOrder' => 'Filament RM anchor over targetable_id; the real inverse is PurchaseOrder::payments (morphMany)',
        'Payment::registeredOrder' => 'Filament RM anchor over targetable_id; the real inverse is RegisteredOrder::payments (morphMany)',
        'CorrespondenceRecipient::correspondence' => 'pivot model; the inverse is Correspondence::recipients (belongsToMany using this pivot)',
        'CorrespondenceRecipient::user' => 'pivot model; the inverse is User::receivedCorrespondences (belongsToMany using this pivot)',
    ];

    private const ONE_WAY_AUDIT = ['creator', 'updater'];

    private const ONE_WAY_TARGETS = [
        Status::class => 'shared lookup consumed by 16 columns across 9 models; owner inverses would surface as far tables in the calendar picker',
    ];

    private const ONE_WAY_MORPH_TO = [
        'CalendarHit::subject' => 'engine-written rows; an inverse on every subject would surface CalendarHit in the calendar picker',
    ];

    private const COLUMNS_WITHOUT_TARGET = ['specifications.tax_id'];

    private const VENDOR_MODELS = ['Permission', 'Role'];

    /**
     * @return array<class-string<Model>, array<string, Relation>>
     */
    private function appRelations(): array
    {
        $all = [];
        $modelsPath = str_replace('\\', '/', app_path('Models'));

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! is_subclass_of($class, Model::class) || in_array(class_basename($class), self::VENDOR_MODELS, true)) {
                continue;
            }

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $type = $method->getReturnType();
                $source = str_replace('\\', '/', (string) $method->getFileName());

                $isRelation = ! $method->isStatic()
                    && $method->getNumberOfParameters() === 0
                    && str_starts_with($source, $modelsPath)
                    && ($type === null ? basename($source) === 'Relationships.php' : $type instanceof ReflectionNamedType && is_subclass_of($type->getName(), Relation::class));

                if ($isRelation && ($relation = $method->invoke(new $class)) instanceof Relation) {
                    $all[$class][$method->name] = $relation;
                }
            }
        }

        return $all;
    }

    public function test_every_foreign_key_column_has_a_belongs_to_or_morph_to_on_its_model(): void
    {
        $missing = [];
        $all = $this->appRelations();

        $this->assertArrayHasKey('currency', $all[BankProfile::class]);
        $this->assertArrayHasKey('registeredOrders', $all[PurchaseRequest::class]);

        foreach ($all as $class => $relations) {
            $table = (new $class)->getTable();
            $keys = collect($relations)
                ->filter(fn (Relation $relation): bool => $relation instanceof BelongsTo)
                ->map(fn (BelongsTo $relation): string => $relation->getForeignKeyName())
                ->all();

            foreach (Schema::getColumnListing($table) as $column) {
                if (str_ends_with($column, '_id') && ! in_array($column, $keys, true) && ! in_array("{$table}.{$column}", self::COLUMNS_WITHOUT_TARGET, true)) {
                    $missing[] = "{$table}.{$column}";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_every_belongs_to_has_a_has_many_or_has_one_inverse_on_its_target(): void
    {
        $all = $this->appRelations();
        $missing = [];

        foreach ($all as $class => $relations) {
            foreach ($relations as $name => $relation) {
                $related = $relation->getRelated()::class;

                if (! $relation instanceof BelongsTo || $relation instanceof MorphTo
                    || in_array($name, self::ONE_WAY_AUDIT, true)
                    || isset(self::ONE_WAY_BELONGS_TO[class_basename($class).'::'.$name])
                    || isset(self::ONE_WAY_TARGETS[$related])) {
                    continue;
                }

                $hasInverse = collect($all[$related] ?? [])->contains(
                    fn (Relation $inverse): bool => $inverse instanceof HasOneOrMany
                        && ! $inverse instanceof MorphOneOrMany
                        && $inverse->getRelated()::class === $class
                        && $inverse->getForeignKeyName() === $relation->getForeignKeyName()
                );

                if (! $hasInverse) {
                    $missing[] = class_basename($class)."::{$name}";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_every_belongs_to_many_has_a_matching_inverse_over_the_same_pivot(): void
    {
        $all = $this->appRelations();
        $missing = [];

        foreach ($all as $class => $relations) {
            foreach ($relations as $name => $relation) {
                if (! $relation instanceof BelongsToMany || (new ReflectionMethod($class, $name))->getAttributes(Indirect::class) !== []) {
                    continue;
                }

                $hasInverse = collect($all[$relation->getRelated()::class] ?? [])->contains(
                    fn (Relation $inverse): bool => $inverse instanceof BelongsToMany
                        && $inverse->getRelated()::class === $class
                        && $inverse->getTable() === $relation->getTable()
                        && $inverse->getForeignPivotKeyName() === $relation->getRelatedPivotKeyName()
                        && $inverse->getRelatedPivotKeyName() === $relation->getForeignPivotKeyName()
                );

                if (! $hasInverse) {
                    $missing[] = class_basename($class)."::{$name}";
                }
            }
        }

        $this->assertSame([], $missing);
    }

    public function test_every_morph_relation_is_paired_with_its_counterpart(): void
    {
        $all = $this->appRelations();
        $morphMany = collect($all)->flatten(1)->filter(fn (Relation $relation): bool => $relation instanceof MorphOneOrMany);
        $morphTo = collect($all)->flatten(1)->filter(fn (Relation $relation): bool => $relation instanceof MorphTo);
        $missing = [];

        foreach ($all as $class => $relations) {
            foreach ($relations as $name => $relation) {
                $paired = match (true) {
                    $relation instanceof MorphOneOrMany => $morphTo->contains(fn (MorphTo $inverse): bool => $inverse->getMorphType() === $relation->getMorphType()),
                    $relation instanceof MorphTo => isset(self::ONE_WAY_MORPH_TO[class_basename($class).'::'.$name])
                        || $morphMany->contains(fn (MorphOneOrMany $inverse): bool => $inverse->getMorphType() === $relation->getMorphType() && $inverse->getRelated()::class === $class),
                    default => true,
                };

                if (! $paired) {
                    $missing[] = class_basename($class)."::{$name}";
                }
            }
        }

        $this->assertSame([], $missing);
    }
}
