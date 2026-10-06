<?php

namespace Tests\Feature\Integrity;

use App\Filament\Traits\HasResourcePermissions;
use App\Models\Permission;
use Filament\Resources\Resource;
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
}
