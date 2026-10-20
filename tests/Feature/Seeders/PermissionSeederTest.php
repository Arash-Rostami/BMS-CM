<?php

namespace Tests\Feature\Seeders;

use App\Models\Permission;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
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

    public function test_it_creates_the_report_and_status_grant_permissions_without_duplicates(): void
    {
        $expected = [
            'report.view', 'report.create', 'report.edit', 'report.delete', 'report.restore',
            'report.view_executive', 'report.view_operations', 'report.view_fx_supply', 'report.view_in_transit',
            'report.view_customs', 'report.view_fx_commitments', 'report.view_requester',
            'status.grant_purchase_request_commercial_manager_approval', 'status.grant_purchase_request_sales_manager_approval',
        ];

        Permission::whereIn('name', $expected)->delete();

        $this->seed(PermissionSeeder::class);
        $this->seed(PermissionSeeder::class);

        $this->assertEqualsCanonicalizing($expected, Permission::whereIn('name', $expected)->pluck('name')->all());
        $this->assertSame(count($expected), Permission::whereIn('name', $expected)->count());
    }

    public function test_it_never_seeds_the_retired_notification_setting_permissions(): void
    {
        Permission::where('name', 'like', 'notification_setting.%')->delete();

        $this->seed(PermissionSeeder::class);

        $this->assertSame(0, Permission::where('name', 'like', 'notification_setting.%')->count());
    }
}
