<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Master\StatusResource\Pages\ManageStatuses;
use App\Filament\Resources\StatusResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class StatusResourceTest extends TestCase
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

    // processApprovalWorkflow — permission naming/generation

    public function test_process_approval_workflow_generates_a_deterministic_permission_name_on_create(): void
    {
        $suffix = uniqid();

        $data = StatusResource::processApprovalWorkflow([
            'requires_approval' => true,
            'english_type' => 'PurchaseRequestTest'.$suffix,
            'english_name' => 'Sales Manager Approval',
        ]);

        $this->assertSame('status.grant_purchase_request_test'.$suffix.'_sales_manager_approval', $data['approval_permission']);
        $this->assertTrue(Permission::where('name', $data['approval_permission'])->exists());
    }

    public function test_process_approval_workflow_reuses_the_existing_permission_on_edit(): void
    {
        $status = Status::factory()->create(['approval_permission' => 'status.grant_custom_flow_step']);
        Permission::firstOrCreate(['name' => 'status.grant_custom_flow_step', 'guard_name' => 'web']);

        $data = StatusResource::processApprovalWorkflow([
            'requires_approval' => true,
            'english_type' => $status->english_type,
            'english_name' => 'Renamed Step',
        ], $status);

        $this->assertSame('status.grant_custom_flow_step', $data['approval_permission']);
    }

    public function test_process_approval_workflow_avoids_colliding_with_an_existing_permission_name(): void
    {
        Permission::firstOrCreate(['name' => 'status.grant_purchase_order_final_check', 'guard_name' => 'web']);

        $data = StatusResource::processApprovalWorkflow([
            'requires_approval' => true,
            'english_type' => 'PurchaseOrder',
            'english_name' => 'Final Check',
        ]);

        $this->assertSame('status.grant_purchase_order_final_check_2', $data['approval_permission']);
    }

    public function test_process_approval_workflow_off_clears_the_gate_and_keeps_the_previous_permission_for_revocation(): void
    {
        $status = Status::factory()->create(['approval_permission' => 'status.grant_shipment_final_release']);

        $data = StatusResource::processApprovalWorkflow(['requires_approval' => false], $status);

        $this->assertNull($data['approval_permission']);
        $this->assertSame('status.grant_shipment_final_release', $data['previous_approval_permission']);
    }

    // syncApprovalUsers — scoped grant/revoke

    public function test_sync_approval_users_grants_the_selected_users_without_touching_unrelated_permissions(): void
    {
        $permissionName = 'status.grant_test_sync_'.uniqid();
        Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);

        $keptUser = User::factory()->create();
        $unrelatedPermission = Permission::firstOrCreate(['name' => 'status.grant_unrelated_'.uniqid(), 'guard_name' => 'web']);
        $keptUser->givePermissionTo($unrelatedPermission);

        $newUser = User::factory()->create();

        StatusResource::syncApprovalUsers([
            'approval_permission' => $permissionName,
            'approval_users' => [$keptUser->id, $newUser->id],
        ]);

        $this->assertTrue($keptUser->fresh()->hasPermissionTo($permissionName));
        $this->assertTrue($newUser->fresh()->hasPermissionTo($permissionName));
        $this->assertTrue($keptUser->fresh()->hasPermissionTo($unrelatedPermission->name));
    }

    public function test_sync_approval_users_revokes_users_dropped_from_the_selection(): void
    {
        $permissionName = 'status.grant_test_revoke_'.uniqid();
        Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo($permissionName);

        StatusResource::syncApprovalUsers([
            'approval_permission' => $permissionName,
            'approval_users' => [],
        ]);

        $this->assertFalse($user->fresh()->hasPermissionTo($permissionName));
    }

    public function test_sync_approval_users_revokes_every_user_when_the_gate_is_turned_off(): void
    {
        $permissionName = 'status.grant_test_turn_off_'.uniqid();
        Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo($permissionName);

        StatusResource::syncApprovalUsers([
            'previous_approval_permission' => $permissionName,
            'approval_permission' => null,
        ]);

        $this->assertFalse($user->fresh()->hasPermissionTo($permissionName));
    }

    // Edit action — full Livewire flow

    public function test_edit_action_enabling_approval_persists_the_permission_and_grants_the_selected_user(): void
    {
        $this->actingAsUserWithPermissions(['status.view', 'status.edit']);

        $status = Status::factory()->create(['name' => 'وضعیت آزمایشی '.random_int(1000, 9999)]);
        $grantee = User::factory()->create();

        Livewire::test(ManageStatuses::class)
            ->mountTableAction('edit', $status)
            ->fillForm([
                'requires_approval' => true,
                'approval_users' => [$grantee->id],
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $status->refresh();

        $this->assertNotNull($status->approval_permission);
        $this->assertTrue($grantee->fresh()->hasPermissionTo($status->approval_permission));
    }

    public function test_edit_action_disabling_approval_clears_the_gate_and_revokes_users(): void
    {
        $this->actingAsUserWithPermissions(['status.view', 'status.edit']);

        $permissionName = 'status.grant_test_disable_'.uniqid();
        Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);

        $status = Status::factory()->create([
            'name' => 'وضعیت آزمایشی '.random_int(1000, 9999),
            'approval_permission' => $permissionName,
        ]);
        $grantee = User::factory()->create();
        $grantee->givePermissionTo($permissionName);

        Livewire::test(ManageStatuses::class)
            ->mountTableAction('edit', $status)
            ->fillForm(['requires_approval' => false])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $status->refresh();

        $this->assertNull($status->approval_permission);
        $this->assertFalse($grantee->fresh()->hasPermissionTo($permissionName));
    }

    // Table indicator

    public function test_approval_gate_table_column_reflects_gated_and_ungated_state(): void
    {
        $this->actingAsUserWithPermissions(['status.view']);

        $gated = Status::factory()->create(['approval_permission' => 'status.grant_test_column_'.uniqid()]);
        $ungated = Status::factory()->create(['approval_permission' => null]);

        Livewire::test(ManageStatuses::class)
            ->assertTableColumnStateSet('approval_permission', true, $gated)
            ->assertTableColumnStateSet('approval_permission', false, $ungated);
    }

    // Infolist labels

    public function test_infolist_approval_workflow_entries_use_the_translated_labels(): void
    {
        app()->setLocale('en');

        $this->assertSame('Stage Order', StatusResource::viewStageOrder()->getLabel());
        $this->assertSame('Approval Gate', StatusResource::viewApprovalGate()->getLabel());
        $this->assertSame('Approved Users', StatusResource::viewApprovalUsers()->getLabel());
    }
}
