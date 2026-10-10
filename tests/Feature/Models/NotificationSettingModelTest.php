<?php

namespace Tests\Feature\Models;

use App\Models\NotificationSetting;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationSettingModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        app()->setLocale('en');
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

    public function test_creating_without_a_user_id_outside_an_authenticated_context_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);

        NotificationSetting::factory()->create(['user_id' => null]);
    }

    public function test_creating_with_an_explicit_user_id_is_allowed_without_authentication(): void
    {
        $owner = User::factory()->create();

        $setting = NotificationSetting::factory()->create(['user_id' => $owner->id]);

        $this->assertSame($owner->id, $setting->user_id);
    }

    public function test_setting_getters_read_from_the_settings_json(): void
    {
        $settings = [
            'is_active' => true,
            'actions' => ['create', 'update'],
            'columns' => ['name'],
            'tables' => ['purchase_requests'],
            'users' => [7],
            'values' => ['status' => 'Open'],
        ];
        $setting = NotificationSetting::factory()->create(['settings' => $settings, 'user_id' => User::factory()]);

        $this->assertTrue($setting->isActive());
        $this->assertSame(['create', 'update'], $setting->getActions());
        $this->assertSame(['name'], $setting->getColumns());
        $this->assertSame(['purchase_requests'], $setting->getTables());
        $this->assertSame([7], $setting->getUsers());
        $this->assertSame(['status' => 'Open'], $setting->getValues());
        $this->assertSame($settings, $setting->getAllSettings());
    }

    public function test_setting_getters_default_to_empty_arrays_and_inactive(): void
    {
        $setting = NotificationSetting::factory()->make(['settings' => null]);

        $this->assertFalse($setting->isActive());
        $this->assertSame([], $setting->getAllSettings());
        $this->assertSame([], $setting->getActions());
        $this->assertSame([], $setting->getUsers());
    }

    public function test_notification_channel_label_resolves_by_type_and_falls_back_for_unknown_types(): void
    {
        $inApp = NotificationSetting::factory()->make(['notification_type' => NotificationSetting::NOTIFICATION_CHANNEL_IN_APP]);
        $email = NotificationSetting::factory()->make(['notification_type' => NotificationSetting::NOTIFICATION_CHANNEL_EMAIL]);
        $all = NotificationSetting::factory()->make(['notification_type' => NotificationSetting::NOTIFICATION_CHANNEL_ALL]);
        $unknown = NotificationSetting::factory()->make(['notification_type' => 'sms']);

        $this->assertSame(__('resources/notificationSetting/strings.channels.in_app'), $inApp->notification_channel);
        $this->assertSame(__('resources/notificationSetting/strings.channels.email'), $email->notification_channel);
        $this->assertSame(__('resources/notificationSetting/strings.channels.all'), $all->notification_channel);
        $this->assertSame(__('resources/notificationSetting/strings.channels.unknown'), $unknown->notification_channel);
    }

    public function test_should_send_flags_match_the_channel_type(): void
    {
        $inApp = NotificationSetting::factory()->make(['notification_type' => 'in_app']);
        $email = NotificationSetting::factory()->make(['notification_type' => 'email']);
        $all = NotificationSetting::factory()->make(['notification_type' => 'all']);
        $unknown = NotificationSetting::factory()->make(['notification_type' => 'sms']);

        $this->assertTrue($inApp->shouldSendInApp());
        $this->assertFalse($inApp->shouldSendEmail());

        $this->assertTrue($email->shouldSendEmail());
        $this->assertFalse($email->shouldSendInApp());

        $this->assertTrue($all->shouldSendInApp());
        $this->assertTrue($all->shouldSendEmail());

        $this->assertFalse($unknown->shouldSendInApp());
        $this->assertFalse($unknown->shouldSendEmail());
    }

    public function test_recipient_returns_the_users_listed_in_settings(): void
    {
        $recipientA = User::factory()->create();
        $recipientB = User::factory()->create();
        $excluded = User::factory()->create();

        $setting = NotificationSetting::factory()->create([
            'settings' => ['users' => [$recipientA->id, $recipientB->id]],
            'user_id' => User::factory(),
        ]);

        $ids = $setting->recipient->pluck('id');

        $this->assertTrue($ids->contains($recipientA->id));
        $this->assertTrue($ids->contains($recipientB->id));
        $this->assertFalse($ids->contains($excluded->id));
    }

    public function test_model_inspector_localizes_the_fillable_columns_and_ignores_unknown_classes(): void
    {
        $this->assertSame(
            ['Settings', 'Channel', 'Additional Notes', 'Created by', 'Updated by'],
            NotificationSetting::getAvailableColumns(NotificationSetting::class)
        );
        $this->assertSame([], NotificationSetting::getAvailableColumns('App\\Models\\DoesNotExist'));
    }

    public function test_model_inspector_lists_columns_only_for_selectable_tables_the_user_can_view(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['name' => 'model_test_role_'.uniqid(), 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::firstOrCreate(['name' => 'purchase_request.view', 'guard_name' => 'web']));
        $user->assignRole($role);

        $this->assertSame([], NotificationSetting::getColumnsForSelectedTables(['purchase_requests']), 'Guests see nothing.');

        $this->actingAs($user);
        $columns = NotificationSetting::getColumnsForSelectedTables(['purchase_requests', 'notification_settings']);

        $this->assertSame(['Purchase Request'], array_keys($columns), 'The non-selectable table is not listed.');
        $this->assertSame('Urgency Level', $columns['Purchase Request']['urgency_level']);
    }
}
