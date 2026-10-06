<?php

namespace Tests\Feature\Models;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
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

    public function test_grade_accessors_parse_a_suffixed_role_name(): void
    {
        $uniq = uniqid();
        $role = Role::factory()->create(['name' => "agent_{$uniq}_junior"]);

        $this->assertSame("agent_{$uniq}", $role->base_name);
        $this->assertSame('junior', $role->grade);
        $this->assertSame('⭐', $role->grade_label);
        $this->assertSame('⭐ Junior', $role->grade_title);
    }

    public function test_grade_accessors_return_null_without_a_grade_suffix(): void
    {
        $uniq = uniqid();
        $role = Role::factory()->create(['name' => "manager_{$uniq}"]);

        $this->assertSame("manager_{$uniq}", $role->base_name);
        $this->assertNull($role->grade);
        $this->assertNull($role->grade_label);
        $this->assertNull($role->grade_title);
    }

    public function test_combine_and_normalize_helpers(): void
    {
        $this->assertSame('agent', Role::extractBaseName('agent_junior'));
        $this->assertSame('junior', Role::extractGrade('agent_junior'));
        $this->assertNull(Role::extractGrade('manager'));

        $this->assertSame('agent_senior', Role::combineName('agent', 'senior'));
        $this->assertSame('agent_mid', Role::combineName('agent_junior', 'mid'));
        $this->assertSame('plain', Role::combineName('plain', null));

        $this->assertSame('admin_junior', Role::normalizeToSnake('Admin Junior'));
        $this->assertSame(
            ['junior' => '⭐', 'mid' => '⭐⭐', 'senior' => '⭐⭐⭐'],
            Role::getGradeOptions()
        );
    }

    public function test_where_base_name_scope_matches_exact_and_prefixed_names_only(): void
    {
        $uniq = uniqid();
        $plain = Role::factory()->create(['name' => "qa_{$uniq}"]);
        $graded = Role::factory()->create(['name' => "qa_{$uniq}_senior"]);
        $longer = Role::factory()->create(['name' => "qa_{$uniq}_lead"]);
        $other = Role::factory()->create(['name' => "qb_{$uniq}"]);

        $ids = Role::whereBaseName("qa_{$uniq}")->pluck('id');

        $this->assertTrue($ids->contains($plain->id));
        $this->assertTrue($ids->contains($graded->id));
        $this->assertTrue($ids->contains($longer->id));
        $this->assertFalse($ids->contains($other->id));
    }

    public function test_where_grade_scope_matches_only_roles_ending_in_that_grade(): void
    {
        $uniq = uniqid();
        $junior = Role::factory()->create(['name' => "dev_{$uniq}_junior"]);
        $senior = Role::factory()->create(['name' => "dev_{$uniq}_senior"]);

        $ids = Role::whereGrade('junior')->pluck('id');

        $this->assertTrue($ids->contains($junior->id));
        $this->assertFalse($ids->contains($senior->id));
    }

    public function test_unique_base_names_collects_one_entry_per_base_name(): void
    {
        $uniq = uniqid();
        Role::factory()->create(['name' => "auditor_{$uniq}_junior"]);
        Role::factory()->create(['name' => "auditor_{$uniq}_senior"]);

        $baseNames = Role::getUniqueBaseNames();

        $this->assertArrayHasKey("auditor_{$uniq}", $baseNames);
        $this->assertNotSame('', $baseNames["auditor_{$uniq}"]);
    }

    public function test_modules_from_permissions_and_permissions_for_modules_are_inverse_operations(): void
    {
        $uniq = uniqid();
        $prView = Permission::firstOrCreate(['name' => "module_{$uniq}.view", 'guard_name' => 'web']);
        $prEdit = Permission::firstOrCreate(['name' => "module_{$uniq}.edit", 'guard_name' => 'web']);
        $otherView = Permission::firstOrCreate(['name' => "other_{$uniq}.view", 'guard_name' => 'web']);

        $this->assertEqualsCanonicalizing(
            ["module_{$uniq}", "other_{$uniq}"],
            Role::getModulesFromPermissions([$prView->id, $otherView->id, $prEdit->id])
        );

        $ids = Role::getPermissionsForModules(["other_{$uniq}"]);
        $this->assertSame([$otherView->id], $ids);

        $moduleIds = Role::getPermissionsForModules(["module_{$uniq}"]);
        $this->assertContains($prView->id, $moduleIds);
        $this->assertContains($prEdit->id, $moduleIds);
        $this->assertNotContains($otherView->id, $moduleIds);
    }

    public function test_sync_modules_grants_only_that_modules_permissions(): void
    {
        $uniq = uniqid();
        $statusView = Permission::firstOrCreate(['name' => "status_{$uniq}.view", 'guard_name' => 'web']);
        $statusEdit = Permission::firstOrCreate(['name' => "status_{$uniq}.edit", 'guard_name' => 'web']);
        $bankView = Permission::firstOrCreate(['name' => "bank_{$uniq}.view", 'guard_name' => 'web']);

        $role = Role::factory()->create(['name' => "ops_{$uniq}_mid"]);
        $role->syncModules(["status_{$uniq}"]);

        $granted = $role->permissions()->pluck('name');

        $this->assertTrue($granted->contains("status_{$uniq}.view"));
        $this->assertTrue($granted->contains("status_{$uniq}.edit"));
        $this->assertFalse($granted->contains("bank_{$uniq}.view"));

        $this->assertSame(["status_{$uniq}"], $role->modules);
    }

    public function test_users_relation_returns_only_users_assigned_the_role(): void
    {
        $role = Role::factory()->create(['name' => 'qa_user_'.uniqid()]);

        $assigned = User::factory()->create();
        $other = User::factory()->create();
        $assigned->assignRole($role);

        $ids = $role->users()->pluck('id');

        $this->assertTrue($ids->contains($assigned->id));
        $this->assertFalse($ids->contains($other->id));
    }
}