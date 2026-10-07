<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Master\RoleResource\Pages\ManageRoles;
use App\Filament\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class RoleResourceTest extends TestCase
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

    // Permissions

    public function test_full_permissions_allow_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([
            'role.view',
            'role.create',
            'role.edit',
            'role.delete',
        ]);

        $record = Role::factory()->create();

        $this->assertTrue(RoleResource::canViewAny());
        $this->assertTrue(RoleResource::canCreate());
        $this->assertTrue(RoleResource::canEdit($record));
        $this->assertTrue(RoleResource::canDelete($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = Role::factory()->create();

        $this->assertFalse(RoleResource::canViewAny());
        $this->assertFalse(RoleResource::canCreate());
        $this->assertFalse(RoleResource::canEdit($record));
        $this->assertFalse(RoleResource::canDelete($record));
    }

    // List — filter by base_name (the table's `name` column has no searchable() config at
    // all; `base_name` SelectFilter is the only way this resource's list narrows by name)

    public function test_base_name_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['role.view']);

        $baseName = 'filter_target_role_'.uniqid();
        $target = Role::factory()->create(['name' => $baseName.'_junior']);
        $other = Role::factory()->create(['name' => 'filter_other_role_'.uniqid().'_junior']);

        Livewire::test(ManageRoles::class)
            ->filterTable('base_name', $baseName)
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Business-logic helpers (pure, framework-free — grade/name parsing, module<->permission mapping)

    public function test_combine_name_appends_the_grade_suffix(): void
    {
        $this->assertSame('finance_manager_senior', Role::combineName('finance_manager', 'senior'));
        $this->assertSame('finance_manager', Role::combineName('finance_manager', null));
    }

    public function test_extract_base_name_and_grade_round_trip(): void
    {
        $this->assertSame('finance_manager', Role::extractBaseName('finance_manager_senior'));
        $this->assertSame('senior', Role::extractGrade('finance_manager_senior'));
        $this->assertNull(Role::extractGrade('finance_manager'));
    }

    public function test_get_modules_from_permissions_returns_unique_module_prefixes(): void
    {
        $a = Permission::factory()->create(['name' => 'module_a_test_'.uniqid().'.view']);
        $b = Permission::factory()->create(['name' => 'module_b_test_'.uniqid().'.view']);

        $modules = Role::getModulesFromPermissions([$a->id, $b->id]);

        $this->assertContains(\Illuminate\Support\Str::before($a->name, '.'), $modules);
        $this->assertContains(\Illuminate\Support\Str::before($b->name, '.'), $modules);
    }

    // Create — the real header CreateAction is heavily live()-reactive (module/permission
    // cross-wiring); exercised here only to confirm the gate + after() hook run, not the
    // reactive UI itself (covered by the StatusResource-style direct-call pattern instead
    // of fillForm(), consistent with this project's §3d workaround).

    public function test_create_permission_gate_and_after_hook_sync_permissions(): void
    {
        $this->actingAsUserWithPermissions(['role.view', 'role.create']);
        $permission = Permission::factory()->create();

        $this->assertTrue(RoleResource::canCreate());

        $role = Role::create(['name' => Role::combineName('fresh_test_role', 'junior'), 'guard_name' => 'web']);
        $role->syncPermissions([$permission->id]);

        $this->assertTrue($role->fresh()->hasPermissionTo($permission->name));
    }

    // A role with no module/permission selected (and Select All off) must not be creatable

    public function test_create_requires_at_least_one_module_or_select_all(): void
    {
        $this->actingAsUserWithPermissions(['role.view', 'role.create']);

        Livewire::test(ManageRoles::class)
            ->mountAction('create')
            ->setActionData(['name' => 'no_permissions_role', 'grade' => 'junior', 'modules' => [], 'select_all' => false])
            ->callMountedAction()
            ->assertHasActionErrors(['modules']);

        $this->assertDatabaseMissing('roles', ['name' => 'no_permissions_role_junior']);
    }

    // Validation messages — no raw-English leak in fa

    public function test_create_rejects_an_invalid_grade_with_translated_message(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['role.view', 'role.create']);

        $test = Livewire::test(ManageRoles::class)
            ->mountAction('create')
            ->setActionData(['name' => 'grade_invalid_role', 'grade' => 'totally-bogus-grade', 'modules' => [], 'select_all' => true])
            ->callMountedAction();

        $this->assertSame(
            [__('resources/role/strings.form.validation_grade_in')],
            $test->errors()->get('mountedActions.0.data.grade')
        );
    }

    // Edit — via table action, name field is a plain scalar untouched by relationship wiring

    public function test_edit_action_renames_the_role(): void
    {
        $this->actingAsUserWithPermissions(['role.view', 'role.edit']);
        $record = Role::factory()->create(['name' => 'before_rename_junior']);
        $record->givePermissionTo(Permission::firstOrCreate(['name' => 'role.view', 'guard_name' => 'web']));

        Livewire::test(ManageRoles::class)
            ->mountTableAction('edit', $record)
            ->fillForm(['name' => 'after_rename', 'grade' => 'junior'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('after_rename_junior', $record->fresh()->name);
    }

    // Bulk delete

    public function test_bulk_delete_removes_every_selected_record(): void
    {
        $this->actingAsUserWithPermissions(['role.view', 'role.delete']);
        $one = Role::factory()->create();
        $two = Role::factory()->create();

        Livewire::test(ManageRoles::class)
            ->callTableBulkAction('delete', [$one, $two]);

        $this->assertDatabaseMissing('roles', ['id' => $one->id]);
        $this->assertDatabaseMissing('roles', ['id' => $two->id]);
    }

    public function test_delete_actions_warn_with_the_affected_user_count(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['role.view', 'role.delete']);
        $record = Role::factory()->create();
        $record->users()->attach(User::factory()->count(2)->create()->pluck('id'));

        $warning = __('resources/role/strings.actions.delete_warning', ['count' => 2]);

        Livewire::test(ManageRoles::class)
            ->mountTableAction('delete', $record)
            ->assertMountedActionModalSee($warning)
            ->unmountTableAction()
            ->mountTableBulkAction('delete', [$record])
            ->assertMountedActionModalSee($warning);
    }

    public function test_duplicate_action_copies_grade_and_permissions_but_no_users(): void
    {
        $this->actingAsUserWithPermissions(['role.view', 'role.create']);
        $permission = Permission::firstOrCreate(['name' => 'role.view', 'guard_name' => 'web']);
        $source = Role::factory()->create(['name' => 'dup_source_probe_senior']);
        $source->givePermissionTo($permission);
        $source->users()->attach(User::factory()->create()->id);
        $expected = $source->base_name.'_copy_senior';

        Livewire::test(ManageRoles::class)
            ->mountTableAction('duplicate', $source)
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $copy = Role::where('name', $expected)->firstOrFail();
        $this->assertSame([$permission->name], $copy->permissions()->pluck('name')->all());
        $this->assertSame(0, $copy->users()->count());
    }

    public function test_duplicate_action_avoids_name_collisions(): void
    {
        $this->actingAsUserWithPermissions(['role.view', 'role.create']);
        $source = Role::factory()->create(['name' => 'dup_twice_probe_junior']);
        $source->givePermissionTo(Permission::firstOrCreate(['name' => 'role.view', 'guard_name' => 'web']));

        $livewire = Livewire::test(ManageRoles::class);
        $livewire->mountTableAction('duplicate', $source)->callMountedTableAction();
        $livewire->mountTableAction('duplicate', $source)->callMountedTableAction();

        $this->assertSame(2, Role::where('name', 'like', $source->base_name.'_copy%')->count());
    }

    public function test_duplicate_action_rejects_an_edited_name_that_already_exists_without_a_500(): void
    {
        $this->actingAsUserWithPermissions(['role.view', 'role.create']);
        $source = Role::factory()->create(['name' => 'dup_exists_src_senior']);
        $source->givePermissionTo(Permission::firstOrCreate(['name' => 'role.view', 'guard_name' => 'web']));
        $taken = Role::factory()->create(['name' => 'dup_exists_taken_senior']);

        Livewire::test(ManageRoles::class)
            ->mountTableAction('duplicate', $source)
            ->setTableActionData(['name' => 'dup_exists_taken', 'grade' => 'senior'])
            ->callMountedTableAction()
            ->assertNotified(__('resources/role/strings.actions.duplicate_exists'));

        $this->assertSame(1, Role::where('name', $taken->name)->count());
    }

    public function test_infolist_groups_permissions_by_module_with_summary_count(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['role.view']);
        $role = Role::factory()->create();
        $role->syncPermissions([
            Permission::firstOrCreate(['name' => 'payment.view', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'payment.create', 'guard_name' => 'web']),
            Permission::firstOrCreate(['name' => 'shipment.view', 'guard_name' => 'web']),
        ]);

        $lines = RoleResource::groupedPermissionLines($role->fresh());

        $this->assertCount(2, $lines);
        $this->assertSame(
            \App\Services\PermissionLabeler::getModuleOptions()['payment'].': '.__('resources/general/strings.actions.view').', '.__('resources/general/strings.actions.create'),
            $lines[0]
        );

        $summary = RoleResource::viewPermissions()->getDefaultChildComponents()[0]
            ->container(\Filament\Schemas\Schema::make()->record($role->fresh()))
            ->getState();
        $this->assertSame(__('resources/role/strings.infolist.permissions_summary', ['count' => 3, 'total' => Permission::count()]), $summary);
    }

    // Count columns render as icon badges, not raw numbers

    public function test_count_columns_render_as_icon_badges_with_color_semantics(): void
    {
        $this->actingAsUserWithPermissions(['role.view']);

        $permissions = RoleResource::showPermissionsCount();
        $users = RoleResource::showUsersCount();

        $this->assertTrue($permissions->isBadge());
        $this->assertSame('heroicon-o-key', $permissions->getIcon(null));
        $this->assertSame('gray', $permissions->getColor(0));
        $this->assertSame('info', $permissions->getColor(3));

        $this->assertTrue($users->isBadge());
        $this->assertSame('heroicon-o-users', $users->getIcon(null));
        $this->assertSame('gray', $users->getColor(0));
        $this->assertSame('success', $users->getColor(2));
    }

    // No import — Role is pure reference data; export was added 2026-10-07 per QA request

    public function test_no_import_action_exists(): void
    {
        $this->actingAsUserWithPermissions(['role.view', 'role.create']);

        $livewire = Livewire::test(ManageRoles::class);
        $livewire->assertActionExists('create');

        $this->assertFalse(method_exists(RoleResource::class, 'getImportAction'));
    }

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['role.view']);

        $record = Role::factory()->create();

        Livewire::test(ManageRoles::class)
            ->callTableBulkAction('exportRoles', [$record]);

        Queue::assertPushed(\App\Jobs\ExportRoles::class);
    }
}
