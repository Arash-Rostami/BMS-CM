<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\EditPurchaseRequest;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class DebugStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $env = base_path('.env');
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
        DB::purge('mysql');
        config(['database.default' => 'mysql']);
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_debug(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach (['purchase_request.edit', 'purchase_request.view'] as $p) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        $underReview = Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)->where('english_name', 'Under Review')->first();
        $sma = Status::where('english_type', PurchaseRequest::TYPE_PURCHASE_REQUEST)->where('english_name', 'Sales Manager Approval')->first();
        $record = PurchaseRequest::factory()->create(['status_id' => $underReview->id]);

        $test = Livewire::test(EditPurchaseRequest::class, ['record' => $record->getRouteKey()]);
        $test->set('data.status_id', $sma->id);
        echo 'DATA STATUS_ID AFTER SET: '.$test->get('data.status_id').PHP_EOL;
        $test->call('save');
        echo 'ERRORS: '.json_encode($test->errors()->toArray()).PHP_EOL;
        echo 'RECORD STATUS AFTER SAVE: '.$record->fresh()->status->english_name.PHP_EOL;
        $this->assertTrue(true);
    }
}
