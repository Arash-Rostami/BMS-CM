<?php

namespace Tests\Feature\Services;

use App\Services\SmartCacheManager;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SmartCacheManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SmartCacheManager::invalidate('CacheProbe');
        Cache::forget('total_count_cacheprobe');
    }

    protected function tearDown(): void
    {
        SmartCacheManager::invalidate('CacheProbe');
        Cache::forget('total_count_cacheprobe');
        parent::tearDown();
    }

    public function test_remember_returns_the_callback_value(): void
    {
        $value = SmartCacheManager::remember('CacheProbe', ['type' => 'total_count'], 10, fn () => 'computed');

        $this->assertSame('computed', $value);
    }

    public function test_remember_does_not_re_run_the_callback_while_the_entry_is_cached(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        SmartCacheManager::remember('CacheProbe', ['type' => 'total_count'], 10, $callback);
        SmartCacheManager::remember('CacheProbe', ['type' => 'total_count'], 10, $callback);

        $this->assertSame(1, $calls);
    }

    public function test_remember_keeps_entries_with_different_filters_independent(): void
    {
        $value = 'first';

        $this->assertSame('first', SmartCacheManager::remember('CacheProbe', ['type' => 'a'], 10, fn () => $value));

        $value = 'second';
        $this->assertSame('first', SmartCacheManager::remember('CacheProbe', ['type' => 'a'], 10, fn () => $value));
        $this->assertSame('second', SmartCacheManager::remember('CacheProbe', ['type' => 'b'], 10, fn () => $value));
    }

    public function test_invalidate_forgets_the_cached_entries_so_the_callback_runs_again(): void
    {
        $calls = 0;
        $callback = function () use (&$calls) {
            $calls++;

            return 'computed';
        };

        SmartCacheManager::remember('CacheProbe', ['type' => 'total_count'], 10, $callback);
        SmartCacheManager::invalidate('CacheProbe');
        SmartCacheManager::remember('CacheProbe', ['type' => 'total_count'], 10, $callback);

        $this->assertSame(2, $calls);
    }

    public function test_invalidate_clears_the_registry_itself(): void
    {
        SmartCacheManager::remember('CacheProbe', ['type' => 'total_count'], 10, fn () => 'computed');

        SmartCacheManager::invalidate('CacheProbe');

        $this->assertNull(Cache::get('smart_cacheprobe_registry'));
    }

    public function test_invalidate_also_forgets_the_legacy_navigation_key(): void
    {
        Cache::put('total_count_cacheprobe', 9, now()->addMinutes(5));

        SmartCacheManager::invalidate('CacheProbe');

        $this->assertNull(Cache::get('total_count_cacheprobe'));
    }
}