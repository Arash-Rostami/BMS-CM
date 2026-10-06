<?php

namespace Tests\Feature\Services;

use App\Models\Payment;
use App\Models\User;
use App\Services\DashboardStats;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        Cache::forget('dashboard_counts:guest');
        DB::beginTransaction();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        Cache::forget("dashboard_counts:{$this->user->id}");
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        Cache::forget("dashboard_counts:{$this->user->id}");
        Cache::forget('dashboard_counts:guest');
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

    public function test_get_returns_a_count_for_every_operational_model(): void
    {
        $counts = DashboardStats::get();

        $this->assertSame(
            ['payments', 'purchase_requests', 'proforma_invoices', 'bank_profiles', 'purchase_orders', 'registered_orders', 'shipments', 'customs'],
            array_keys($counts)
        );
        foreach ($counts as $count) {
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
        }
    }

    public function test_fresh_call_reflects_a_new_record_with_a_baseline_delta(): void
    {
        $baseline = DashboardStats::get(true)['payments'];

        Payment::factory()->create();

        $this->assertSame($baseline + 1, DashboardStats::get(true)['payments']);
    }

    public function test_get_reads_the_cached_value_within_the_ttl(): void
    {
        $first = DashboardStats::get();

        Payment::factory()->create();

        $this->assertSame($first, DashboardStats::get());
        $this->assertSame($first['payments'] + 1, DashboardStats::get(true)['payments']);
    }

    public function test_fresh_call_does_not_write_the_cache(): void
    {
        DashboardStats::get(true);

        $this->assertFalse(Cache::has("dashboard_counts:{$this->user->id}"));
    }

    public function test_unauthenticated_call_caches_under_the_guest_key(): void
    {
        auth()->logout();

        $counts = DashboardStats::get();

        $this->assertTrue(Cache::has('dashboard_counts:guest'));
        $this->assertSame($counts, DashboardStats::get());
    }
}