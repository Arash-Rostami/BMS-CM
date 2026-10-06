<?php

namespace Tests\Feature\Models;

use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        Cache::forget('country_8.8.8.8');
        Cache::forget('country_');
    }

    protected function tearDown(): void
    {
        Cache::forget('country_8.8.8.8');
        Cache::forget('country_');
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

    // DashboardAccess

    public function test_can_access_panel_is_granted_only_for_allowed_email_domains(): void
    {
        $panel = \Filament\Facades\Filament::getPanel('dashboard');

        $main = User::factory()->create(['email' => 'someone@persolco.com']);
        $second = User::factory()->create(['email' => 'someone@anothercompany.org']);
        $outside = User::factory()->create(['email' => 'someone@gmail.com']);

        $this->assertTrue($main->canAccessPanel($panel));
        $this->assertTrue($second->canAccessPanel($panel));
        $this->assertFalse($outside->canAccessPanel($panel));
    }

    // isAdmin

    public function test_is_admin_is_true_only_for_the_three_admin_roles(): void
    {
        $junior = Role::firstOrCreate(['name' => 'admin_junior', 'guard_name' => 'web']);
        $mid = Role::firstOrCreate(['name' => 'admin_mid', 'guard_name' => 'web']);
        $senior = Role::firstOrCreate(['name' => 'admin_senior', 'guard_name' => 'web']);
        $agent = Role::firstOrCreate(['name' => 'agent_junior', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        $plain = User::factory()->create();
        $admin->assignRole($junior);
        $plain->assignRole($agent);

        $this->assertTrue($admin->isAdmin());
        $this->assertFalse($plain->isAdmin());

        $allThree = User::factory()->create();
        $allThree->syncRoles([$junior, $mid, $senior]);
        $this->assertTrue($allThree->isAdmin());
    }

    // Setting trait + casts

    public function test_get_setting_reads_the_json_settings_with_a_default_fallback(): void
    {
        $user = User::factory()->create(['settings' => ['theme' => 'dark']]);

        $this->assertSame('dark', $user->getSetting('theme'));
        $this->assertSame('fallback', $user->getSetting('missing', 'fallback'));
        $this->assertNull(User::factory()->create()->getSetting('theme'));
    }

    public function test_password_is_hashed_and_hidden_from_array_output(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertArrayNotHasKey('deleted_at', $user->toArray());
    }

    // forceDeleting guard

    public function test_force_deleting_a_user_with_pipeline_records_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        PurchaseOrder::factory()->create();

        $this->expectException(\RuntimeException::class);
        $user->forceDelete();
    }

    public function test_force_deleting_an_unlinked_user_removes_the_row(): void
    {
        $user = User::factory()->create();

        $user->forceDelete();

        $this->assertNull(User::withTrashed()->find($user->id));
    }

    // IpLookup

    public function test_user_country_is_unidentified_for_an_empty_ip(): void
    {
        $user = User::factory()->create(['ip' => null]);

        $this->assertSame('Unidentified IP', $user->user_country);
    }

    public function test_user_country_reads_the_cached_lookup_without_hitting_the_api(): void
    {
        Cache::put('country_8.8.8.8', 'United States', now()->addMinutes(10));
        $user = User::factory()->create(['ip' => '8.8.8.8']);

        $this->assertSame('United States', $user->user_country);
    }

    // UserImage

    public function test_filament_avatar_url_uses_the_role_base_name_with_fallbacks(): void
    {
        $uniq = uniqid();
        $role = Role::firstOrCreate(['name' => "manager_{$uniq}_junior", 'guard_name' => 'web']);

        $withRole = User::factory()->create();
        $withRole->assignRole($role);
        $this->assertStringContainsString('manager-', $withRole->getFilamentAvatarUrl());

        $fromColumn = User::factory()->create(['role' => 'accountant_mid']);
        $this->assertStringContainsString('accountant-', $fromColumn->getFilamentAvatarUrl());

        $this->assertStringContainsString('agent-', User::factory()->create()->getFilamentAvatarUrl());
    }

    // Constant contract

    public function test_cache_minutes_pins_the_cache_ttl_contract(): void
    {
        $this->assertSame(60, User::CACHE_MINUTES);
    }

    // Login/Logout listeners

    public function test_the_login_event_stamps_last_log_in(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertNotNull($user->fresh()->last_log_in, 'Dispatching the Login event must stamp last_log_in on the user.');
    }

    public function test_the_logout_event_stamps_last_log_out(): void
    {
        $user = User::factory()->create();

        event(new Logout('web', $user));

        $this->assertNotNull($user->fresh()->last_log_out, 'Dispatching the Logout event must stamp last_log_out on the user.');
    }
}