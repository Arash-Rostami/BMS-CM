<?php

namespace Tests\Feature\Traits;

use App\Filament\Resources\Master\BankResource\Pages\ManageBanks;
use App\Models\Bank;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * App\Filament\Traits\PrefillsTableSearch — the mount() override on the project
 * base App\Filament\Pages\ManageRecords that copies request()->query('search')
 * into $this->tableSearch, driving the ?search= deep link on every master-data
 * Manage<X> page. Covered through ManageBanks, a real consumer.
 */
class PrefillsTableSearchTest extends TestCase
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

    public function test_mounting_with_a_search_query_param_prefills_the_table_search_and_narrows_the_rows(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['bank.view']);

        $matching = Bank::factory()->create(['english_name' => 'Alpha Quokka Prefill Bank']);
        $other = Bank::factory()->create(['english_name' => 'Beta Zebra Prefill Bank']);

        Livewire::withQueryParams(['search' => 'Quokka'])
            ->test(ManageBanks::class)
            ->assertSet('tableSearch', 'Quokka', 'The ?search= query param must prefill tableSearch on mount.')
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_mounting_without_a_search_query_param_leaves_the_table_unfiltered(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['bank.view']);

        $one = Bank::factory()->create(['english_name' => 'Alpha Quokka Prefill Bank']);
        $two = Bank::factory()->create(['english_name' => 'Beta Zebra Prefill Bank']);

        Livewire::test(ManageBanks::class)
            ->assertSet('tableSearch', '', 'Without a query param the table search must stay empty.')
            ->assertCanSeeTableRecords([$one, $two]);
    }
}