<?php

namespace Tests\Feature\Seeders;

use App\Models\PurchaseRequest;
use App\Models\Status;
use Database\Seeders\PurchaseRequestStatusSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseRequestStatusSeederTest extends TestCase
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

    private function live(string $englishName): ?Status
    {
        return Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)->where('english_name', $englishName)->first();
    }

    private function resetToProductionShape(): void
    {
        Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)
            ->whereIn('english_name', ['Submitted', 'Sales Manager Approval'])
            ->get()
            ->each->forceDelete();

        Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)
            ->update(['stage_order' => null, 'approval_permission' => null]);
    }

    public function test_it_adds_the_missing_stages_and_sets_order_and_approval_permissions(): void
    {
        $this->resetToProductionShape();
        $underReviewName = $this->live('Under Review')->name;
        $authorizedName = $this->live('Authorized')->name;

        $this->seed(PurchaseRequestStatusSeeder::class);

        $this->assertSame([1, 2, 3, 4], [
            $this->live('Submitted')->stage_order,
            $this->live('Under Review')->stage_order,
            $this->live('Sales Manager Approval')->stage_order,
            $this->live('Authorized')->stage_order,
        ]);
        $this->assertSame('status.grant_purchase_request_sales_manager_approval', $this->live('Sales Manager Approval')->approval_permission);
        $this->assertSame('status.grant_purchase_request_commercial_manager_approval', $this->live('Authorized')->approval_permission);
        $this->assertNull($this->live('Submitted')->approval_permission);
        $this->assertNull($this->live('Under Review')->approval_permission);
        $this->assertSame($underReviewName, $this->live('Under Review')->name);
        $this->assertSame($authorizedName, $this->live('Authorized')->name, 'an existing status is never renamed');
    }

    public function test_it_is_idempotent_and_leaves_the_other_statuses_alone(): void
    {
        $this->resetToProductionShape();
        $before = Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)->count();

        $this->seed(PurchaseRequestStatusSeeder::class);
        $afterFirst = Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)->count();
        $this->seed(PurchaseRequestStatusSeeder::class);

        $this->assertSame($before + 2, $afterFirst);
        $this->assertSame($afterFirst, Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)->count());

        foreach (['Conditional', 'Declined'] as $name) {
            $this->assertNull($this->live($name)->stage_order);
            $this->assertNull($this->live($name)->approval_permission);
        }
    }
}
