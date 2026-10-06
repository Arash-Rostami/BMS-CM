<?php

namespace Tests\Feature\Middleware;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    private const ANALYTICS_KEYS = ['concentration', 'cycle_time', 'exposure_aging', 'open_exposure', 'pipeline_stalls', 'shipment_punctuality'];

    private int $panelUserId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        $this->forgetDashboardCaches();
    }

    protected function tearDown(): void
    {
        $this->forgetDashboardCaches();
        DB::rollBack();
        parent::tearDown();
    }

    private function forgetDashboardCaches(): void
    {
        foreach (self::ANALYTICS_KEYS as $key) {
            Cache::forget("analytics:{$key}");
        }
        if ($this->panelUserId > 0) {
            Cache::forget("dashboard_counts:{$this->panelUserId}");
        }
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

    public function test_an_active_user_reaches_the_panel(): void
    {
        $user = User::factory()->create(['email' => 'active-user@persolco.com']);
        $this->panelUserId = $user->id;

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk('An active user must be able to open the panel dashboard.');
    }

    public function test_an_inactive_user_is_logged_out_and_redirected_to_the_panel_login(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive-user@persolco.com',
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('filament.dashboard.auth.login'), 'An inactive user must be bounced back to the panel login page.');
        $this->assertFalse(auth()->check(), 'The inactive user must have been logged out by the middleware.');
    }

    public function test_an_inactive_users_session_data_is_wiped_by_the_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive-user@persolco.com',
            'status' => 'inactive',
        ]);

        session(['marker' => 'should-not-survive']);

        $response = $this->actingAs($user)->get('/dashboard');

        $this->assertSame(302, $response->getStatusCode(), 'The inactive user must get a redirect, not a rendered page.');
        $this->assertNull(session('marker'), 'Session invalidation on logout must wipe data that existed before the request.');
    }
}