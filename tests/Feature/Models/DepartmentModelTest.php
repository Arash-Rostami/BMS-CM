<?php

namespace Tests\Feature\Models;

use App\Models\Department;
use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DepartmentModelTest extends TestCase
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

    // Consumer relations keep resolving an already-assigned department after it's deactivated (history is preserved)

    public function test_purchase_request_department_relation_still_resolves_an_inactive_department(): void
    {
        $inactive = Department::factory()->inactive()->create();
        $pr = PurchaseRequest::factory()->create(['department_id' => $inactive->id]);

        $this->assertSame($inactive->id, $pr->fresh()->department->id);
    }

    public function test_purchase_request_cost_center_relation_still_resolves_an_inactive_department(): void
    {
        $inactive = Department::factory()->inactive()->create();
        $pr = PurchaseRequest::factory()->create(['cost_center_id' => $inactive->id]);

        $this->assertSame($inactive->id, $pr->fresh()->costCenter->id);
    }

    public function test_user_department_relation_still_resolves_an_inactive_department(): void
    {
        $inactive = Department::factory()->inactive()->create();
        $user = User::factory()->create(['department_id' => $inactive->id]);

        $this->assertSame($inactive->id, $user->fresh()->department->id);
    }

    public function test_consumer_relations_still_resolve_active_departments(): void
    {
        $active = Department::factory()->create();
        $pr = PurchaseRequest::factory()->create(['department_id' => $active->id, 'cost_center_id' => $active->id]);

        $this->assertSame($active->id, $pr->fresh()->department->id);
        $this->assertSame($active->id, $pr->fresh()->costCenter->id);
    }

    // scopeActive

    public function test_scope_active_returns_only_active_departments(): void
    {
        $active = Department::factory()->create();
        $inactive = Department::factory()->inactive()->create();

        $results = Department::active()->pluck('id');

        $this->assertContains($active->id, $results);
        $this->assertNotContains($inactive->id, $results);
    }

    // Localization accessor

    public function test_localized_name_resolves_by_locale(): void
    {
        $department = Department::factory()->create([
            'name' => 'واحد مالی',
            'english_name' => 'Finance Unit',
        ]);

        app()->setLocale('fa');
        $this->assertSame('واحد مالی', $department->localized_name);

        app()->setLocale('en');
        $this->assertSame('Finance Unit', $department->localized_name);
    }

    // Constants + casts

    public function test_scannable_identifier_is_the_code_column(): void
    {
        $this->assertSame('code', Department::SCANNABLE_IDENTIFIER);
    }

    public function test_is_active_is_cast_to_a_boolean(): void
    {
        $department = Department::factory()->create();

        $this->assertIsBool($department->is_active);
    }

    // UserStamps + soft deletes

    public function test_creation_stamps_the_authenticated_user_and_soft_delete_keeps_the_row(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $department = Department::factory()->create();
        $this->assertSame($user->id, $department->creator->id);

        $department->delete();

        $this->assertNull(Department::find($department->id));
        $this->assertNotNull(Department::withTrashed()->find($department->id));
        $this->assertTrue($department->fresh()->trashed());
    }
}
