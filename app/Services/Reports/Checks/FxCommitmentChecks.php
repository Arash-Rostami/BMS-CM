<?php

namespace App\Services\Reports\Checks;

use App\Filament\Resources\BankProfileResource;
use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\BankProfileResource\Enums\Status as BankProfileStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\CommitmentStatus;
use App\Filament\Resources\RegisteredOrderResource;
use App\Filament\Resources\ShipmentResource;
use App\Models\BankProfile;
use App\Models\Custom;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Services\Reports\Builders\FxCommitmentsReportService;
use App\Services\Reports\Rules\FxRateService;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\FxCommitmentLedger;
use App\Services\Reports\Support\ReportDates;
use App\Services\Reports\Support\ReportFilters;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class FxCommitmentChecks
{
    public const DATE_BASES = [
        'commitment_payment_date' => 'bank_profiles.commitment_payment_date',
        'creation_date' => 'bank_profiles.creation_date',
        'purchase_date' => 'bank_profiles.purchase_date',
    ];

    private const REPORTS = ['fx_commitments'];

    private const LANG = 'resources/report/strings.attention.';

    private const BANK_TAB = 'resources/bankProfile/strings.form.';

    private const CUSTOM_TAB = 'resources/custom/strings.form.';

    private const INVOICE_TAB = 'resources/shipment/strings.invoice.tab_label';

    private const LOGISTICS_TAB = 'resources/shipment/strings.form.tab_logistics';

    private const ORDER_TAB = 'resources/registeredOrder/strings.form.tab_general';

    /**
     * @return array<int, Check>
     */
    public static function all(): array
    {
        return [
            self::profile('commitment_without_equivalent', 'error', 'purchased_equivalent', 'tab_details', fn (Builder $q): Builder => self::counted($q)->where(fn (Builder $zero) => $zero->whereNull('bank_profiles.purchased_equivalent')->orWhere('bank_profiles.purchased_equivalent', 0)), fn (BankProfile $p): string => preciseNumber($p->purchased_equivalent)),
            self::profile('commitment_without_currency', 'error', 'purchased_currency_id', 'tab_details', fn (Builder $q): Builder => self::counted($q)->whereNull('bank_profiles.purchased_currency_id')),
            self::profile('commitment_without_order', 'error', 'registered_order_id', 'tab_general', fn (Builder $q): Builder => self::counted($q)->whereRaw('ro.id is null')),
            self::profile('commitment_missing_date', 'warning', 'commitment_payment_date', 'tab_general', fn (Builder $q, ReportFilters $f): Builder => $q->whereRaw(self::predicate($f, 'no_date'))),
            self::profile('commitment_overdue', 'error', 'commitment_payment_date', 'tab_general', fn (Builder $q, ReportFilters $f): Builder => $q->whereRaw(self::predicate($f, 'overdue')), fn (BankProfile $p): string => self::date($p->commitment_payment_date), fn (ReportFilters $f): array => ['days' => $f->threshold('late_grace')]),
            self::profile('commitment_due_soon', 'warning', 'commitment_payment_date', 'tab_general', fn (Builder $q, ReportFilters $f): Builder => $q->whereRaw(self::predicate($f, 'due_soon')), fn (BankProfile $p): string => self::date($p->commitment_payment_date), fn (ReportFilters $f): array => ['days' => $f->threshold('commit_due')]),
            self::profile('commitment_date_order_invalid', 'warning', 'commitment_payment_date', 'tab_general', fn (Builder $q): Builder => self::counted($q)->whereNotNull('bank_profiles.commitment_payment_date')->where(fn (Builder $wrong) => $wrong
                ->whereColumn('bank_profiles.commitment_payment_date', '<', 'bank_profiles.purchase_date')
                ->orWhereColumn('bank_profiles.commitment_payment_date', '<', 'bank_profiles.creation_date')), fn (BankProfile $p): string => self::date($p->commitment_payment_date)),
            self::profile('fx_rate_missing', 'warning', 'purchased_currency_id', 'tab_details', fn (Builder $q, ReportFilters $f): Builder => self::counted($q)->where(fn (Builder $missing) => $missing
                ->whereIn('bank_profiles.registered_order_id', app(FxCommitmentLedger::class)->orderIdsWithFlag($f, FxCommitmentLedger::NO_RATE))
                ->orWhereIn('bank_profiles.purchased_currency_id', self::currenciesWithoutEurRate($f)))),
            self::custom('cleared_without_invoice', 'error', ShipmentResource::class, self::INVOICE_TAB, 'items', fn (Builder $q, ReportFilters $f): Builder => $q->whereIn('customs.shipment_id', app(FxCommitmentLedger::class)->shipmentIdsWith($f, FxCommitmentLedger::NO_INVOICE))),
            self::custom('cleared_without_shipment', 'error', CustomResource::class, self::CUSTOM_TAB.'tab_general', 'shipment_id', fn (Builder $q): Builder => $q->whereNull('s.id')),
            self::custom('cleared_without_order', 'error', CustomResource::class, self::CUSTOM_TAB.'tab_general', 'registered_order_id', fn (Builder $q): Builder => $q->whereRaw('not exists (select 1 from registered_orders where registered_orders.id = customs.registered_order_id and registered_orders.deleted_at is null)')),
            self::custom('order_differs_from_shipment', 'warning', CustomResource::class, self::CUSTOM_TAB.'tab_general', 'registered_order_id', fn (Builder $q): Builder => $q->whereNotNull('s.id')->whereColumn('s.registered_order_id', '<>', 'customs.registered_order_id')),
            self::custom('cleared_on_void_shipment', 'warning', ShipmentResource::class, self::LOGISTICS_TAB, 'status_id', fn (Builder $q, ReportFilters $f): Builder => $q->whereIn('customs.shipment_id', app(FxCommitmentLedger::class)->shipmentIdsWith($f, FxCommitmentLedger::VOID))),
            self::custom('commitment_not_completed_after_clearance', 'warning', CustomResource::class, self::CUSTOM_TAB.'tab_status_financial', 'commitment_status_id', fn (Builder $q): Builder => self::notCompleted($q)),
            self::shipment('invoice_currency_mismatch', 'error', '_inv_currency', self::INVOICE_TAB, FxCommitmentLedger::MISMATCH),
            self::shipment('cleared_currency_unknown', 'error', '_inv_currency', self::INVOICE_TAB, FxCommitmentLedger::NO_CURRENCY),
            self::shipment('cleared_currency_inferred', 'info', '_inv_currency', self::INVOICE_TAB, FxCommitmentLedger::CURRENCY_INFERRED),
            self::shipment('invoice_total_outlier', 'warning', 'items', self::INVOICE_TAB, FxCommitmentLedger::OUTLIER, fn (Shipment $s): string => preciseNumber($s->grand_total)),
            self::order('resolved_exceeds_purchased', 'currency_id', fn (Builder $q, ReportFilters $f): Builder => $q->whereIn('registered_orders.id', app(FxCommitmentLedger::class)->overResolvedOrderIds($f)), fn (ReportFilters $f): array => ['pct' => $f->threshold('exceed_pct')]),
            self::order('mixed_purchase_currency', 'currency_id', fn (Builder $q): Builder => $q->whereIn('registered_orders.id', DB::table('bank_profiles')
                ->whereNull('deleted_at')
                ->whereIn('status_id', self::countedIds())
                ->groupBy('registered_order_id')
                ->havingRaw('count(distinct purchased_currency_id) > 1 or sum(case when purchased_currency_id is null then 1 else 0 end) > 0')
                ->select('registered_order_id'))),
            self::order('cleared_without_purchase', 'status_id', fn (Builder $q, ReportFilters $f): Builder => $q
                ->whereIn('registered_orders.id', app(FxCommitmentLedger::class)->orderIds($f))
                ->whereNotExists(fn ($counted) => $counted->selectRaw('1')->from('bank_profiles as bp')->whereColumn('bp.registered_order_id', 'registered_orders.id')->whereNull('bp.deleted_at')->whereIn('bp.status_id', self::countedIds()))),
        ];
    }

    /**
     * @return array<int, int>
     */
    public static function countedIds(): array
    {
        $stages = app(ReportStages::class);

        return collect([BankProfileStatus::Purchased, BankProfileStatus::Received])
            ->map(fn (BankProfileStatus $case): ?int => $stages->statusByName(BankProfile::TYPE_BANK_PROFILE, $case->value)?->id)
            ->filter()
            ->map(fn (int $id): int => $id)
            ->values()
            ->all();
    }

    public static function scoped(Builder $query, ReportFilters $filters): Builder
    {
        $query
            ->when($filters->bankId, fn (Builder $q, int $id) => $q->where('bank_profiles.bank_id', $id))
            ->when($filters->companyId, fn (Builder $q, int $id) => $q->where('bank_profiles.company_id', $id))
            ->when($filters->registeredOrderId, fn (Builder $q, int $id) => $q->where('bank_profiles.registered_order_id', $id))
            ->when($filters->purchasedCurrencyId, fn (Builder $q, int $id) => $q->where('bank_profiles.purchased_currency_id', $id))
            ->when($filters->supplySource, fn (Builder $q, string $source) => $q->where('bank_profiles.supply_source', $source))
            ->whereIn('bank_profiles.status_id', $filters->statusIds !== [] ? $filters->statusIds : self::countedIds());

        return ReportDates::apply($query, $filters, self::DATE_BASES, 'commitment_payment_date');
    }

    private static function counted(Builder $query): Builder
    {
        return $query->whereIn('bank_profiles.status_id', self::countedIds());
    }

    private static function predicate(ReportFilters $filters, string $key): string
    {
        return app(FxCommitmentsReportService::class)->predicates($filters)[$key];
    }

    private static function currenciesWithoutEurRate(ReportFilters $filters): array
    {
        if ($filters->amountIn !== 'eur') {
            return [];
        }

        $fx = app(FxRateService::class);

        return DB::table('bank_profiles')
            ->whereNull('deleted_at')
            ->whereIn('status_id', self::countedIds())
            ->whereNotNull('purchased_currency_id')
            ->distinct()
            ->pluck('purchased_currency_id')
            ->filter(fn ($id): bool => $fx->toEur(1.0, (int) $id) === null)
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    private static function notCompleted(Builder $query): Builder
    {
        $completed = app(ReportStages::class)->statusByName(Custom::TYPE_COMMITMENT_STATUS, CommitmentStatus::Completed->value)?->id;

        return $completed === null
            ? $query->whereRaw('0 = 1')
            : $query->where(fn (Builder $open) => $open->whereNull('customs.commitment_status_id')->orWhere('customs.commitment_status_id', '<>', $completed));
    }

    private static function date(mixed $date): string
    {
        return $date ? adaptiveDate($date) : '-';
    }

    private static function expected(string $key, ?Closure $params): Closure
    {
        return fn (mixed $record = null, ?ReportFilters $filters = null): string => __(self::LANG."checks.{$key}.expected", $params ? $params($filters ?? new ReportFilters) : []);
    }

    private static function profile(string $key, string $severity, string $field, string $tab, Closure $where, ?Closure $offending = null, ?Closure $params = null): Check
    {
        return new Check(
            key: "bank_profile.{$key}",
            model: BankProfile::class,
            severity: $severity,
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => $where(app(FxCommitmentsReportService::class)->profiles($base, $filters), $filters),
            offending: $offending ?? fn (): string => '-',
            expected: self::expected("bank_profile.{$key}", $params),
            fix: ['resource' => BankProfileResource::class, 'tab' => self::BANK_TAB.$tab, 'field' => $field],
        );
    }

    private static function custom(string $key, string $severity, string $resource, string $tab, string $field, Closure $where): Check
    {
        return new Check(
            key: "custom.{$key}",
            model: Custom::class,
            severity: $severity,
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => $where(self::clearedFiles($base, $filters), $filters),
            offending: fn (): string => '-',
            expected: self::expected("custom.{$key}", null),
            fix: ['resource' => $resource, 'tab' => $tab, 'field' => $field],
        );
    }

    private static function clearedFiles(Builder $base, ReportFilters $filters): Builder
    {
        return $base
            ->select('customs.*')
            ->leftJoin('shipments as s', fn ($join) => $join->on('s.id', '=', 'customs.shipment_id')->whereNull('s.deleted_at'))
            ->whereIn('customs.clearance_status_id', CustomChecks::clearedIds($filters))
            ->when($filters->registeredOrderId, fn (Builder $customs, int $id): Builder => $customs->where('customs.registered_order_id', $id));
    }

    private static function shipment(string $key, string $severity, string $field, string $tab, int $condition, ?Closure $offending = null): Check
    {
        return new Check(
            key: "shipment.{$key}",
            model: Shipment::class,
            severity: $severity,
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => $base->whereIn('shipments.id', app(FxCommitmentLedger::class)->shipmentIdsWith($filters, $condition)),
            offending: $offending ?? fn (): string => '-',
            expected: self::expected("shipment.{$key}", null),
            fix: ['resource' => ShipmentResource::class, 'tab' => $tab, 'field' => $field],
        );
    }

    private static function order(string $key, string $field, Closure $where, ?Closure $params = null): Check
    {
        return new Check(
            key: "registered_order.{$key}",
            model: RegisteredOrder::class,
            severity: 'warning',
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => $where($base->when($filters->registeredOrderId, fn (Builder $orders, int $id): Builder => $orders->where('registered_orders.id', $id)), $filters),
            offending: fn (): string => '-',
            expected: self::expected("registered_order.{$key}", $params),
            fix: ['resource' => RegisteredOrderResource::class, 'tab' => self::ORDER_TAB, 'field' => $field],
        );
    }
}
