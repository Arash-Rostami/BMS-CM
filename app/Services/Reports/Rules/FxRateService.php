<?php

namespace App\Services\Reports\Rules;

use App\Filament\Resources\Operational\BankProfileResource\Enums\Status as BankProfileStatus;
use App\Models\BankProfile;
use App\Models\Currency;
use App\Models\Status;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class FxRateService
{
    public const EUR_NAME = 'Euro';

    protected const TTL = 300;

    protected static array $memo = [];

    protected static array $eur = [];

    protected static ?int $version = null;

    public static function flush(): void
    {
        $bumped = (int) Cache::get('reports:fx:version', 0) + 1;

        static::$memo = [];
        static::$eur = [];
        static::$version = $bumped;
        Cache::forever('reports:fx:version', $bumped);
    }

    public function eurCurrencyId(): ?int
    {
        $version = static::version();

        if (! array_key_exists($version, static::$eur)) {
            static::$eur = [$version => Currency::where('is_active', true)
                ->whereRaw('LOWER(english_name) = ?', [strtolower(static::EUR_NAME)])
                ->orderBy('id')
                ->value('id')];
        }

        return static::$eur[$version];
    }

    protected static function version(): int
    {
        return static::$version ??= (int) Cache::get('reports:fx:version', 0);
    }

    public function rate(int $from, int $to): ?array
    {
        if ($from === $to) {
            return ['rate' => 1.0, 'bank_profile_id' => 0, 'date' => null];
        }

        $key = static::version().":{$from}:{$to}";

        if (! array_key_exists($key, static::$memo)) {
            $found = Cache::remember("reports:fx:{$key}", static::TTL, fn () => $this->lookup($from, $to) ?? ['rate' => null]);
            static::$memo[$key] = $found['rate'] === null ? null : $found;
        }

        return static::$memo[$key];
    }

    public function toEur(float|string|null $amount, ?int $currencyId): ?float
    {
        $eur = $this->eurCurrencyId();

        if (! is_numeric($amount) || is_null($currencyId) || is_null($eur)) {
            return null;
        }

        if ($currencyId === $eur) {
            return (float) $amount;
        }

        $rate = $this->rate($currencyId, $eur);

        return $rate ? (float) $amount * $rate['rate'] : null;
    }

    protected function lookup(int $from, int $to): ?array
    {
        $profile = BankProfile::query()
            ->whereIn('status_id', $this->rateStatusIds())
            ->where('requested_amount', '>', 0)
            ->where('purchased_equivalent', '>', 0)
            ->where(fn ($query) => $query
                ->where(fn ($direction) => $direction->where('requested_currency_id', $from)->where('purchased_currency_id', $to))
                ->orWhere(fn ($direction) => $direction->where('requested_currency_id', $to)->where('purchased_currency_id', $from)))
            ->orderByRaw('purchase_date is null')
            ->orderByDesc('purchase_date')
            ->orderByDesc('id')
            ->first(['id', 'requested_currency_id', 'requested_amount', 'purchased_equivalent', 'purchase_date']);

        if (! $profile) {
            return null;
        }

        $rate = (int) $profile->requested_currency_id === $from
            ? $profile->purchased_equivalent / $profile->requested_amount
            : $profile->requested_amount / $profile->purchased_equivalent;

        return [
            'rate' => (float) $rate,
            'bank_profile_id' => $profile->id,
            'date' => $profile->purchase_date ? Carbon::parse($profile->purchase_date) : null,
        ];
    }

    protected function rateStatusIds(): array
    {
        return Status::where('english_type', BankProfile::TYPE_BANK_PROFILE)
            ->whereIn('english_name', [BankProfileStatus::Purchased->value, BankProfileStatus::Received->value])
            ->pluck('id')
            ->all();
    }
}
