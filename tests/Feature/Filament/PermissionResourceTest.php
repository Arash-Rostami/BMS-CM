<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Master\PermissionResource\Pages\ManagePermissions;
use App\Filament\Resources\PermissionResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class PermissionResourceTest extends TestCase
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

        $role = Role::create(['name' => 'agent_test_role_'.uniqid(), 'guard_name' => 'web']);
        foreach ($permissionNames as $permissionName) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']));
        }
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    // Count columns render as icon badges, not raw numbers

    public function test_count_columns_render_as_icon_badges_with_color_semantics(): void
    {
        $this->actingAsUserWithPermissions(['permission.view']);

        $roles = PermissionResource::showRolesCount();
        $users = PermissionResource::showUsersCount();

        $this->assertTrue($roles->isBadge());
        $this->assertSame('heroicon-o-user-group', $roles->getIcon(null));
        $this->assertSame('gray', $roles->getColor(0));
        $this->assertSame('info', $roles->getColor(3));

        $this->assertTrue($users->isBadge());
        $this->assertSame('heroicon-o-users', $users->getIcon(null));
        $this->assertSame('gray', $users->getColor(0));
        $this->assertSame('success', $users->getColor(2));
    }

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'permission.view',
            'permission.create',
            'permission.edit',
            'permission.delete',
        ]);

        $record = Permission::factory()->create();

        $this->assertTrue(PermissionResource::canViewAny());
        $this->assertTrue(PermissionResource::canCreate());
        $this->assertTrue(PermissionResource::canEdit($record));
        $this->assertTrue(PermissionResource::canDelete($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Permission::factory()->create();

        $this->assertFalse(PermissionResource::canViewAny());
        $this->assertFalse(PermissionResource::canCreate());
        $this->assertFalse(PermissionResource::canEdit($record));
        $this->assertFalse(PermissionResource::canDelete($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_name(): void
    {
        $this->actingAsUserWithPermissions(['permission.view']);

        $target = Permission::factory()->create(['name' => 'search_target_module.view']);
        $other = Permission::factory()->create(['name' => 'search_other_module.view']);

        Livewire::test(ManagePermissions::class)
            ->searchTable('search_target_module')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Search also matches the translated label shown on screen, not just the raw stored name

    public function test_search_matches_the_translated_label_not_just_the_raw_name(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['permission.view']);

        $purchaseRequest = Permission::firstOrCreate(['name' => 'purchase_request.view', 'guard_name' => 'web']);
        $unrelated = Permission::firstOrCreate(['name' => 'bank.create', 'guard_name' => 'web']);

        Livewire::test(ManagePermissions::class)
            ->searchTable('Purchase Request')
            ->assertCanSeeTableRecords([$purchaseRequest])
            ->assertCanNotSeeTableRecords([$unrelated]);
    }

    // Create — happy path + validation

    public function test_create_happy_path_saves_a_new_permission(): void
    {
        $this->actingAsUserWithPermissions(['permission.view', 'permission.create']);

        Livewire::test(ManagePermissions::class)
            ->callAction('create', data: [
                'name' => 'brand_new_module.view',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('permissions', ['name' => 'brand_new_module.view']);
    }

    public function test_create_rejects_a_duplicate_name(): void
    {
        $this->actingAsUserWithPermissions(['permission.view', 'permission.create']);
        Permission::factory()->create(['name' => 'duplicate_module.view']);

        Livewire::test(ManagePermissions::class)
            ->callAction('create', data: [
                'name' => 'duplicate_module.view',
            ])
            ->assertHasActionErrors(['name' => 'unique']);
    }

    public function test_create_rejects_a_nonexistent_role_id_with_translated_message(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['permission.view', 'permission.create']);

        $test = Livewire::test(ManagePermissions::class)
            ->mountAction('create')
            ->fillForm([
                'name' => 'another_module.view',
                'roles' => [999999],
            ])
            ->callMountedAction();

        $this->assertSame(
            [__('resources/permission/strings.form.validation_roles_in')],
            $test->errors()->get('mountedActions.0.data.roles.0')
        );
    }

    // Edit — via table action (direct fields only, no relationship-bound Select involved)

    public function test_edit_action_updates_the_name(): void
    {
        $this->actingAsUserWithPermissions(['permission.view', 'permission.edit']);
        $record = Permission::factory()->create(['name' => 'before_module.view']);

        Livewire::test(ManagePermissions::class)
            ->mountTableAction('edit', $record)
            ->fillForm(['name' => 'after_module.view'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('after_module.view', $record->fresh()->name);
    }

    // Bulk delete

    public function test_bulk_delete_removes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['permission.view', 'permission.delete']);
        $one = Permission::factory()->create();
        $two = Permission::factory()->create();

        Livewire::test(ManagePermissions::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertDatabaseMissing('permissions', ['id' => $one->id]);
        $this->assertDatabaseMissing('permissions', ['id' => $two->id]);
    }

    public function test_delete_actions_warn_with_role_and_user_counts(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['permission.view', 'permission.delete']);
        $one = Permission::factory()->create();
        $two = Permission::factory()->create();
        $role = Role::create(['name' => 'agent_test_role_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo([$one, $two]);
        $one->users()->attach(User::factory()->create());

        $row = fn ($c) => __('resources/permission/strings.actions.delete_warning', $c);

        Livewire::test(ManagePermissions::class)
            ->mountTableAction('delete', $one)
            ->assertMountedActionModalSee($row(['roles' => 1, 'users' => 1]))
            ->unmountTableAction()
            ->mountTableBulkAction('delete', [$one, $two])
            ->assertMountedActionModalSee($row(['roles' => 2, 'users' => 1]));
    }

    public function test_view_infolist_renders_users_holding_the_permission_directly(): void
    {
        $this->actingAsUserWithPermissions(['permission.view']);
        $record = Permission::factory()->create();
        $holder = User::factory()->create();
        $record->users()->attach($holder);

        Livewire::test(ManagePermissions::class)
            ->mountTableAction('view', $record)
            ->assertMountedActionModalSee($holder->name);

        $this->assertSame('users.name', PermissionResource::viewUsers()->getName());
    }

    public function test_ungranted_filter_keeps_only_permissions_with_no_roles_and_no_users(): void
    {
        $this->actingAsUserWithPermissions(['permission.view']);

        $orphan = Permission::factory()->create();
        $withRole = Permission::factory()->create();
        $withUser = Permission::factory()->create();
        Role::create(['name' => 'agent_test_role_'.uniqid(), 'guard_name' => 'web'])->givePermissionTo($withRole);
        $withUser->users()->attach(User::factory()->create());

        Livewire::test(ManagePermissions::class)
            ->filterTable('ungranted', true)
            ->assertCanSeeTableRecords([$orphan])
            ->assertCanNotSeeTableRecords([$withRole, $withUser]);
    }

    // No import — Permission is pure reference data reused by Role; export was added 2026-10-07 per QA request

    public function test_no_import_action_exists(): void
    {
        $this->actingAsUserWithPermissions(['permission.view', 'permission.create']);

        $livewire = Livewire::test(ManagePermissions::class);
        $livewire->assertActionExists('create');

        $this->assertFalse(method_exists(PermissionResource::class, 'getImportAction'));
    }

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['permission.view']);

        $record = Permission::factory()->create();

        Livewire::test(ManagePermissions::class)
            ->callTableBulkAction('exportPermissions', [$record]);

        Queue::assertPushed(\App\Jobs\ExportPermissions::class);
    }
}
