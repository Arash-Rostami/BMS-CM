<?php

namespace Tests\Feature\Notifications;

use App\Models\NotificationSetting;
use App\Models\Permission;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ModelEventEmail;
use App\Notifications\ModelEventNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

abstract class NotificationTestCase extends TestCase
{
    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        config(['app.locale' => 'en']);
        app()->setLocale('en');
        $this->actor = User::factory()->create();
        $this->actingAs($this->actor);
        Notification::fake();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    protected function recipient(array $attributes = [], array $permissions = ['purchase_request.view']): User
    {
        $user = User::factory()->create($attributes);
        $role = Role::create(['name' => 'nt_role_'.uniqid(), 'guard_name' => 'web']);

        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']));
        }

        $user->assignRole($role);

        return $user;
    }

    protected function rule(User|array $recipients, array $overrides = [], string $type = 'in_app'): NotificationSetting
    {
        $ids = collect($recipients instanceof User ? [$recipients] : $recipients)->pluck('id')->all();

        return NotificationSetting::create([
            'settings' => array_merge([
                'is_active' => true,
                'actions' => ['update'],
                'tables' => ['purchase_requests'],
                'users' => $ids,
            ], $overrides),
            'notification_type' => $type,
            'user_id' => $this->actor->id,
        ]);
    }

    protected function request(array $attributes = []): PurchaseRequest
    {
        return PurchaseRequest::factory()->create($attributes);
    }

    protected function sentCount(User $user, string $class = ModelEventNotification::class): int
    {
        return Notification::sent($user, $class)->count();
    }

    protected function emailCount(User $user): int
    {
        return $this->sentCount($user, ModelEventEmail::class);
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
}
