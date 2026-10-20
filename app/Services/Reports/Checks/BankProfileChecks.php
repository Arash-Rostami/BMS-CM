<?php

namespace App\Services\Reports\Checks;

use App\Filament\Resources\BankProfileResource;
use App\Filament\Resources\Operational\BankProfileResource\Enums\Status as BankProfileStatus;
use App\Models\BankProfile;
use App\Models\Status;
use App\Services\Reports\Support\ReportDates;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\SqlToday;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class BankProfileChecks
{
    public const STUCK_DAYS = ReportFilters::THRESHOLD_DEFAULTS['stuck'];

    public const DUE_SOON_DAYS = ReportFilters::THRESHOLD_DEFAULTS['due_soon'];

    public const DATE_BASES = [
        'creation_date' => 'bank_profiles.creation_date',
        'allocation_date' => 'bank_profiles.allocation_date',
        'purchase_date' => 'bank_profiles.purchase_date',
        'payment_due_date' => 'bank_profiles.payment_due_date',
    ];

    private const REPORTS = ['fx_supply'];

    private const FORM = 'resources/bankProfile/strings.form.';

    private const LANG = 'resources/report/strings.attention.';

    private const SUPPLIED = [BankProfileStatus::Purchased, BankProfileStatus::Received];

    private const ALLOCATED_OR_LATER = [BankProfileStatus::Allocated, BankProfileStatus::Purchased, BankProfileStatus::Received];

    private const SETTLED = [BankProfileStatus::Received, BankProfileStatus::Rejected];

    public const HISTORY_AT = '(select max(created_at) from status_histories where statusable_type = ? and statusable_id = bank_profiles.id and field = ?)';

    /**
     * @return array<int, Check>
     */
    public static function all(): array
    {
        return [
            self::purchasedWithoutEquivalent(),
            self::purchasedWithoutCurrency(),
            self::purchasedWithoutPurchaseDate(),
            self::allocatedWithoutAllocationDate(),
            self::missingExchangeRate(),
            self::requestedAmountZero(),
            self::stuckInStage(),
            self::paymentDueSoon(),
            self::paymentOverdue(),
            self::statusInvalidType(),
            self::statusMissing(),
            self::noStatusHistory(),
            self::dateOrderInvalid(),
            self::missingPaymentDueDate(),
            self::suppliedWithoutBank(),
        ];
    }

    private static function dateOrderInvalid(): Check
    {
        return self::make('date_order_invalid', 'error', 'allocation_date', 'tab_general',
            fn (Builder $query) => $query->where(fn (Builder $wrong) => $wrong
                ->whereColumn('bank_profiles.allocation_date', '<', 'bank_profiles.creation_date')
                ->orWhereColumn('bank_profiles.purchase_date', '<', 'bank_profiles.allocation_date')),
            fn (BankProfile $profile) => self::orderText($profile));
    }

    private static function missingPaymentDueDate(): Check
    {
        return self::make('missing_payment_due_date', 'warning', 'payment_due_date', 'tab_general',
            fn (Builder $query) => $query->whereNull('bank_profiles.payment_due_date')->where(fn (Builder $open) => self::unsettled($open)),
            fn () => '-');
    }

    private static function suppliedWithoutBank(): Check
    {
        return self::make('supplied_without_bank', 'error', 'bank_id', 'tab_general',
            fn (Builder $query) => $query->whereIn('bank_profiles.status_id', self::statusIds(self::ALLOCATED_OR_LATER))->whereNull('bank_profiles.bank_id'),
            fn () => '-');
    }

    private static function orderText(BankProfile $profile): string
    {
        $parts = [];

        if ($profile->allocation_date && $profile->creation_date && $profile->allocation_date->lt($profile->creation_date)) {
            $parts[] = self::date($profile->allocation_date).' < '.self::date($profile->creation_date);
        }

        if ($profile->purchase_date && $profile->allocation_date && $profile->purchase_date->lt($profile->allocation_date)) {
            $parts[] = self::date($profile->purchase_date).' < '.self::date($profile->allocation_date);
        }

        return implode(' ; ', $parts) ?: '-';
    }

    private static function purchasedWithoutEquivalent(): Check
    {
        return self::make('purchased_without_equivalent', 'error', 'purchased_equivalent', 'tab_details',
            fn (Builder $query) => self::supplied($query)->where(fn (Builder $zero) => $zero->whereNull('purchased_equivalent')->orWhere('purchased_equivalent', 0)),
            fn (BankProfile $profile) => preciseNumber($profile->purchased_equivalent));
    }

    private static function purchasedWithoutCurrency(): Check
    {
        return self::make('purchased_without_currency', 'error', 'purchased_currency_id', 'tab_details',
            fn (Builder $query) => self::supplied($query)->whereNull('purchased_currency_id'),
            fn () => '-');
    }

    private static function purchasedWithoutPurchaseDate(): Check
    {
        return self::make('purchased_without_purchase_date', 'warning', 'purchase_date', 'tab_general',
            fn (Builder $query) => self::supplied($query)->whereNull('purchase_date'),
            fn () => '-');
    }

    private static function allocatedWithoutAllocationDate(): Check
    {
        return self::make('allocated_without_allocation_date', 'warning', 'allocation_date', 'tab_general',
            fn (Builder $query) => $query->whereIn('status_id', self::statusIds(self::ALLOCATED_OR_LATER))->whereNull('allocation_date'),
            fn () => '-');
    }

    private static function missingExchangeRate(): Check
    {
        return self::make('missing_exchange_rate', 'warning', 'exchange_rate', 'tab_details',
            fn (Builder $query) => self::supplied($query)->where(fn (Builder $zero) => $zero->whereNull('exchange_rate')->orWhere('exchange_rate', 0)),
            fn (BankProfile $profile) => preciseNumber($profile->exchange_rate));
    }

    private static function requestedAmountZero(): Check
    {
        return self::make('requested_amount_zero', 'error', 'requested_amount', 'tab_details',
            fn (Builder $query) => $query->where(fn (Builder $zero) => $zero->whereNull('requested_amount')->orWhere('requested_amount', 0)),
            fn (BankProfile $profile) => preciseNumber($profile->requested_amount));
    }

    private static function stuckInStage(): Check
    {
        return self::make('stuck_in_stage', 'warning', 'status_id', 'tab_general',
            fn (Builder $query, ReportFilters $filters) => $query
                ->select('bank_profiles.*')
                ->selectRaw(self::HISTORY_AT.' as history_at', [BankProfile::class, 'status_id'])
                ->where(fn (Builder $open) => self::unsettled($open))
                ->whereRaw('DATEDIFF('.SqlToday::literal().', COALESCE('.self::HISTORY_AT.', bank_profiles.updated_at, bank_profiles.created_at)) > ?', [BankProfile::class, 'status_id', $filters->threshold('stuck')]),
            fn (BankProfile $profile) => self::stuckText($profile),
            [], 'stuck');
    }

    public static function dueSoon(Builder $query, ReportFilters $filters): Builder
    {
        return $query
            ->whereBetween('bank_profiles.payment_due_date', [today()->toDateString(), today()->addDays($filters->threshold('due_soon'))->toDateString()])
            ->where(fn (Builder $open) => self::unsettled($open));
    }

    public static function overdue(Builder $query): Builder
    {
        return $query
            ->where('bank_profiles.payment_due_date', '<', today()->toDateString())
            ->where(fn (Builder $open) => self::unsettled($open));
    }

    public static function notFinished(Builder $query): Builder
    {
        return $query->where(fn (Builder $open) => $open
            ->whereNull('bank_profiles.status_id')
            ->orWhereNotIn('bank_profiles.status_id', self::finishedIds()));
    }

    public static function showsFinished(ReportFilters $filters): bool
    {
        return $filters->showClosed || ($filters->statusIds !== [] && self::finishedIds()->whereIn('id', $filters->statusIds)->exists());
    }

    public static function finishedIds(): Builder
    {
        return self::statusIds([...self::SUPPLIED, BankProfileStatus::Rejected]);
    }

    private static function paymentDueSoon(): Check
    {
        return self::make('payment_due_soon', 'warning', 'payment_due_date', 'tab_general',
            fn (Builder $query, ReportFilters $filters) => self::dueSoon($query, $filters),
            fn (BankProfile $profile) => self::date($profile->payment_due_date),
            [], 'due_soon');
    }

    private static function paymentOverdue(): Check
    {
        return self::make('payment_overdue', 'error', 'payment_due_date', 'tab_general',
            fn (Builder $query) => self::overdue($query),
            fn (BankProfile $profile) => self::date($profile->payment_due_date));
    }

    private static function statusInvalidType(): Check
    {
        return self::make('status_invalid_type', 'error', 'status_id', 'tab_general',
            fn (Builder $query) => $query
                ->select('bank_profiles.*')
                ->addSelect(['status_type' => Status::withTrashed()->select('english_type')->whereColumn('statuses.id', 'bank_profiles.status_id')])
                ->whereIn('status_id', Status::withTrashed()->select('id')->where('english_type', '<>', BankProfile::TYPE_BANK_PROFILE)),
            fn (BankProfile $profile) => $profile->status_type ?? '-',
            ['type' => BankProfile::TYPE_BANK_PROFILE]);
    }

    private static function statusMissing(): Check
    {
        return self::make('status_missing', 'error', 'status_id', 'tab_general',
            fn (Builder $query) => $query->whereNull('status_id'),
            fn () => '-',
            ['type' => BankProfile::TYPE_BANK_PROFILE]);
    }

    private static function unsettled(Builder $query): Builder
    {
        return $query->whereNull('status_id')->orWhereNotIn('status_id', self::statusIds(self::SETTLED));
    }

    private static function noStatusHistory(): Check
    {
        return self::make('no_status_history', 'info', 'status_id', 'tab_general',
            fn (Builder $query) => $query
                ->whereNotNull('status_id')
                ->whereDoesntHave('statusHistories', fn (Builder $history) => $history->where('field', 'status_id')),
            fn (BankProfile $profile) => self::date($profile->updated_at));
    }

    private static function make(string $key, string $severity, string $field, string $tab, Closure $where, Closure $offending, array $expected = [], ?string $threshold = null): Check
    {
        return new Check(
            key: "bank_profile.{$key}",
            model: BankProfile::class,
            severity: $severity,
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => $where(self::scoped($base, $filters), $filters),
            offending: $offending,
            expected: fn (mixed $record = null, ?ReportFilters $filters = null): string => __(self::LANG."checks.bank_profile.{$key}.expected", $threshold ? ['days' => ($filters ?? new ReportFilters)->threshold($threshold)] : $expected),
            fix: ['resource' => BankProfileResource::class, 'tab' => self::FORM.$tab, 'field' => $field],
        );
    }

    public static function scoped(Builder $query, ReportFilters $filters): Builder
    {
        $query
            ->when($filters->bankId, fn (Builder $q, int $id) => $q->where('bank_profiles.bank_id', $id))
            ->when($filters->companyId, fn (Builder $q, int $id) => $q->where('bank_profiles.company_id', $id))
            ->when($filters->registeredOrderId, fn (Builder $q, int $id) => $q->where('bank_profiles.registered_order_id', $id))
            ->when($filters->currencyId, fn (Builder $q, int $id) => $q->where('bank_profiles.requested_currency_id', $id))
            ->when($filters->purchasedCurrencyId, fn (Builder $q, int $id) => $q->where('bank_profiles.purchased_currency_id', $id))
            ->when($filters->supplySource, fn (Builder $q, string $source) => $q->where('bank_profiles.supply_source', $source))
            ->when($filters->statusIds, fn (Builder $q, array $ids) => $q->whereIn('bank_profiles.status_id', $ids));

        return ReportDates::apply($query, $filters, self::DATE_BASES, 'creation_date');
    }

    private static function supplied(Builder $query): Builder
    {
        return $query->whereIn('status_id', self::statusIds(self::SUPPLIED));
    }

    public static function statusIds(array $cases): Builder
    {
        return Status::query()
            ->select('id')
            ->where('english_type', BankProfile::TYPE_BANK_PROFILE)
            ->whereIn('english_name', array_map(fn (BankProfileStatus $case) => $case->value, $cases));
    }

    private static function date(mixed $date): string
    {
        return $date ? adaptiveDate($date) : '-';
    }

    private static function stuckText(BankProfile $profile): string
    {
        $since = Carbon::parse($profile->history_at ?? $profile->updated_at ?? $profile->created_at)->startOfDay();
        $text = __(self::LANG.'days', ['count' => (int) $since->diffInDays(today())]);

        return $profile->history_at ? $text : $text.' '.__(self::LANG.'no_history_note');
    }
}
