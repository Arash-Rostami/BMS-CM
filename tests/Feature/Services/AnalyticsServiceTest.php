<?php

namespace Tests\Feature\Services;

use App\Services\AnalyticsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    private const KEYS = ['concentration', 'cycle_time', 'exposure_aging', 'open_exposure', 'pipeline_stalls', 'shipment_punctuality'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        foreach (self::KEYS as $key) {
            Cache::forget("analytics:{$key}");
        }
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        foreach (self::KEYS as $key) {
            Cache::forget("analytics:{$key}");
        }
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

    public function test_each_method_reads_through_its_exact_cache_key(): void
    {
        $stub = ['stub' => true];
        Cache::put('analytics:concentration', $stub, now()->addMinutes(5));

        $this->assertSame($stub, AnalyticsService::concentrationRisk());
    }

    public function test_a_cache_miss_writes_the_exact_key(): void
    {
        AnalyticsService::exposureAging();

        $this->assertTrue(Cache::has('analytics:exposure_aging'));
    }

    public function test_concentration_risk_returns_hhi_values_within_bounds(): void
    {
        $result = AnalyticsService::concentrationRisk();

        $this->assertSame(['supplier_hhi', 'currency_hhi'], array_keys($result));
        foreach ($result as $hhi) {
            $this->assertIsFloat($hhi);
            $this->assertGreaterThanOrEqual(0, $hhi);
            $this->assertLessThanOrEqual(10000, $hhi);
        }
    }

    public function test_cycle_time_by_stage_covers_all_four_stages(): void
    {
        $result = AnalyticsService::cycleTimeByStage();

        $this->assertSame(['request_to_order', 'order_to_payment', 'payment_to_shipment', 'shipment_to_clearance'], array_keys($result));
        foreach ($result as $stage) {
            $this->assertIsInt($stage['count']);
            $this->assertGreaterThanOrEqual(0, $stage['count']);
            $this->assertArrayHasKey('p50', $stage);
            $this->assertArrayHasKey('p90', $stage);
        }
    }

    public function test_shipment_punctuality_exposes_the_delay_buckets(): void
    {
        $result = AnalyticsService::shipmentPunctuality();

        $this->assertSame(
            ['on_time', 'late_1_3', 'late_4_7', 'late_8_plus', 'currently_overdue'],
            array_keys($result)
        );
    }

    public function test_exposure_and_stall_methods_return_arrays(): void
    {
        $this->assertIsArray(AnalyticsService::openCurrencyExposure());
        $this->assertIsArray(AnalyticsService::pipelineStalls());
    }
}