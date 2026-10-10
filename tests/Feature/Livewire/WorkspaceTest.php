<?php

namespace Tests\Feature\Livewire;

use App\Livewire\LandingPage\Workspace;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceTest extends TestCase
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

    public function test_searchable_module_ids_match_the_workspace_record_pinning_config_exactly(): void
    {
        $this->actingAsUserWithPermissions(Permission::pluck('name')->all());

        $config = Livewire::test(Workspace::class, ['counts' => []])->get('workspaceConfig');

        $searchable = collect($config['modules'])->where('searchable', true)->pluck('id')->sort()->values()->all();
        $expected = collect(array_keys(config('workspace.resources')))->sort()->values()->all();

        $this->assertSame($expected, $searchable);
    }

    public function test_the_module_list_is_permission_gated_and_open_modules_stay_visible(): void
    {
        $this->actingAsUserWithPermissions(['purchase_request.view']);

        $config = Livewire::test(Workspace::class, ['counts' => []])->get('workspaceConfig');
        $ids = collect($config['modules'])->pluck('id')->sort()->values()->all();

        $this->assertSame(['entityAttributes', 'notifications', 'purchaseRequests'], $ids);
    }

    public function test_every_module_ships_a_complete_tile_contract(): void
    {
        $this->actingAsUserWithPermissions(Permission::pluck('name')->all());

        $config = Livewire::test(Workspace::class, ['counts' => []])->get('workspaceConfig');

        $this->assertCount(23, $config['modules']);

        $ids = [];
        foreach ($config['modules'] as $module) {
            foreach (['id', 'searchable', 'label', 'route', 'icon', 'theme', 'badge'] as $key) {
                $this->assertArrayHasKey($key, $module);
            }

            $ids[] = $module['id'];
            $this->assertIsBool($module['searchable']);
            $this->assertNotSame('', $module['label']);
            $this->assertNotSame('', $module['route']);
            $this->assertStringContainsString('<svg', $module['icon']);
            $this->assertNotSame('', $module['theme']);
            $this->assertNotSame('', $module['badge']);
        }

        $this->assertSame($ids, array_unique($ids));
    }

    public function test_stats_map_snake_case_counts_to_camel_case_ids_and_default_missing_to_zero(): void
    {
        $config = Livewire::test(Workspace::class, ['counts' => [
            'purchase_requests' => '4',
            'shipments' => 12,
        ]])->get('workspaceConfig');

        $stats = $config['stats'];

        $this->assertSame(4, $stats['purchaseRequests']);
        $this->assertSame(12, $stats['shipments']);
        $this->assertSame(0, $stats['payments']);
        $this->assertSame(
            ['purchaseRequests', 'proformaInvoices', 'registeredOrders', 'bankProfiles', 'purchaseOrders', 'payments', 'shipments', 'customs'],
            array_keys($stats)
        );
    }

    public function test_records_url_keeps_the_resource_placeholder_contract(): void
    {
        $config = Livewire::test(Workspace::class, ['counts' => []])->get('workspaceConfig');

        $this->assertSame(url('/workspace/records/__RES__'), $config['recordsUrl']);
    }

    public function test_the_record_tile_is_inert_while_its_rename_edit_is_open(): void
    {
        Livewire::test(Workspace::class, ['counts' => []])
            ->assertSeeHtml('@mousedown="editingKey === p.key && $event.preventDefault()"')
            ->assertSeeHtml('@click="editingKey === p.key && $event.preventDefault()"')
            ->assertSeeHtml('@click.stop.prevent')
            ->assertSeeHtml(__('dashboard/strings.record_pin.rename'));
    }

    public function test_the_picker_renders_the_status_chip_and_the_recent_candidates_section(): void
    {
        Livewire::test(Workspace::class, ['counts' => []])
            ->assertSeeHtml('x-text="rec.status"')
            ->assertSeeHtml('x-text="p.status"')
            ->assertSeeHtml('recentCandidates()')
            ->assertSeeHtml('@click="addRecent(rec)"')
            ->assertSeeHtml('mx-2 mt-2 mb-3 rounded-lg border border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5')
            ->assertSeeHtml('h-px flex-1 rounded-full')
            ->assertSeeHtml('bg-white dark:bg-white/5 shadow-[var(--md-elevation-1)]')
            ->assertDontSeeHtml('rounded-full border')
            ->assertSeeHtml(__('dashboard/strings.record_pin.recent'));
    }

    public function test_the_recent_heading_lang_key_exists_in_all_three_locales(): void
    {
        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);
            $this->assertNotSame('dashboard/strings.record_pin.recent', __('dashboard/strings.record_pin.recent'));
        }

        app()->setLocale('en');
    }
}
