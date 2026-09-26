<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Operational\BankProfileResource\Pages\CreateBankProfile;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Permission;
use App\Models\RegisteredOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ScratchBpFieldTest extends TestCase
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

    private function actingAsUserWithPermissions(array $permissionNames): User
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    public function test_scratch(): void
    {
        $this->actingAsUserWithPermissions(['bank_profile.create', 'bank_profile.view']);
        $ro = RegisteredOrder::factory()->create([
            'buyer_id' => Company::factory()->create()->id,
            'currency_id' => Currency::factory()->create()->id,
        ]);

        request()->query->set('registered_order_id', (string) $ro->id);

        $instance = Livewire::test(CreateBankProfile::class);

        $field = $instance->instance()->form->getComponent('registered_order_id');

        dump([
            'data.registered_order_id' => $instance->get('data.registered_order_id'),
            'field_class' => $field ? get_class($field) : null,
            'is_disabled' => $field?->isDisabled(),
        ]);
    }
}
