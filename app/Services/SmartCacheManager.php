<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SmartCacheManager
{
    private const CACHE_PREFIX = 'smart_';

    public static function invalidate(string $model): void
    {
        $registryKey = self::getRegistryKey($model);
        $registry = Cache::get($registryKey, []);

        foreach ($registry as $cacheKey) {
            Cache::forget($cacheKey);
        }

        Cache::forget($registryKey);
        self::clearNavigationCache($model);
    }

    public static function remember(string $model, array $filters, int $minutes, callable $callback)
    {
        $registryKey = self::getRegistryKey($model);
        $key = self::generateKey($model, $filters);
        self::registerCacheKey($registryKey, $key);

        return Cache::remember($key, now()->addMinutes($minutes), $callback);
    }

    private static function clearNavigationCache($model)
    {
        $baseKey = strtolower($model);

        Cache::forget('total_count_'.$baseKey);
    }

    private static function generateKey(string $model, array $filters): string
    {
        $hash = md5(json_encode($filters));

        return self::CACHE_PREFIX.strtolower($model).'_'.substr($hash, 0, 16);
    }

    private static function getRegistryKey(string $model): string
    {
        return self::CACHE_PREFIX.strtolower($model).'_registry';
    }

    private static function registerCacheKey(string $registryKey, string $key): void
    {
        $registry = Cache::get($registryKey, []);

        if (! in_array($key, $registry)) {
            $registry[] = $key;
            Cache::forever($registryKey, $registry);
        }
    }
}
