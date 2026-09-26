<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\CustomResource\Pages\EditCustom;
use App\Filament\Resources\Operational\CustomResource\RelationManagers\RegisteredOrderRelationManager;
use App\Filament\Resources\Operational\CustomResource\RelationManagers\ShipmentRelationManager;
use App\Filament\Resources\Operational\CustomResource\Traits\HandleStatusMutation;
use App\Models\Custom;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use ReflectionProperty;
use Tests\TestCase;

class CustomResourceTest extends TestCase
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

    private function statusHistoryTab(Schema $schema): ?Tab
    {
        $tabsComponent = $schema->getComponents()[0];
        $reflection = new ReflectionProperty($tabsComponent, 'childComponents');
        $reflection->setAccessible(true);

        return collect($reflection->getValue($tabsComponent)['default'])->first(function ($tab) {
            $labelReflection = new ReflectionProperty($tab, 'label');
            $labelReflection->setAccessible(true);

            return $labelReflection->getValue($tab) === __('resources/general/strings.status_history.tab_label');
        });
    }

    private function statusHistoryTabBadge(Tab $tab, $record): ?int
    {
        $reflection = new ReflectionProperty($tab, 'badge');
        $reflection->setAccessible(true);

        return ($reflection->getValue($tab))($record);
    }

    public function test_infolist_status_history_tab_renders_and_badge_matches_history_count(): void
    {
        app()->setLocale('en');
        $custom = Custom::factory()->create();
        $custom->update(['clearance_status_id' => Status::factory()->create()->id]);
        $custom->load('statusHistories');

        $tab = $this->statusHistoryTab(CustomResource::infolist(Schema::make()));

        $this->assertNotNull($tab);
        $this->assertSame($custom->statusHistories->count(), $this->statusHistoryTabBadge($tab, $custom));
    }

    public function test_registered_order_relation_manager_renders_and_exports_without_error(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['custom.view', 'registered_order.view']);

        $custom = Custom::factory()->create();

        Livewire::test(RegisteredOrderRelationManager::class, [
            'ownerRecord' => $custom,
            'pageClass' => EditCustom::class,
        ])
            ->assertSuccessful()
            ->callTableBulkAction('exportRegisteredOrders', [$custom->registeredOrder])
            ->assertHasNoTableBulkActionErrors();

        Queue::assertPushed(\App\Jobs\ExportRegisteredOrders::class);
    }

    public function test_shipment_relation_manager_renders_for_a_custom_with_a_shipment(): void
    {
        $this->actingAsUserWithPermissions(['custom.view', 'shipment.view']);

        $custom = Custom::factory()->create();

        Livewire::test(ShipmentRelationManager::class, [
            'ownerRecord' => $custom,
            'pageClass' => EditCustom::class,
        ])->assertSuccessful();
    }

    private function statusMutator(): object
    {
        return new class
        {
            use HandleStatusMutation;

            public function run(array $data, ?Custom $record = null): void
            {
                $this->assertCustomStatusTransitionsAllowed($data, $record);
            }
        };
    }

    public function test_status_transitions_are_unrestricted_today_for_all_three_columns_with_no_stage_order_configured(): void
    {
        $clearance = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => null]);
        $guarantee = Status::factory()->create(['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS, 'stage_order' => null]);
        $commitment = Status::factory()->create(['english_type' => Custom::TYPE_COMMITMENT_STATUS, 'stage_order' => null]);
        $custom = Custom::factory()->create();

        $this->statusMutator()->run([
            'clearance_status_id' => $clearance->id,
            'bank_guarantee_status_id' => $guarantee->id,
            'commitment_status_id' => $commitment->id,
        ], $custom);

        $this->assertTrue(true);
    }

    public function test_server_side_transition_guard_rejects_a_skipped_stage_once_stage_order_is_configured(): void
    {
        $stage1 = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => 1]);
        Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => 2]);
        $stage3 = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => 3]);
        $custom = Custom::factory()->create(['clearance_status_id' => $stage1->id]);

        $this->expectException(ValidationException::class);

        $this->statusMutator()->run(['clearance_status_id' => $stage3->id], $custom);
    }

    public function test_editing_clearance_and_guarantee_status_still_succeeds_with_no_stage_order_configured(): void
    {
        $this->actingAsUserWithPermissions(['custom.view', 'custom.edit']);

        $newClearance = Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => null]);
        $newGuarantee = Status::factory()->create(['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS, 'stage_order' => null]);
        $custom = Custom::factory()->create([
            'clearance_status_id' => Status::factory()->create(['english_type' => Custom::TYPE_CLEARANCE_STATUS, 'stage_order' => null])->id,
            'bank_guarantee_status_id' => Status::factory()->create(['english_type' => Custom::TYPE_BANK_GUARANTEE_STATUS, 'stage_order' => null])->id,
            'commitment_status_id' => Status::factory()->create(['english_type' => Custom::TYPE_COMMITMENT_STATUS, 'stage_order' => null])->id,
        ]);

        Livewire::test(EditCustom::class, ['record' => $custom->getRouteKey()])
            ->set('data.clearance_status_id', $newClearance->id)
            ->set('data.bank_guarantee_status_id', $newGuarantee->id)
            ->call('save')
            ->assertHasNoErrors();

        $custom->refresh();
        $this->assertSame($newClearance->id, $custom->clearance_status_id);
        $this->assertSame($newGuarantee->id, $custom->bank_guarantee_status_id);
    }
}
