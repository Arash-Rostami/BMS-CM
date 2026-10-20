<?php

namespace App\Services\Reports\Checks;

use App\Filament\Resources\RegisteredOrderResource;
use App\Models\RegisteredOrder;
use App\Services\Reports\Builders\OperationsReportService;
use App\Services\Reports\Support\FileActivity;
use App\Services\Reports\Support\ReportDates;
use App\Services\Reports\Support\ReportFilters;
use Closure;
use Illuminate\Database\Eloquent\Builder;

final class RegisteredOrderChecks
{
    public const UNFULFILLED_SQL = 'not exists (select 1 from registered_order_purchase_order rop join purchase_orders po on po.id = rop.purchase_order_id where rop.registered_order_id = registered_orders.id and po.deleted_at is null)';

    public const DATE_BASES = [
        'order_date' => 'registered_orders.order_date',
        'validity_date' => 'registered_orders.validity_date',
        'created_at' => 'registered_orders.created_at',
    ];

    private const REPORTS = ['operations'];

    private const FORM = 'resources/registeredOrder/strings.form.';

    private const LANG = 'resources/report/strings.attention.';

    /**
     * @return array<int, Check>
     */
    public static function all(): array
    {
        return [
            self::idleOverThreshold(),
            self::idleOverFourteen(),
            self::validityExpiring(),
            self::validityExpired(),
            self::noItems(),
            self::noBankProfileAfterDays(),
            self::missingValidityDate(),
            self::missingParty(),
            self::missingCurrency(),
            self::inCustomsWithoutPaymentOrBankProfile(),
        ];
    }

    public static function scoped(Builder $query, ReportFilters $filters): Builder
    {
        $query
            ->when($filters->sellerId, fn (Builder $q, int $id) => $q->where('registered_orders.seller_id', $id))
            ->when($filters->buyerId, fn (Builder $q, int $id) => $q->where('registered_orders.buyer_id', $id))
            ->when($filters->companyId, fn (Builder $q, int $id) => $q->where(fn (Builder $party) => $party->where('registered_orders.seller_id', $id)->orWhere('registered_orders.buyer_id', $id)))
            ->when($filters->currencyId, fn (Builder $q, int $id) => $q->where('registered_orders.currency_id', $id))
            ->when($filters->registeredOrderId, fn (Builder $q, int $id) => $q->where('registered_orders.id', $id))
            ->when($filters->statusIds, fn (Builder $q, array $ids) => $q->whereIn('registered_orders.status_id', $ids))
            ->when($filters->productId, fn (Builder $q, int $id) => $q->whereHas('items', fn (Builder $items) => $items->where('product_id', $id)))
            ->when($filters->categoryId, fn (Builder $q) => $q->whereHas('items.product', fn (Builder $products) => $products->whereIn('products.category_id', $filters->categoryIds())))
            ->when(self::validStage($filters->stage), function (Builder $q, string $stage): void {
                [$sql, $bindings] = app(OperationsReportService::class)->stageSql();
                $q->whereRaw("({$sql}) = ?", [...$bindings, $stage]);
            });

        return ReportDates::apply($query, $filters, self::DATE_BASES, 'order_date');
    }

    public static function open(Builder $query, ReportFilters $filters): Builder
    {
        if ($filters->showClosed) {
            return $query;
        }

        $cleared = CustomChecks::clearedIds($filters);

        return $query->where(fn (Builder $open) => $open
            ->whereDoesntHave('shipments')
            ->orWhereHas('shipments', fn (Builder $shipments) => $shipments->where(fn (Builder $file) => $file
                ->whereDoesntHave('customs')
                ->orWhereHas('customs', fn (Builder $customs) => $customs->where(fn (Builder $unfinished) => $unfinished
                    ->whereNull('clearance_status_id')
                    ->orWhereNotIn('clearance_status_id', $cleared))))));
    }

    public static function openOrders(ReportFilters $filters): Builder
    {
        return self::open(self::scoped(RegisteredOrder::query(), $filters), $filters);
    }

    public static function closedCount(ReportFilters $filters): int
    {
        return self::scoped(RegisteredOrder::query(), $filters)->count() - self::openOrders($filters)->count();
    }

    private static function validStage(?string $stage): ?string
    {
        return $stage !== null && array_key_exists($stage, OperationsReportService::STAGES) ? $stage : null;
    }

    private static function idleOverThreshold(): Check
    {
        return self::make('idle_over_threshold', 'warning', 'status_id', 'tab_general',
            fn (Builder $query, ReportFilters $filters) => self::idle($query)->whereRaw(FileActivity::idleSql().' > ?', [$filters->threshold('idle')]),
            fn (RegisteredOrder $order) => self::idleText($order),
            'idle');
    }

    private static function idleOverFourteen(): Check
    {
        return self::make('idle_over_14', 'error', 'status_id', 'tab_general',
            fn (Builder $query, ReportFilters $filters) => self::idle($query)->whereRaw(FileActivity::idleSql().' > ?', [$filters->threshold('idle_error')]),
            fn (RegisteredOrder $order) => self::idleText($order),
            'idle_error');
    }

    private static function validityExpiring(): Check
    {
        return self::make('validity_expiring', 'warning', 'validity_date', 'tab_general',
            fn (Builder $query, ReportFilters $filters) => $query->whereRaw(self::UNFULFILLED_SQL)->whereBetween('registered_orders.validity_date', [today()->toDateString(), today()->addDays($filters->threshold('validity'))->toDateString()]),
            fn (RegisteredOrder $order) => self::date($order->validity_date),
            'validity');
    }

    private static function validityExpired(): Check
    {
        return self::make('validity_expired', 'error', 'validity_date', 'tab_general',
            fn (Builder $query) => $query->whereRaw(self::UNFULFILLED_SQL)->where('registered_orders.validity_date', '<', today()->toDateString()),
            fn (RegisteredOrder $order) => self::date($order->validity_date));
    }

    private static function noItems(): Check
    {
        return self::make('no_items', 'error', 'items', 'tab_general',
            fn (Builder $query) => $query->whereDoesntHave('items'),
            fn () => '-');
    }

    private static function noBankProfileAfterDays(): Check
    {
        return self::make('no_bank_profile_after_n_days', 'warning', 'order_date', 'tab_general',
            fn (Builder $query, ReportFilters $filters) => $query
                ->whereDoesntHave('bankProfiles')
                ->whereDate('registered_orders.order_date', '<', today()->subDays($filters->threshold('supply'))->toDateString()),
            fn (RegisteredOrder $order) => self::date($order->order_date),
            'supply');
    }

    private static function missingValidityDate(): Check
    {
        return self::make('missing_validity_date', 'warning', 'validity_date', 'tab_general',
            fn (Builder $query) => $query->whereNull('registered_orders.validity_date'),
            fn () => '-');
    }

    private static function missingParty(): Check
    {
        return self::make('missing_party', 'error', 'seller_id', 'tab_general',
            fn (Builder $query) => $query
                ->select('registered_orders.*')
                ->selectRaw(self::goneSql('companies', 'seller_id').' as seller_gone')
                ->selectRaw(self::goneSql('companies', 'buyer_id').' as buyer_gone')
                ->where(fn (Builder $party) => $party->whereRaw(self::goneSql('companies', 'seller_id'))->orWhereRaw(self::goneSql('companies', 'buyer_id'))),
            fn (RegisteredOrder $order) => collect([
                $order->seller_gone ? __(self::FORM.'seller') : null,
                $order->buyer_gone ? __(self::FORM.'buyer') : null,
            ])->filter()->implode(', '));
    }

    private static function missingCurrency(): Check
    {
        return self::make('missing_currency', 'error', 'currency_id', 'tab_general',
            fn (Builder $query) => $query->whereRaw(self::goneSql('currencies', 'currency_id')),
            fn () => '-');
    }

    private static function goneSql(string $table, string $column): string
    {
        return "not exists (select 1 from {$table} ref where ref.id = registered_orders.{$column} and ref.deleted_at is null)";
    }

    private static function inCustomsWithoutPaymentOrBankProfile(): Check
    {
        return self::make('in_customs_without_payment_or_bank_profile', 'warning', 'status_id', 'tab_general',
            function (Builder $query): Builder {
                [$paid, $bindings] = app(OperationsReportService::class)->paidSql();

                return $query
                    ->where(fn (Builder $reached) => $reached->whereHas('shipments')->orWhereHas('customs'))
                    ->whereDoesntHave('bankProfiles')
                    ->whereRaw("not ({$paid})", $bindings);
            },
            fn () => '-');
    }

    private static function date(mixed $date): string
    {
        return $date ? adaptiveDate($date) : '-';
    }

    private static function idle(Builder $query): Builder
    {
        return FileActivity::join($query)
            ->select('registered_orders.*')
            ->selectRaw(FileActivity::idleSql().' as idle_days')
            ->selectRaw('file_activity.last_at is null as idle_from_updated_at');
    }

    private static function idleText(RegisteredOrder $order): string
    {
        $text = __(self::LANG.'days', ['count' => (int) $order->idle_days]);

        return $order->idle_from_updated_at ? $text.' '.__(self::LANG.'no_history_note') : $text;
    }

    private static function make(string $key, string $severity, string $field, string $tab, Closure $where, Closure $offending, ?string $threshold = null): Check
    {
        return new Check(
            key: "registered_order.{$key}",
            model: RegisteredOrder::class,
            severity: $severity,
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => $where(self::open(self::scoped($base, $filters), $filters), $filters),
            offending: $offending,
            expected: fn (mixed $record = null, ?ReportFilters $filters = null): string => __(self::LANG."checks.registered_order.{$key}.expected", $threshold ? ['days' => ($filters ?? new ReportFilters)->threshold($threshold)] : []),
            fix: ['resource' => RegisteredOrderResource::class, 'tab' => self::FORM.$tab, 'field' => $field],
        );
    }
}
