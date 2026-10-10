<?php

namespace Tests\Feature\Integrity;

use App\Filament\Resources\EntityAttributeResource;
use App\Filament\Resources\TargetResource;
use App\Filament\Traits\HasGlobalSearchConvention;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ReflectionClass;
use Tests\TestCase;

class ResourceAuthorizationIntegrityTest extends TestCase
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

    private const GUARDED_METHODS = [
        'canViewAny',
        'canView',
        'canCreate',
        'canEdit',
        'canDelete',
        'canDeleteAny',
        'canForceDelete',
        'canForceDeleteAny',
        'canRestore',
        'canRestoreAny',
        'getViewAnyAuthorizationResponse',
        'getViewAuthorizationResponse',
        'getCreateAuthorizationResponse',
        'getEditAuthorizationResponse',
        'getUpdateAuthorizationResponse',
        'getAttachAuthorizationResponse',
        'getDetachAuthorizationResponse',
        'getDetachAnyAuthorizationResponse',
        'getAssociateAuthorizationResponse',
        'getDissociateAuthorizationResponse',
        'getDissociateAnyAuthorizationResponse',
        'getDeleteAuthorizationResponse',
        'getDeleteAnyAuthorizationResponse',
        'getForceDeleteAuthorizationResponse',
        'getForceDeleteAnyAuthorizationResponse',
        'getRestoreAuthorizationResponse',
        'getRestoreAnyAuthorizationResponse',
    ];

    // Resources with a documented, deliberate reason to override one of the GUARDED_METHODS
    // directly instead of relying solely on HasResourcePermissions — see filamentPattern.md
    // §1.5 ("Live trap reproduced 2026-10-07") for UserResource (Delete is a hard business
    // rule, never permission-gated). NotificationSettingResource doesn't need an entry here —
    // it doesn't compose HasResourcePermissions at all (2026-10-07: deliberately open to every
    // authenticated user, no Spatie permission; only Delete is restricted, by ownership/
    // recipient, not by a permission grant), so this check skips it entirely (see below).
    private const ALLOWED_OVERRIDES = [
        'App\\Filament\\Resources\\UserResource' => [
            'canDelete', 'canDeleteAny', 'getDeleteAuthorizationResponse', 'getDeleteAnyAuthorizationResponse',
        ],
        'App\\Filament\\Resources\\CalendarRuleResource' => [
            'canEdit', 'canDelete', 'canRestore',
            'getEditAuthorizationResponse', 'getUpdateAuthorizationResponse',
            'getDeleteAuthorizationResponse', 'getRestoreAuthorizationResponse',
        ],
    ];

    public function test_no_resource_overrides_the_shared_permission_trait(): void
    {
        $traitFile = (new ReflectionClass(HasResourcePermissions::class))->getFileName();
        $violations = [];

        foreach (glob(app_path('Filament/Resources/*Resource.php')) as $file) {
            $class = 'App\\Filament\\Resources\\'.basename($file, '.php');

            if (! is_subclass_of($class, Resource::class)) {
                continue;
            }

            if (! in_array(HasResourcePermissions::class, class_uses_recursive($class))) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            foreach (self::GUARDED_METHODS as $method) {
                if (in_array($method, self::ALLOWED_OVERRIDES[$class] ?? [], true)) {
                    continue;
                }

                $methodFile = $reflection->getMethod($method)->getFileName();

                if ($methodFile !== $traitFile) {
                    $violations[] = "{$class}::{$method}() is declared in {$methodFile}, bypassing HasResourcePermissions";
                }
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_permission_trait_covers_every_action_the_relation_manager_bridge_can_request(): void
    {
        $bridgeActions = [
            'viewAny', 'view', 'create', 'update', 'delete', 'deleteAny',
            'restore', 'restoreAny', 'forceDelete', 'forceDeleteAny',
            'attach', 'detach', 'detachAny', 'associate', 'dissociate', 'dissociateAny',
        ];

        $violations = [];

        foreach ($bridgeActions as $action) {
            $method = 'get'.ucfirst($action).'AuthorizationResponse';

            if (! method_exists(HasResourcePermissions::class, $method)) {
                $violations[] = "HasResourcePermissions is missing {$method}() — Filament's RelationManager bridge builds this exact name from the raw '{$action}' action string, and a miss falls through to the vendor's policy-less default-allow";
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_every_module_prefix_has_the_full_action_set_seeded(): void
    {
        if (! Schema::hasTable((new Permission)->getTable())) {
            $this->markTestSkipped('permissions table not present in this database');
        }

        $actions = ['view', 'create', 'edit', 'delete', 'restore'];
        $violations = [];

        $existing = Permission::query()->pluck('name');

        foreach ($existing->map(fn ($name) => Str::before($name, '.'))->unique() as $module) {
            foreach ($actions as $action) {
                if (! $existing->contains("{$module}.{$action}")) {
                    $violations[] = "{$module} has {$module}.view but no {$module}.{$action} — the Role form's per-action toggle finds zero permissions to attach, so checking it silently saves nothing";
                }
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_every_relation_manager_delegates_authorization_to_its_related_resource(): void
    {
        $violations = [];

        foreach (glob(app_path('Filament/Resources/{,Operational/,Master/}*Resource/RelationManagers/*RelationManager.php'), GLOB_BRACE) as $file) {
            $class = 'App\\Filament\\Resources\\'.str_replace(
                [app_path('Filament/Resources/'), '/', '.php'],
                ['', '\\', ''],
                $file
            );

            $related = (new ReflectionClass($class))->getDefaultProperties()['relatedResource'] ?? null;

            if (! is_string($related) || ! str_starts_with($related, 'App\\Filament\\Resources\\')) {
                $violations[] = "{$class} has no \$relatedResource — its actions bypass Spatie permissions (vendor default-allow)";
            }
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    public function test_every_conventional_resource_is_safely_globally_searchable(): void
    {
        $classes = collect(glob(app_path('Filament/Resources/*Resource.php')))
            ->map(fn (string $file): string => 'App\\Filament\\Resources\\'.basename($file, '.php'))
            ->filter(fn (string $class): bool => in_array(HasGlobalSearchConvention::class, class_uses_recursive($class)));

        $this->assertGreaterThanOrEqual(20, $classes->count());

        $gated = $classes->filter(fn (string $class): bool => in_array(HasResourcePermissions::class, class_uses_recursive($class)));
        $viewer = User::factory()->create();
        $role = Role::create(['name' => 'gs_role_'.uniqid(), 'guard_name' => 'web']);
        $gated->each(fn (string $class) => $role->givePermissionTo(Permission::firstOrCreate(['name' => $class::getPermissionPrefix().'.view', 'guard_name' => 'web'])));
        $viewer->assignRole($role);
        $violations = [];

        $this->actingAs(User::factory()->create());
        foreach ($gated as $class) {
            $class::canGloballySearch() && $violations[] = "{$class} is searchable without its view permission";
        }

        $this->actingAs($viewer);
        Model::preventLazyLoading();

        try {
            foreach ($classes as $class) {
                $model = $class::getModel();
                $record = $model::factory()->create();
                $found = $class::getGlobalSearchEloquentQuery()->whereKey($record->getKey())->get()->first();

                $found === null && $violations[] = "{$class} cannot find its own record";

                $class::canGloballySearch() || $violations[] = "{$class} is not globally searchable for a viewer";
                $found === null || $this->globalSearchShapeViolations($class, $found, $violations);

                if (in_array(SoftDeletes::class, class_uses_recursive($model))) {
                    $record->delete();
                    $class::getGlobalSearchEloquentQuery()->whereKey($record->getKey())->exists() && $violations[] = "{$class} returns soft-deleted records";
                }
            }
        } finally {
            Model::preventLazyLoading(false);
        }

        foreach ([EntityAttributeResource::class, TargetResource::class] as $excluded) {
            $this->assertSame([], $excluded::getGloballySearchableAttributes(), "{$excluded} is intentionally not globally searchable");
        }

        $this->assertEmpty($violations, implode("\n", $violations));
    }

    private function globalSearchShapeViolations(string $class, Model $found, array &$violations): void
    {
        $class::getGloballySearchableAttributes() === [] && $violations[] = "{$class} has no searchable attributes";
        blank($class::getGlobalSearchResultTitle($found)) && $violations[] = "{$class} has an empty title";
        blank($class::getGlobalSearchResultUrl($found)) && $violations[] = "{$class} has no result URL";

        $details = $class::getGlobalSearchResultDetails($found);
        count($details) > 3 && $violations[] = "{$class} shows more than 3 detail pairs";

        foreach ($details as $label => $value) {
            (is_string($label) && is_scalar($value)) || $violations[] = "{$class} detail '{$label}' is not a label => value pair";
        }
    }
}
