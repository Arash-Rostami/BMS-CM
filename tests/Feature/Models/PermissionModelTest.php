<?php

namespace Tests\Feature\Models;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
}