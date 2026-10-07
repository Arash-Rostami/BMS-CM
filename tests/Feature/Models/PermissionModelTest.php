<?php

namespace Tests\Feature\Models;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Exceptions\PermissionAlreadyExists;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionModelTest extends TestCase
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

    public function test_users_relation_returns_only_users_assigned_the_permission(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'qa_probe.'.uniqid(), 'guard_name' => 'web']);

        $assigned = User::factory()->create();
        $other = User::factory()->create();
        $assigned->givePermissionTo($permission);

        $ids = $permission->users()->pluck('id');

        $this->assertTrue($ids->contains($assigned->id));
        $this->assertFalse($ids->contains($other->id));
    }

    private function makePermission(string $suffix = 'view'): Permission
    {
        return Permission::create(['name' => 'qa_probe_'.uniqid().'.'.$suffix, 'guard_name' => 'web']);
    }

    public function test_users_relation_is_a_morph_pivot_scoped_to_the_user_model_type(): void
    {
        $permission = $this->makePermission();
        $user = User::factory()->create();
        $user->givePermissionTo($permission);

        $row = DB::table(config('permission.table_names.model_has_permissions'))
            ->where('permission_id', $permission->id)
            ->first();

        $this->assertSame(User::class, $row->model_type);
        $this->assertSame($user->id, (int) $row->model_id);
        $this->assertInstanceOf(User::class, $permission->users()->first());
    }

    public function test_users_relation_excludes_users_holding_the_permission_only_through_a_role(): void
    {
        $permission = $this->makePermission();
        $role = Role::create(['name' => 'qa_role_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $viaRole = User::factory()->create();
        $viaRole->assignRole($role);

        $this->assertTrue($viaRole->hasPermissionTo($permission));
        $this->assertFalse($permission->users()->pluck('id')->contains($viaRole->id));
        $this->assertTrue($permission->roles->contains('id', $role->id));
    }

    public function test_revoking_the_permission_removes_the_user_from_the_relation(): void
    {
        $permission = $this->makePermission();
        $user = User::factory()->create();
        $user->givePermissionTo($permission);
        $user->revokePermissionTo($permission);

        $this->assertSame(0, $permission->users()->count());
    }

    public function test_duplicate_name_on_the_same_guard_is_rejected(): void
    {
        $permission = $this->makePermission();

        $this->expectException(PermissionAlreadyExists::class);

        Permission::create(['name' => $permission->name, 'guard_name' => 'web']);
    }

    public function test_same_name_is_allowed_on_a_different_guard(): void
    {
        $permission = $this->makePermission();

        $other = Permission::create(['name' => $permission->name, 'guard_name' => 'api']);

        $this->assertNotSame($permission->id, $other->id);
    }

    public function test_find_by_name_resolves_the_app_permission_model_with_module_action_shape(): void
    {
        $permission = $this->makePermission('edit');

        $found = Permission::findByName($permission->name, 'web');

        $this->assertInstanceOf(Permission::class, $found);
        $this->assertSame($permission->id, $found->id);
        $this->assertMatchesRegularExpression('/^[a-z0-9_]+\.(view|create|edit|delete)$/', $found->name);
    }

    public function test_find_by_name_throws_for_a_missing_permission(): void
    {
        $this->expectException(PermissionDoesNotExist::class);

        Permission::findByName('qa_missing.'.uniqid(), 'web');
    }

    public function test_factory_builds_a_web_guard_app_permission(): void
    {
        $permission = Permission::factory()->create(['name' => 'qa_factory.'.uniqid()]);

        $this->assertInstanceOf(Permission::class, $permission);
        $this->assertSame('web', $permission->guard_name);
        $this->assertDatabaseHas(config('permission.table_names.permissions'), ['id' => $permission->id]);
    }

    public function test_deleting_the_permission_detaches_it_from_users_and_roles(): void
    {
        $permission = $this->makePermission();
        $role = Role::create(['name' => 'qa_role_'.uniqid(), 'guard_name' => 'web']);
        $user = User::factory()->create();
        $role->givePermissionTo($permission);
        $user->givePermissionTo($permission);

        $permission->delete();

        $this->assertNull(Permission::find($permission->id));
        $this->assertSame(0, DB::table(config('permission.table_names.role_has_permissions'))->where('permission_id', $permission->id)->count());
        $this->assertSame(0, DB::table(config('permission.table_names.model_has_permissions'))->where('permission_id', $permission->id)->count());
    }
}