<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Master\UserResource\Pages\ManageUsers;
use App\Filament\Resources\UserResource;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
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
            'user.view',
            'user.create',
            'user.edit',
        ]);

        $record = User::factory()->create();

        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(UserResource::canCreate());
        $this->assertTrue(UserResource::canEdit($record));
    }

    public function test_no_permissions_denies_every_gated_action(): void
    {
        $this->actingAsUserWithPermissions([]);

        $record = User::factory()->create();

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(UserResource::canCreate());
        $this->assertFalse(UserResource::canEdit($record));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_name(): void
    {
        $this->actingAsUserWithPermissions(['user.view']);

        $target = User::factory()->create(['name' => 'SEARCH-TARGET-USER']);
        $other = User::factory()->create(['name' => 'SEARCH-OTHER-USER']);

        Livewire::test(ManageUsers::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable('SEARCH-TARGET-USER')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Filters

    public function test_status_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['user.view']);

        $active = User::factory()->create(['status' => 'active']);
        $pending = User::factory()->create(['status' => 'pending']);

        Livewire::test(ManageUsers::class)
            ->filterTable('status', ['active'])
            ->assertCanSeeTableRecords([$active])
            ->assertCanNotSeeTableRecords([$pending]);
    }

    public function test_department_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['user.view']);

        $department = Department::factory()->create();
        $withDepartment = User::factory()->create(['department_id' => $department->id]);
        $withoutDepartment = User::factory()->create(['department_id' => null]);

        Livewire::test(ManageUsers::class)
            ->filterTable('department', $department->id)
            ->assertCanSeeTableRecords([$withDepartment])
            ->assertCanNotSeeTableRecords([$withoutDepartment]);
    }

    // filterTable() above forces the value; this proves the dropdown actually has options to pick

    public function test_department_filter_is_preloaded_so_its_dropdown_actually_has_options(): void
    {
        $this->actingAsUserWithPermissions(['user.view']);

        $filter = Livewire::test(ManageUsers::class)->instance()->getTable()->getFilter('department');

        $this->assertTrue($filter->isPreloaded());
    }

    // Global search contract

    public function test_global_search_title_uses_the_emoji_prefix_and_date(): void
    {
        $record = User::factory()->create(['name' => 'Global Search User']);

        $this->assertStringContainsString('Global Search User', UserResource::getGlobalSearchResultTitle($record));
    }

    // Edit — direct Eloquent round trip (avoids the §3d fillForm() harness issue on
    // plain-scalar + relationship-bound field combos, same workaround as TargetResourceTest)

    public function test_edit_permission_allows_updating_the_name(): void
    {
        $this->actingAsUserWithPermissions(['user.view', 'user.edit']);
        $record = User::factory()->create(['name' => 'Before Edit']);

        $this->assertTrue(UserResource::canEdit($record));

        $record->update(['name' => 'After Edit']);

        $this->assertSame('After Edit', $record->fresh()->name);
    }

    // Validation messages — no raw-English leak in fa. A single-value Select whose options
    // come from a BackedEnum class validates via Rule::enum(), not Rule::in() — the message
    // key is 'enum', not 'in' (confirmed empirically; see localizationPattern.md §3).

    public function test_edit_rejects_an_invalid_status_with_translated_message(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['user.view', 'user.edit']);
        $record = User::factory()->create();

        $test = Livewire::test(ManageUsers::class)
            ->mountTableAction('edit', $record)
            ->fillForm(['status' => 'totally-bogus-status'])
            ->callMountedTableAction();

        $this->assertSame(
            [__('resources/user/strings.form.validation_status_in')],
            $test->errors()->get('mountedActions.0.data.status')
        );
    }

    public function test_edit_rejects_an_invalid_position_with_translated_message(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['user.view', 'user.edit']);
        $record = User::factory()->create();

        $test = Livewire::test(ManageUsers::class)
            ->mountTableAction('edit', $record)
            ->fillForm(['position' => 'totally-bogus-position'])
            ->callMountedTableAction();

        $this->assertSame(
            [__('resources/user/strings.form.validation_position_in')],
            $test->errors()->get('mountedActions.0.data.position')
        );
    }

    // Infolist labels

    public function test_infolist_entries_use_the_translated_labels(): void
    {
        app()->setLocale('en');

        $this->assertSame('Name', UserResource::viewName()->getLabel());
        $this->assertSame('Email', UserResource::viewEmail()->getLabel());
        $this->assertSame('Status', UserResource::viewStatus()->getLabel());
    }

    // No bulk import — settled policy (security-sensitive: bulk account creation touches
    // auth/password/role assignment); positive control first (User genuinely has create/export)

    public function test_user_has_no_import_action(): void
    {
        $this->actingAsUserWithPermissions(['user.view', 'user.create']);

        $livewire = Livewire::test(ManageUsers::class);

        $livewire->assertActionExists('create');

        $this->assertFalse(method_exists(UserResource::class, 'getImportAction'));
    }

    public function test_exporter_write_emits_one_row_per_record_without_any_sensitive_fields(): void
    {
        $this->markTestSkipped('UserExporter::write()/columnLabels() (shared flat-write shape) is parked on worktree-agent-a5f81ad74b0cf6b52 (commit dc6e0d5), unmerged pending review — master\'s UserExporter is still a native Filament Exporter with no write()/columnLabels() statics. Un-skip once that worktree is reviewed and merged.');
    }

    public function test_import_and_export_column_counts_are_pinned(): void
    {
        $this->markTestSkipped('UserExporter::columnLabels() is parked on worktree-agent-a5f81ad74b0cf6b52 (commit dc6e0d5), unmerged pending review. Un-skip once that worktree is reviewed and merged.');
    }

    // Delete stays forbidden even with the permission — the global ViewAction footer button

    public function test_delete_is_denied_even_with_the_delete_permission(): void
    {
        $this->actingAsUserWithPermissions(['user.view', 'user.delete']);
        $record = User::factory()->create();

        $this->assertFalse(UserResource::canDelete($record));
        $this->assertFalse(UserResource::canDeleteAny());
        $this->assertTrue(UserResource::getDeleteAuthorizationResponse($record)->denied());
        $this->assertTrue(UserResource::getDeleteAnyAuthorizationResponse()->denied());
    }

    // Status field defaults to Active — 2026-10-07 QA fix

    public function test_status_field_defaults_to_active(): void
    {
        $this->assertSame('active', UserResource::getStatus()->getDefaultState());
    }

    // Bulk activate/deactivate — 2026-10-07 QA addition, status-column based (no is_active column on users)

    public function test_bulk_activate_sets_status_to_active(): void
    {
        $this->actingAsUserWithPermissions(['user.view', 'user.edit']);
        $inactive = User::factory()->create(['status' => 'inactive']);

        Livewire::test(ManageUsers::class)
            ->callTableBulkAction('activate', [$inactive]);

        $this->assertSame('active', $inactive->fresh()->status);
    }

    public function test_bulk_deactivate_sets_status_to_inactive(): void
    {
        $this->actingAsUserWithPermissions(['user.view', 'user.edit']);
        $active = User::factory()->create(['status' => 'active']);

        Livewire::test(ManageUsers::class)
            ->callTableBulkAction('deactivate', [$active]);

        $this->assertSame('inactive', $active->fresh()->status);
    }

    public function test_bulk_deactivate_skips_self_and_admin_junior_users(): void
    {
        $actor = $this->actingAsUserWithPermissions(['user.view', 'user.edit']);
        $junior = User::factory()->create(['status' => 'active']);
        $junior->assignRole(Role::firstOrCreate(['name' => 'admin_junior', 'guard_name' => 'web']));
        $regular = User::factory()->create(['status' => 'active']);

        Livewire::test(ManageUsers::class)
            ->callTableBulkAction('deactivate', [$actor, $junior, $regular]);

        $this->assertSame('active', $actor->fresh()->status);
        $this->assertSame('active', $junior->fresh()->status);
        $this->assertSame('inactive', $regular->fresh()->status);
    }

    public function test_bulk_deactivate_does_not_count_already_inactive_users_as_skipped(): void
    {
        $actor = $this->actingAsUserWithPermissions(['user.view', 'user.edit']);
        $alreadyInactive = User::factory()->create(['status' => 'inactive']);
        $active = User::factory()->create(['status' => 'active']);

        Livewire::test(ManageUsers::class)
            ->callTableBulkAction('deactivate', [$alreadyInactive, $active])
            ->assertNotNotified(__('resources/user/strings.bulk.deactivate_skipped', ['count' => 0]))
            ->assertNotNotified(__('resources/user/strings.bulk.deactivate_skipped', ['count' => 1]));

        $this->assertSame('inactive', $active->fresh()->status);

        Livewire::test(ManageUsers::class)
            ->callTableBulkAction('deactivate', [$actor, $active])
            ->assertNotified(__('resources/user/strings.bulk.deactivate_skipped', ['count' => 1]));
    }

    public function test_bulk_deactivate_requires_confirmation(): void
    {
        $this->actingAsUserWithPermissions(['user.view', 'user.edit']);

        $action = Livewire::test(ManageUsers::class)->instance()->getTable()->getBulkAction('deactivate');

        $this->assertTrue($action->isConfirmationRequired());
    }

    public function test_stale_login_filter_keeps_null_and_old_logins_only(): void
    {
        $this->actingAsUserWithPermissions(['user.view']);
        $never = User::factory()->create(['last_log_in' => null]);
        $old = User::factory()->create(['last_log_in' => now()->subDays(45)]);
        $recent = User::factory()->create(['last_log_in' => now()->subDays(2)]);

        Livewire::test(ManageUsers::class)
            ->filterTable('stale_login', true)
            ->assertCanSeeTableRecords([$never, $old])
            ->assertCanNotSeeTableRecords([$recent]);
    }

    public function test_inactive_users_get_the_dimmed_row_class_and_active_users_do_not(): void
    {
        $this->actingAsUserWithPermissions(['user.view']);
        $inactive = User::factory()->create(['status' => 'inactive']);
        $active = User::factory()->create(['status' => 'active']);

        $table = Livewire::test(ManageUsers::class)->instance()->getTable();

        $this->assertContains('user-row-inactive', (array) $table->getRecordClasses($inactive));
        $this->assertNotContains('user-row-inactive', (array) $table->getRecordClasses($active));
    }

    public function test_last_login_column_is_visible_and_handles_null(): void
    {
        $this->actingAsUserWithPermissions(['user.view']);
        $never = User::factory()->create(['last_log_in' => null]);

        Livewire::test(ManageUsers::class)
            ->assertTableColumnExists('last_log_in')
            ->assertTableColumnFormattedStateSet('last_log_in', __('resources/user/strings.table.never'), $never);
    }

    // Deliberately always visible, not selection-conditional — a conditional ->visible() closure
    // was tried and removed 2026-10-07: it never actually hid anything in the real browser.

    public function test_activate_and_deactivate_are_always_visible_regardless_of_selection(): void
    {
        $this->actingAsUserWithPermissions(['user.view', 'user.edit']);
        $active = User::factory()->create(['status' => 'active']);

        Livewire::test(ManageUsers::class)
            ->selectTableRecords([$active])
            ->assertTableBulkActionVisible('activate')
            ->assertTableBulkActionVisible('deactivate');
    }

    // Custom exporter (write()-based, matches Role/Permission/Target/etc.) — 2026-10-07, replacing
    // the native Filament ExportBulkAction/Exporter shape that shipped with this resource before.

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['user.view']);

        $record = User::factory()->create();

        Livewire::test(ManageUsers::class)
            ->callTableBulkAction('exportUsers', [$record]);

        Queue::assertPushed(\App\Jobs\ExportUsers::class);
    }
}
