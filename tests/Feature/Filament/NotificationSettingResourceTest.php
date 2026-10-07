<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Master\NotificationSettingResource\Exports\NotificationSettingExporter;
use App\Filament\Resources\Master\NotificationSettingResource\Pages\ManageNotificationSettings;
use App\Filament\Resources\NotificationSettingResource;
use App\Jobs\ExportNotificationSettings;
use App\Models\NotificationSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationSettingResourceTest extends TestCase
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

    private function baseSettings(array $overrides = []): array
    {
        return array_merge([
            'tables' => ['bank_profiles'],
            'actions' => ['create'],
            'users' => [],
            'is_active' => true,
        ], $overrides);
    }

    // Permissions + ownership (issue #4)

    public function test_full_permissions_allow_every_gated_action_on_an_owned_record(): void
    {
        $actor = $this->actingAsUserWithPermissions([
            'notification_setting.view',
            'notification_setting.create',
            'notification_setting.edit',
            'notification_setting.delete',
            'notification_setting.restore',
        ]);

        $record = NotificationSetting::factory()->create(['user_id' => $actor->id]);

        $this->assertTrue(NotificationSettingResource::canViewAny());
        $this->assertTrue(NotificationSettingResource::canCreate());
        $this->assertTrue(NotificationSettingResource::canEdit($record));
        $this->assertTrue(NotificationSettingResource::canDelete($record));
        $this->assertTrue(NotificationSettingResource::canRestore($record));
    }

    // This module is deliberately open to every authenticated user, no Spatie permission
    // required — only Delete is restricted, by ownership/recipient, not by a permission grant.

    public function test_view_create_edit_are_open_to_any_authenticated_user_with_no_permissions(): void
    {
        $otherOwner = User::factory()->create();
        $this->actingAs($otherOwner);
        $someoneElsesRecord = NotificationSetting::factory()->create(['settings' => ['tables' => ['users'], 'actions' => ['create'], 'users' => []]]);

        $actor = $this->actingAsUserWithPermissions([]);

        $this->assertTrue(NotificationSettingResource::canViewAny());
        $this->assertTrue(NotificationSettingResource::canCreate());
        $this->assertTrue(NotificationSettingResource::canEdit($someoneElsesRecord));
        $this->assertFalse(NotificationSettingResource::canDelete($someoneElsesRecord));
    }

    public function test_delete_is_denied_for_a_record_owned_by_someone_else_even_with_the_delete_permission(): void
    {
        $otherOwner = User::factory()->create();
        $this->actingAs($otherOwner);
        $record = NotificationSetting::factory()->create(['settings' => $this->baseSettings()]);

        $this->actingAsUserWithPermissions(['notification_setting.view', 'notification_setting.delete']);

        $this->assertFalse(NotificationSettingResource::canDelete($record));
    }

    public function test_delete_is_allowed_for_the_record_owner(): void
    {
        $actor = $this->actingAsUserWithPermissions(['notification_setting.view', 'notification_setting.delete']);
        $record = NotificationSetting::factory()->create(['user_id' => $actor->id]);

        $this->assertTrue(NotificationSettingResource::canDelete($record));

        Livewire::test(ManageNotificationSettings::class)
            ->callTableAction('delete', $record);

        $this->assertNull(NotificationSetting::find($record->id));
    }

    public function test_delete_is_allowed_for_a_designated_recipient_who_is_not_the_creator(): void
    {
        $recipient = $this->actingAsUserWithPermissions(['notification_setting.view', 'notification_setting.delete']);

        $creator = User::factory()->create();
        $this->actingAs($creator);
        $record = NotificationSetting::factory()->create(['settings' => $this->baseSettings(['users' => [$recipient->id]])]);

        $this->actingAs($recipient);

        $this->assertTrue(NotificationSettingResource::canDelete($record));
    }

    public function test_bulk_delete_only_removes_records_the_user_owns_or_is_a_recipient_of(): void
    {
        $actor = $this->actingAsUserWithPermissions(['notification_setting.view', 'notification_setting.delete']);
        $owned = NotificationSetting::factory()->create(['user_id' => $actor->id]);

        $otherOwner = User::factory()->create();
        $this->actingAs($otherOwner);
        $notOwned = NotificationSetting::factory()->create(['settings' => $this->baseSettings()]);

        $this->actingAs($actor);

        Livewire::test(ManageNotificationSettings::class)
            ->callTableBulkAction('delete', [$owned, $notOwned]);

        $this->assertNull(NotificationSetting::find($owned->id));
        $this->assertNotNull(NotificationSetting::find($notOwned->id));
    }

    // List — search

    public function test_manage_page_renders_and_search_finds_by_table_name(): void
    {
        $this->actingAsUserWithPermissions(['notification_setting.view']);

        $target = NotificationSetting::factory()->create(['settings' => ['tables' => ['search_target_table']]]);
        $other = NotificationSetting::factory()->create(['settings' => ['tables' => ['search_other_table']]]);

        Livewire::test(ManageNotificationSettings::class)
            ->assertCanSeeTableRecords([$target, $other])
            ->searchTable('search_target_table')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    // Sorting (issue #2 — recipient.*.name is a computed accessor, not a real column)

    public function test_sorting_the_table_no_longer_crashes_on_the_recipients_column(): void
    {
        $this->actingAsUserWithPermissions(['notification_setting.view']);
        NotificationSetting::factory()->count(2)->create();

        Livewire::test(ManageNotificationSettings::class)
            ->sortTable('recipient.*.name')
            ->assertOk();
    }

    public function test_recipients_column_is_not_sortable(): void
    {
        $this->assertFalse(NotificationSettingResource::showRecipients()->isSortable());
    }

    public function test_sorting_by_tables_still_works(): void
    {
        $this->actingAsUserWithPermissions(['notification_setting.view']);
        NotificationSetting::factory()->count(2)->create();

        Livewire::test(ManageNotificationSettings::class)
            ->sortTable('settings.tables')
            ->assertOk();
    }

    // Filters

    public function test_notification_channel_filter_narrows_the_table(): void
    {
        $this->actingAsUserWithPermissions(['notification_setting.view']);

        $email = NotificationSetting::factory()->create(['notification_type' => 'email']);
        $inApp = NotificationSetting::factory()->create(['notification_type' => 'in_app']);

        Livewire::test(ManageNotificationSettings::class)
            ->filterTable('notification_type', 'email')
            ->assertCanSeeTableRecords([$email])
            ->assertCanNotSeeTableRecords([$inApp]);
    }

    public function test_creator_filter_narrows_the_table(): void
    {
        $userA = $this->actingAsUserWithPermissions(['notification_setting.view']);
        $withA = NotificationSetting::factory()->create();

        $userB = User::factory()->create();
        $this->actingAs($userB);
        $withB = NotificationSetting::factory()->create();

        $this->actingAs($userA);

        Livewire::test(ManageNotificationSettings::class)
            ->filterTable('user_id', $userA->id)
            ->assertCanSeeTableRecords([$withA])
            ->assertCanNotSeeTableRecords([$withB]);
    }

    public function test_mine_filter_shows_only_rules_created_by_or_addressed_to_the_user(): void
    {
        $actor = $this->actingAsUserWithPermissions([]);
        $created = NotificationSetting::factory()->create(['user_id' => $actor->id, 'settings' => $this->baseSettings()]);

        $other = User::factory()->create();
        $this->actingAs($other);
        $addressed = NotificationSetting::factory()->create(['settings' => $this->baseSettings(['users' => [$actor->id]])]);
        $unrelated = NotificationSetting::factory()->create(['settings' => $this->baseSettings(['users' => [$other->id]])]);

        $this->actingAs($actor);

        Livewire::test(ManageNotificationSettings::class)
            ->assertCanSeeTableRecords([$created, $addressed, $unrelated])
            ->filterTable('mine', true)
            ->assertCanSeeTableRecords([$created, $addressed])
            ->assertCanNotSeeTableRecords([$unrelated]);
    }

    public function test_mine_filter_matches_string_stored_recipients(): void
    {
        $actor = $this->actingAsUserWithPermissions([]);
        $this->actingAs(User::factory()->create());
        $stringStored = NotificationSetting::factory()->create(['settings' => $this->baseSettings(['users' => [(string) $actor->id]])]);
        $unrelated = NotificationSetting::factory()->create(['settings' => $this->baseSettings(['users' => ['0']])]);

        $this->actingAs($actor);

        Livewire::test(ManageNotificationSettings::class)
            ->filterTable('mine', true)
            ->assertCanSeeTableRecords([$stringStored])
            ->assertCanNotSeeTableRecords([$unrelated]);
    }

    public function test_recipients_column_resolves_names_with_a_single_users_query(): void
    {
        $actor = $this->actingAsUserWithPermissions([]);
        $users = User::factory()->count(3)->create();

        foreach ($users as $user) {
            NotificationSetting::factory()->create(['settings' => $this->baseSettings(['users' => [$user->id, $actor->id]])]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        Livewire::test(ManageNotificationSettings::class)->assertSee($users[0]->name);

        $nameMapQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'select `name`, `id` from `users`'))
            ->count();

        $this->assertSame(1, $nameMapQueries);
    }

    public function test_user_selector_is_required_and_defaults_to_the_current_user(): void
    {
        $actor = $this->actingAsUserWithPermissions([]);

        $field = NotificationSettingResource::getUserSelector();

        $this->assertTrue($field->isRequired());
        $this->assertSame([$actor->id], $field->getDefaultState());
    }

    // Create — happy path + validation (issue #3)

    public function test_create_happy_path_saves_a_new_notification_setting(): void
    {
        $this->actingAsUserWithPermissions(['notification_setting.view', 'notification_setting.create']);

        Livewire::test(ManageNotificationSettings::class)
            ->callAction('create', data: [
                'notification_type' => 'email',
                'settings' => [
                    'tables' => ['purchase_requests'],
                    'actions' => ['create'],
                    'users' => [User::factory()->create()->id],
                    'is_active' => true,
                ],
                'notes' => 'Created via test',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('notification_settings', [
            'notification_type' => 'email',
            'notes' => 'Created via test',
        ]);
    }

    public function test_create_rejects_a_completely_blank_submission(): void
    {
        $this->actingAsUserWithPermissions([]);
        $before = NotificationSetting::count();

        Livewire::test(ManageNotificationSettings::class)
            ->callAction('create', data: [
                'notification_type' => '',
                'settings' => [
                    'tables' => [],
                    'actions' => [],
                    'users' => [],
                ],
            ])
            ->assertHasActionErrors([
                'notification_type' => 'required',
                'settings.tables' => 'required',
                'settings.actions' => 'required',
                'settings.users' => 'required',
            ]);

        $this->assertSame($before, NotificationSetting::count());
    }

    public function test_create_rejects_a_notes_field_over_the_max_length(): void
    {
        $this->actingAsUserWithPermissions(['notification_setting.view', 'notification_setting.create']);

        Livewire::test(ManageNotificationSettings::class)
            ->callAction('create', data: [
                'notification_type' => 'email',
                'settings' => [
                    'tables' => ['purchase_requests'],
                    'actions' => ['create'],
                ],
                'notes' => str_repeat('a', 501),
            ])
            ->assertHasActionErrors(['notes' => 'max']);
    }

    // Validation messages — no raw-English leak in fa (Select ['in']/['*.in'], not ['exists'])

    public function test_create_action_rejects_an_invalid_notification_channel_with_translated_message(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['notification_setting.view', 'notification_setting.create']);

        $test = Livewire::test(ManageNotificationSettings::class)
            ->mountAction('create')
            ->fillForm(['notification_type' => 'totally-bogus-channel'])
            ->callMountedAction();

        $this->assertSame(
            [__('resources/notificationSetting/strings.form.validation_in')],
            $test->errors()->get('mountedActions.0.data.notification_type')
        );
    }

    public function test_create_action_rejects_invalid_actions_with_translated_message(): void
    {
        app()->setLocale('fa');
        $this->actingAsUserWithPermissions(['notification_setting.view', 'notification_setting.create']);

        $test = Livewire::test(ManageNotificationSettings::class)
            ->mountAction('create')
            ->fillForm(['settings.actions' => ['totally-bogus-action']])
            ->callMountedAction();

        $this->assertSame(
            [__('resources/notificationSetting/strings.form.validation_in')],
            $test->errors()->get('mountedActions.0.data.settings.actions.0')
        );
    }

    // Edit

    public function test_edit_action_updates_the_notes(): void
    {
        $actor = $this->actingAsUserWithPermissions(['notification_setting.view', 'notification_setting.edit']);
        $record = NotificationSetting::factory()->create([
            'notes' => 'Before',
            'settings' => $this->baseSettings(['users' => [$actor->id]]),
        ]);

        Livewire::test(ManageNotificationSettings::class)
            ->mountTableAction('edit', $record)
            ->fillForm(['notes' => 'After'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $this->assertSame('After', $record->fresh()->notes);
    }

    // Localization (issue #1)

    public function test_localized_tables_helper_resolves_a_module_label_instead_of_the_raw_table_name(): void
    {
        app()->setLocale('en');

        $record = NotificationSetting::factory()->create(['settings' => $this->baseSettings(['tables' => ['bank_profiles']])]);

        $this->assertSame(['Bank Profile'], $record->getLocalizedTables());
        $this->assertNotContains('bank_profiles', $record->getLocalizedTables());
    }

    public function test_list_table_renders_the_localized_label_not_the_raw_table_name(): void
    {
        app()->setLocale('en');
        $this->actingAsUserWithPermissions(['notification_setting.view']);

        NotificationSetting::factory()->create(['settings' => $this->baseSettings(['tables' => ['bank_profiles']])]);

        Livewire::test(ManageNotificationSettings::class)
            ->assertSee('Bank Profile');
    }

    public function test_infolist_entries_use_the_translated_labels(): void
    {
        app()->setLocale('en');

        $this->assertNotEmpty(NotificationSettingResource::viewNotes()->getLabel());
        $this->assertNotEmpty(NotificationSettingResource::viewNotificationChannel()->getLabel());
    }

    // Export (issue #5)

    public function test_export_bulk_action_dispatches_the_queued_export_job(): void
    {
        Queue::fake();
        $this->actingAsUserWithPermissions(['notification_setting.view']);

        $record = NotificationSetting::factory()->create();

        Livewire::test(ManageNotificationSettings::class)
            ->callTableBulkAction('exportNotificationSettings', [$record]);

        Queue::assertPushed(ExportNotificationSettings::class);
    }

    private function exportToRows(Builder $query): array
    {
        $path = tempnam(sys_get_temp_dir(), 'ns_export_').'.csv';
        NotificationSettingExporter::write($query, $path);

        $csv = (string) file_get_contents($path);
        unlink($path);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $lines = array_values(array_filter(explode("\n", str_replace("\r\n", "\n", trim(ltrim($csv, "\xEF\xBB\xBF"))))));
        $header = str_getcsv($lines[0]);
        $rows = array_map(fn (string $line) => array_combine($header, str_getcsv($line)), array_slice($lines, 1));

        return ['header' => $header, 'rows' => $rows];
    }

    public function test_exporter_write_emits_one_row_per_record_with_localized_table_labels(): void
    {
        app()->setLocale('en');
        $creator = User::factory()->create(['name' => 'Export Creator']);
        $this->actingAs($creator);

        $record = NotificationSetting::factory()->create([
            'notification_type' => 'email',
            'settings' => $this->baseSettings(['tables' => ['bank_profiles'], 'actions' => ['create']]),
        ]);

        ['header' => $header, 'rows' => $rows] = $this->exportToRows(NotificationSetting::whereKey($record->id));

        $labels = NotificationSettingExporter::columnLabels();

        $this->assertCount(12, $header);
        $this->assertSame('Bank Profile', $rows[0][$labels['tables']]);
        $this->assertStringContainsString('Create', $rows[0][$labels['actions']]);
        $this->assertSame('Export Creator', $rows[0][$labels['creator']]);
        $this->assertSame(__('resources/notificationSetting/strings.export.active'), $rows[0][$labels['is_active']]);
    }

    // Soft delete / restore lifecycle

    public function test_delete_then_restore_lifecycle_via_table_actions(): void
    {
        $actor = $this->actingAsUserWithPermissions(['notification_setting.view', 'notification_setting.delete', 'notification_setting.restore']);
        $record = NotificationSetting::factory()->create(['user_id' => $actor->id]);

        Livewire::test(ManageNotificationSettings::class)
            ->callTableAction('delete', $record);

        $this->assertNull(NotificationSetting::find($record->id));
        $this->assertTrue(NotificationSetting::withTrashed()->find($record->id)->trashed());

        Livewire::test(ManageNotificationSettings::class)
            ->filterTable('trashed')
            ->callTableAction('restore', $record);

        $this->assertNotNull(NotificationSetting::find($record->id));
    }
}
