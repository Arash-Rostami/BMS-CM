<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Master\StatusResource\Exports\StatusExporter;
use App\Filament\Resources\Master\StatusResource\Pages\ManageStatuses;
use App\Filament\Resources\StatusResource;
use App\Jobs\ExportStatuses;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
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

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'status.view',
            'status.create',
            'status.edit',
            'status.delete',
            'status.restore',
        ]);

        $record = Status::factory()->create();

        $this->assertTrue(StatusResource::canViewAny());
        $this->assertTrue(StatusResource::canCreate());
        $this->assertTrue(StatusResource::canEdit($record));
        $this->assertTrue(StatusResource::canDelete($record));
        $this->assertTrue(StatusResource::canRestore($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Status::factory()->create();

        $this->assertFalse(StatusResource::canViewAny());
        $this->assertFalse(StatusResource::canCreate());
        $this->assertFalse(StatusResource::canEdit($record));
        $this->assertFalse(StatusResource::canDelete($record));
        $this->assertFalse(StatusResource::canRestore($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_english_name(): void
    {
        $this->actingAsUserWithPermissions(['status.view']);

        $target = Status::factory()->create();
        $other = Status::factory()->create();

        $term = 'STATUS-SEARCH-TARGET-'.$target->id;
        Status::whereKey($target->id)->update(['english_name' => $term]);
        Status::whereKey($other->id)->update(['english_name' => 'STATUS-SEARCH-OTHER-'.$other->id]);
        $target->refresh();
        $other->refresh();

        Livewire::test(ManageStatuses::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable($term)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Filters

    public function test_type_filter_narrows_the_table_by_english_type(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['status.view']);

        $typeA = 'FilterTypeA'.uniqid();
        $typeB = 'FilterTypeB'.uniqid();
        $matching = Status::factory()->create(['type' => $typeA, 'english_type' => $typeA]);
        $other = Status::factory()->create(['type' => $typeB, 'english_type' => $typeB]);

        Livewire::test(ManageStatuses::class)
            ->filterTable('english_type', [$typeA])
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['status.view']);
        $withA = Status::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = Status::factory()->create();

        $this->actingAs($userA);

        Livewire::test(ManageStatuses::class)
            ->filterTable('created_by_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_updater_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['status.view']);
        $withA = Status::factory()->create();
        $withA->update(['stage_order' => 1]);

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = Status::factory()->create();
        $withB->update(['stage_order' => 1]);

        $this->actingAs($userA);

        Livewire::test(ManageStatuses::class)
            ->filterTable('updated_by_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    // Create — happy path + validation

    public function test_create_action_creates_a_new_status_reusing_an_existing_type(): void
    {
        $this->actingAsUserWithPermissions(['status.view', 'status.create']);

        $type = 'CreateHappyType'.uniqid();
        Status::factory()->create(['type' => $type, 'english_type' => $type]);

        $name = 'نام تازه '.random_int(1000, 9999);
        $englishName = 'New Status '.uniqid();

        Livewire::test(ManageStatuses::class)
            ->mountAction('create')
            ->fillForm([
                'type' => $type,
                'english_type' => $type,
                'name' => $name,
                'english_name' => $englishName,
                'requires_approval' => false,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $record = Status::where('english_name', $englishName)->first();

        $this->assertNotNull($record);
        $this->assertSame($name, $record->name);
        $this->assertSame($type, $record->type);
        $this->assertNull($record->approval_permission);
    }

    public function test_create_action_requires_name_and_english_name(): void
    {
        $this->actingAsUserWithPermissions(['status.view', 'status.create']);

        $type = 'ValidationType'.uniqid();
        Status::factory()->create(['type' => $type, 'english_type' => $type]);

        Livewire::test(ManageStatuses::class)
            ->mountAction('create')
            ->fillForm([
                'type' => $type,
                'english_type' => $type,
                'name' => '',
                'english_name' => '',
            ])
            ->callMountedAction()
            ->assertHasActionErrors(['name' => 'required', 'english_name' => 'required']);
    }

    // Edit — plain field update, independent of the approval workflow

    public function test_edit_action_updates_the_name_and_english_name(): void
    {
        $this->actingAsUserWithPermissions(['status.view', 'status.edit']);

        $status = Status::factory()->create(['name' => 'وضعیت آزمایشی '.random_int(1000, 9999)]);
        $newName = 'وضعیت ویرایش شده '.random_int(1000, 9999);
        $newEnglishName = 'Edited Status '.uniqid();

        Livewire::test(ManageStatuses::class)
            ->mountTableAction('edit', $status)
            ->fillForm([
                'name' => $newName,
                'english_name' => $newEnglishName,
            ])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $status->refresh();

        $this->assertSame($newName, $status->name);
        $this->assertSame($newEnglishName, $status->english_name);
    }

    // Bulk actions — toolbar order

    public function test_bulk_actions_toolbar_orders_export_before_delete_and_restore(): void
    {
        $this->actingAsUserWithPermissions(['status.view', 'status.delete', 'status.restore']);

        Livewire::test(ManageStatuses::class)
            ->assertTableBulkActionsExistInOrder(['exportStatuses', 'delete', 'restore']);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $this->actingAsUserWithPermissions(['status.view', 'status.delete', 'status.restore']);
        $record = Status::factory()->create();

        Livewire::test(ManageStatuses::class)
            ->callTableAction('delete', $record);

        $this->assertNull(Status::find($record->id));
        $this->assertTrue(Status::withTrashed()->find($record->id)->trashed());

        Livewire::test(ManageStatuses::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(Status::find($record->id));
    }

    public function test_bulk_delete_soft_deletes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['status.view', 'status.delete']);
        $one = Status::factory()->create();
        $two = Status::factory()->create();

        Livewire::test(ManageStatuses::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertNull(Status::find($one->id));
        $this->assertNull(Status::find($two->id));
    }

    // Global search contract

    public function test_global_search_title_uses_the_translated_template(): void
    {
        app()->setLocale('en');
        $record = Status::factory()->create(['english_name' => 'Global Search Status '.uniqid()]);

        $expected = __('resources/status/strings.general.global_search_title', [
            'name' => $record->getLocalizedNameAttribute(),
            'date' => toYmdDate($record),
        ]);

        $this->assertSame($expected, StatusResource::getGlobalSearchResultTitle($record));
    }

    // Export — bulk action dispatches the queued job

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['status.view']);

        $record = Status::factory()->create();

        Livewire::test(ManageStatuses::class)
            ->callTableBulkAction('exportStatuses', [$record]);

        Queue::assertPushed(ExportStatuses::class, fn ($job) => $job->ids === [$record->id]);
    }

    // Export — flat single-row round trip

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'status_export_').'.csv';
        StatusExporter::write($query, $path);

        $csv = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $rows = array_map(fn (string $line) => array_combine($header, str_getcsv($line)), array_slice($lines, 1));

        return ['header' => $header, 'rows' => $rows];
    }

    public function test_exporter_write_emits_one_row_per_record_with_localized_values(): void
    {
        app()->setLocale('en');
        $creator = User::factory()->create(['name' => 'Export Creator']);

        $this->actingAs($creator);
        $record = Status::factory()->create([
            'name' => 'وضعیت صادراتی',
            'english_name' => 'Export Status '.uniqid(),
        ]);

        ['header' => $header, 'rows' => $rows] = $this->exportToRows(Status::whereKey($record->id));

        $labels = StatusExporter::columnLabels();

        $this->assertCount(9, $header);
        $this->assertSame($record->english_name, $rows[0][$labels['english_name']]);
        $this->assertSame('Export Creator', $rows[0][$labels['creator']]);
        $this->assertSame(jdate($record->created_at)->format('Y-m-d'), $rows[0][$labels['created_at']]);
    }

    public function test_export_column_count_is_pinned(): void
    {
        // Pinned count — a silent column drop during a future refactor must fail
        // this test, not slip through unnoticed (see importsPattern's settled policy).
        $this->assertCount(9, StatusExporter::columnLabels());
    }

    // Status gets export only — no bulk import action exists

    public function test_no_import_action_exists_alongside_create_and_export(): void
    {
        $this->actingAsUserWithPermissions(['status.view', 'status.create', 'status.delete', 'status.restore']);

        $test = Livewire::test(ManageStatuses::class);

        $test->assertActionExists('create');
        $test->assertTableBulkActionExists('exportStatuses');

        $headerActionNames = collect($test->instance()->getCachedHeaderActions())
            ->flatMap(fn ($action) => $action instanceof ActionGroup ? $action->getFlatActions() : [$action])
            ->map(fn ($action) => $action->getName())
            ->all();

        $this->assertNotEmpty($headerActionNames);

        $bulkActionNames = array_keys($test->instance()->getTable()->getFlatBulkActions());

        $this->assertNotEmpty($bulkActionNames);

        foreach ([...$headerActionNames, ...$bulkActionNames] as $name) {
            $this->assertStringNotContainsStringIgnoringCase('import', $name);
        }
    }
}
