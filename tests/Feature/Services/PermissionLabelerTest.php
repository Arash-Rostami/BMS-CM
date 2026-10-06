<?php

namespace Tests\Feature\Services;

use App\Services\PermissionLabeler;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PermissionLabelerTest extends TestCase
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

    public function test_ordinary_permission_label_is_unaffected(): void
    {
        $label = PermissionLabeler::getLabel('purchase_request.view');

        $this->assertNotEmpty($label);
        $this->assertStringNotContainsString('resources/general/strings.actions.', $label);
    }

    public function test_status_grant_permission_resolves_to_the_status_own_localized_name(): void
    {
        app()->setLocale('en');

        $label = PermissionLabeler::getLabel('status.grant_purchase_request_sales_manager_approval');

        $this->assertStringNotContainsString('resources/general/strings.actions.', $label);
        $this->assertStringContainsString('Sales Manager Approval', $label);
    }

    public function test_status_grant_permission_label_switches_with_locale(): void
    {
        app()->setLocale('fa');

        $label = PermissionLabeler::getLabel('status.grant_purchase_request_sales_manager_approval');

        $this->assertStringNotContainsString('Sales Manager Approval', $label);
        $this->assertStringContainsString('تاییدیه ابتدایی', $label);
    }

    public function test_status_grant_permission_with_no_matching_status_falls_back_gracefully(): void
    {
        $label = PermissionLabeler::getLabel('status.grant_some_untracked_permission_name');

        $this->assertStringNotContainsString('resources/general/strings.actions.', $label);
        $this->assertNotEmpty($label);
    }
}
