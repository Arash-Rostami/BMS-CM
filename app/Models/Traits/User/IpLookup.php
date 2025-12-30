<?php

namespace App\Models\Traits\User;

use Throwable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

trait IpLookup
{
    protected static function getCountryFromIp(string $ip): string
    {
        if (empty($ip)) return 'Unidentified IP';

        return Cache::remember("country_{$ip}", now()->addMinutes(10), function () use ($ip) {
            try {
                $response = Http::timeout(5)
                    ->retry(2, 100)
                    ->get("http://ip-api.com/json/{$ip}");

                if ($response->successful() && $response->json('status') === 'success') {
                    return $response->json('country') ?? '🌎';
                }

                return 'Unidentified IP';

            } catch (Throwable $e) {
                return 'Unidentified IP';
            }
        });
    }

    public function getUserCountryAttribute(): string
    {
        return static::getCountryFromIp($this->ip ?? '');
    }
}
