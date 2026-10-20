<?php

namespace App\Services\Reports\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class ReportFilters
{
    private const ID_FIELDS = [
        'seller_id' => 'sellerId',
        'buyer_id' => 'buyerId',
        'category_id' => 'categoryId',
        'product_id' => 'productId',
        'currency_id' => 'currencyId',
        'registered_order_id' => 'registeredOrderId',
        'cost_center_id' => 'costCenterId',
        'bank_id' => 'bankId',
        'company_id' => 'companyId',
        'causer_id' => 'causerId',
        'purchased_currency_id' => 'purchasedCurrencyId',
        'requester_id' => 'requesterId',
    ];

    public const ACTIVITY_WINDOWS = ['24h', '7d', '30d'];

    public const DEFAULT_ACTIVITY_WINDOW = '7d';

    public const IDLE_DAYS = 7;

    public const DEPARTURE_BASES = ['exit_or_tracking', 'etd_too', 'tracking_only'];

    public const THRESHOLD_DEFAULTS = [
        'idle_error' => 14,
        'validity' => 30,
        'supply' => 30,
        'stuck' => 30,
        'due_soon' => 14,
        'arrive_1' => 7,
        'arrive_2' => 14,
        'late_error' => 14,
        'late_grace' => 0,
        'outlier_factor' => 3,
        'supply_full' => 99,
        'top_n' => 5,
        'exceed_pct' => 1,
        'commit_due' => 30,
    ];

    public const WEIGHT_BASES = ['gross', 'net'];

    public const VIEW_MODES = ['shipment', 'product'];

    public const VALUE_BASES = ['grand_total', 'items_total'];

    public const AMOUNT_MODES = ['purchased', 'eur'];

    public const RATE_BASES = ['pair', 'order'];

    public const COMMITMENT_STATES = ['resolved', 'open', 'overdue', 'not_computable'];

    private const MIRRORED_THRESHOLDS = ['stuck'];

    private const THRESHOLD_BOUNDS = [
        'late_grace' => [0, 365],
        'exceed_pct' => [0, 100],
        'supply_full' => [1, 100],
        'top_n' => [1, 20],
        'outlier_factor' => [1, 100],
    ];

    private const MIN_YEAR = 1900;

    private const MAX_YEAR = 2200;

    public function __construct(
        public readonly ?Carbon $dateFrom = null,
        public readonly ?Carbon $dateUntil = null,
        public readonly ?int $sellerId = null,
        public readonly ?int $buyerId = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $productId = null,
        public readonly ?int $currencyId = null,
        public readonly ?int $registeredOrderId = null,
        public readonly ?int $costCenterId = null,
        public readonly ?int $bankId = null,
        public readonly ?int $companyId = null,
        public readonly ?string $supplySource = null,
        public readonly array $statusIds = [],
        public readonly ?int $causerId = null,
        public readonly string $activityWindow = self::DEFAULT_ACTIVITY_WINDOW,
        public readonly ?string $module = null,
        public readonly int $idleDays = self::IDLE_DAYS,
        public readonly ?string $clearanceType = null,
        public readonly array $clearanceStatusIds = [],
        public readonly ?int $bankGuaranteeStatusId = null,
        public readonly ?int $commitmentStatusId = null,
        public readonly ?int $clearedFromStage = null,
        public readonly ?string $arrivalBasis = null,
        public readonly bool $showClosed = false,
        public readonly ?int $stuckDays = null,
        public readonly ?string $quick = null,
        public readonly ?int $docStatusId = null,
        public readonly bool $showArrived = false,
        public readonly ?string $stage = null,
        public readonly ?int $purchasedCurrencyId = null,
        public readonly array $thresholds = [],
        public readonly ?string $dateBasis = null,
        public readonly bool $includeUndated = true,
        public readonly bool $includeVoid = false,
        public readonly ?string $departureBasis = null,
        public readonly array $shipmentStatusIds = [],
        public readonly ?int $requesterId = null,
        public readonly bool $includeFlaggedInvoices = false,
        public readonly bool $includeOutliers = false,
        public readonly string $weightBasis = self::WEIGHT_BASES[0],
        public readonly string $viewMode = self::VIEW_MODES[0],
        public readonly bool $fallbackOrderAmount = false,
        public readonly string $valueBasis = self::VALUE_BASES[0],
        public readonly string $amountIn = self::AMOUNT_MODES[0],
        public readonly ?string $commitmentState = null,
        public readonly string $rateBasis = self::RATE_BASES[0],
    ) {}

    public static function fromArray(?array $data): static
    {
        $data ??= [];
        [$from, $until] = self::normalisedRange(self::date($data['date_from'] ?? null), self::date($data['date_until'] ?? null));
        $ids = [];

        foreach (self::ID_FIELDS as $key => $property) {
            $ids[$property] = self::id($data[$key] ?? null);
        }

        return new self(...$ids, dateFrom: $from, dateUntil: $until, supplySource: self::token($data['supply_source'] ?? null), statusIds: self::ids($data['status_id'] ?? null), activityWindow: self::window($data['activity_window'] ?? null), module: self::token($data['module'] ?? null), idleDays: self::days($data['idle_days'] ?? null) ?? self::IDLE_DAYS, clearanceType: self::token($data['clearance_type'] ?? null), clearanceStatusIds: self::ids($data['clearance_status_id'] ?? null), bankGuaranteeStatusId: self::id($data['bank_guarantee_status_id'] ?? null), commitmentStatusId: self::id($data['commitment_status_id'] ?? null), clearedFromStage: self::id($data['cleared_from_stage'] ?? null), arrivalBasis: self::basis($data['arrival_basis'] ?? null), showClosed: self::flag($data['show_closed'] ?? false), stuckDays: self::days($data['stuck_days'] ?? null), quick: self::token($data['quick'] ?? null), docStatusId: self::id($data['doc_status_id'] ?? null), showArrived: self::flag($data['show_arrived'] ?? false), stage: self::token($data['stage'] ?? null), thresholds: self::thresholdValues($data), dateBasis: self::token($data['date_basis'] ?? null), includeUndated: self::flag($data['include_undated'] ?? true), includeVoid: self::flag($data['include_void'] ?? false), departureBasis: self::departure($data['departure_basis'] ?? null), shipmentStatusIds: self::ids($data['shipment_status_id'] ?? null), includeFlaggedInvoices: self::flag($data['include_flagged_invoices'] ?? false), includeOutliers: self::flag($data['include_outliers'] ?? false), weightBasis: self::choice($data['weight_basis'] ?? null, self::WEIGHT_BASES), viewMode: self::choice($data['view_mode'] ?? null, self::VIEW_MODES), fallbackOrderAmount: self::flag($data['fallback_order_amount'] ?? false), valueBasis: self::choice($data['value_basis'] ?? null, self::VALUE_BASES), amountIn: self::choice($data['amount_in'] ?? null, self::AMOUNT_MODES), commitmentState: self::optionalChoice($data['commitment_state'] ?? null, self::COMMITMENT_STATES), rateBasis: self::choice($data['rate_basis'] ?? null, self::RATE_BASES));
    }

    public function threshold(string $key): int
    {
        return match ($key) {
            'stuck' => $this->stuckDays ?? self::THRESHOLD_DEFAULTS['stuck'],
            'idle' => $this->idleDays,
            default => $this->thresholds[$key] ?? self::THRESHOLD_DEFAULTS[$key] ?? throw new \InvalidArgumentException("Unknown threshold [{$key}]."),
        };
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function thresholdBounds(string $key): array
    {
        return self::THRESHOLD_BOUNDS[$key] ?? [1, 365];
    }

    public function toArray(): array
    {
        $ids = [];

        foreach (self::ID_FIELDS as $key => $property) {
            $ids[$key] = $this->{$property};
        }

        return [
            'date_from' => $this->dateFrom?->toDateString(),
            'date_until' => $this->dateUntil?->toDateString(),
            ...$ids,
            'supply_source' => $this->supplySource,
            'status_id' => $this->statusIds,
            'activity_window' => $this->activityWindow,
            'module' => $this->module,
            'idle_days' => $this->idleDays,
            'clearance_type' => $this->clearanceType,
            'clearance_status_id' => $this->clearanceStatusIds,
            'bank_guarantee_status_id' => $this->bankGuaranteeStatusId,
            'commitment_status_id' => $this->commitmentStatusId,
            'cleared_from_stage' => $this->clearedFromStage,
            'arrival_basis' => $this->arrivalBasis,
            'show_closed' => $this->showClosed,
            'stuck_days' => $this->stuckDays,
            'quick' => $this->quick,
            'doc_status_id' => $this->docStatusId,
            'show_arrived' => $this->showArrived,
            'stage' => $this->stage,
            'date_basis' => $this->dateBasis,
            'include_undated' => $this->includeUndated,
            'include_void' => $this->includeVoid,
            'departure_basis' => $this->departureBasis,
            'shipment_status_id' => $this->shipmentStatusIds,
            'include_flagged_invoices' => $this->includeFlaggedInvoices,
            'include_outliers' => $this->includeOutliers,
            'weight_basis' => $this->weightBasis,
            'view_mode' => $this->viewMode,
            'fallback_order_amount' => $this->fallbackOrderAmount,
            'value_basis' => $this->valueBasis,
            'amount_in' => $this->amountIn,
            'commitment_state' => $this->commitmentState,
            'rate_basis' => $this->rateBasis,
            ...$this->thresholdValuesFlat(),
        ];
    }

    public function cacheKey(): string
    {
        $values = array_filter($this->toArray(), fn ($value) => $value !== null && $value !== []);
        unset($values['quick'], $values['view_mode']);
        $values['today'] = today()->toDateString();
        ksort($values);

        return md5(json_encode($values));
    }

    public function categoryIds(): ?array
    {
        if ($this->categoryId === null) {
            return null;
        }

        return DB::table('category_closure')
            ->where('ancestor_id', $this->categoryId)
            ->pluck('descendant_id')
            ->push($this->categoryId)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function thresholdValuesFlat(): array
    {
        $flat = [];

        foreach (self::thresholdKeys() as $key) {
            $flat["th_{$key}"] = $this->thresholds[$key] ?? null;
        }

        return $flat;
    }

    private static function thresholdKeys(): array
    {
        return array_values(array_diff(array_keys(self::THRESHOLD_DEFAULTS), self::MIRRORED_THRESHOLDS));
    }

    private static function thresholdValues(array $data): array
    {
        $values = [];

        foreach (self::thresholdKeys() as $key) {
            [$min, $max] = self::thresholdBounds($key);
            $value = self::days($data["th_{$key}"] ?? null, $min, $max);

            if ($value !== null) {
                $values[$key] = $value;
            }
        }

        return $values;
    }

    private static function normalisedRange(?Carbon $from, ?Carbon $until): array
    {
        if ($from && $until && $from->greaterThan($until)) {
            [$from, $until] = [$until, $from];
        }

        return [$from?->copy()->startOfDay(), $until?->copy()->endOfDay()];
    }

    private static function date(mixed $value): ?Carbon
    {
        $day = is_string($value) ? substr($value, 0, 10) : '';

        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $day, $parts) || $parts[1] < self::MIN_YEAR || $parts[1] > self::MAX_YEAR || ! checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m-d', $day) ?: null;
    }

    private static function id(mixed $value): ?int
    {
        $value = is_float($value) && floor($value) === $value ? (int) $value : $value;

        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : $id;
    }

    private static function ids(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(fn ($item) => static::id($item), $value))));
    }

    private static function window(mixed $value): string
    {
        return is_string($value) && in_array($value, self::ACTIVITY_WINDOWS, true) ? $value : self::DEFAULT_ACTIVITY_WINDOW;
    }

    private static function token(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $value) ? $value : null;
    }

    private static function basis(mixed $value): ?string
    {
        return is_string($value) && in_array($value, ['doc_submission_date', 'warehouse_date', 'eta'], true) ? $value : null;
    }

    private static function departure(mixed $value): ?string
    {
        return is_string($value) && in_array($value, self::DEPARTURE_BASES, true) ? $value : null;
    }

    private static function flag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private static function choice(mixed $value, array $allowed): string
    {
        return self::optionalChoice($value, $allowed) ?? $allowed[0];
    }

    private static function optionalChoice(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    private static function days(mixed $value, int $min = 1, int $max = 365): ?int
    {
        $value = is_float($value) && floor($value) === $value ? (int) $value : $value;

        if (! is_int($value) && ! is_string($value)) {
            return null;
        }

        $days = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);

        return $days === false ? null : $days;
    }
}
