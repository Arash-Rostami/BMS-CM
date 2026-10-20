<?php

namespace App\Services\Reports\Checks;

use App\Filament\Resources\BankProfileResource;
use App\Filament\Resources\Operational\BankProfileResource\Enums\Status as BankProfileStatus;
use App\Filament\Resources\RegisteredOrderResource;
use App\Filament\Resources\ShipmentResource;
use App\Models\BankProfile;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Services\Reports\Builders\ExecutiveReportService;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\TransitLedger;
use Closure;
use Illuminate\Database\Eloquent\Builder;

final class ExecutiveChecks
{
    private const REPORTS = ['executive'];

    private const LANG = 'resources/report/strings.attention.';

    private const INVOICE_TAB = 'resources/shipment/strings.invoice.tab_label';

    private const LOGISTICS_TAB = 'resources/shipment/strings.form.tab_logistics';

    /**
     * @return array<int, Check>
     */
    public static function all(): array
    {
        return [
            self::shipment('transit_value_unknown', 'error', 'items', fn (array $row): bool => (bool) ($row['flags'] & TransitLedger::FLAG_NO_VALUE), fn (): string => '-'),
            self::shipment('transit_currency_unknown', 'error', '_inv_currency', fn (array $row): bool => (bool) ($row['flags'] & TransitLedger::FLAG_NO_CURRENCY), fn (Shipment $shipment): string => preciseNumber($shipment->grand_total)),
            self::shipment('transit_invoice_currency_mismatch', 'error', '_inv_currency', fn (array $row): bool => (bool) ($row['flags'] & TransitLedger::FLAG_MISMATCH), fn (Shipment $shipment): string => self::currencies($shipment)),
            self::outlier(),
            self::shipment('transit_currency_inferred', 'info', '_inv_currency', fn (array $row): bool => (bool) ($row['flags'] & TransitLedger::FLAG_CURRENCY_INFERRED), fn (Shipment $shipment): string => self::usedCurrency($shipment)),
            self::shipment('transit_value_estimated', 'info', 'items', fn (array $row): bool => $row['estimated'], fn (): string => '-'),
            self::shipment('transit_shipped_above_order', 'info', 'shipped_quantity', fn (array $row): bool => $row['over_shipped'], fn (): string => '-', null, self::LOGISTICS_TAB),
            self::shipment('transit_fx_rate_missing', 'warning', '_inv_currency', fn (array $row): bool => (bool) ($row['flags'] & TransitLedger::FLAG_NO_RATE), fn (Shipment $shipment): string => self::missingRate($shipment)),
            self::supplyUnknown(),
            self::suppliedWithoutEquivalent(),
        ];
    }

    private static function shipment(string $key, string $severity, string $field, Closure $predicate, Closure $offending, ?Closure $select = null, string $tab = self::INVOICE_TAB): Check
    {
        return new Check(
            key: "shipment.{$key}",
            model: Shipment::class,
            severity: $severity,
            reports: self::REPORTS,
            query: function (Builder $base, ReportFilters $filters) use ($predicate, $select): Builder {
                $query = $base->whereIn('shipments.id', TransitLedger::idsWhere($filters, $predicate));

                return $select ? $select($query) : $query;
            },
            offending: $offending,
            expected: fn (): string => __(self::LANG."checks.shipment.{$key}.expected"),
            fix: ['resource' => ShipmentResource::class, 'tab' => $tab, 'field' => $field],
        );
    }

    private static function outlier(): Check
    {
        return self::shipment('transit_invoice_total_outlier', 'warning', '_inv_freight_charges', fn (array $row): bool => (bool) ($row['flags'] & TransitLedger::FLAG_OUTLIER),
            fn (Shipment $shipment): string => __(self::LANG.'transit_outlier', ['total' => preciseNumber($shipment->grand_total), 'items' => preciseNumber($shipment->items_total)]),
            fn (Builder $query): Builder => $query
                ->select('shipments.*')
                ->selectRaw('(select SUM(total_amount) from shipment_items where shipment_items.shipment_id = shipments.id) as items_total'));
    }

    private static function supplyUnknown(): Check
    {
        return new Check(
            key: 'registered_order.transit_supply_unknown',
            model: RegisteredOrder::class,
            severity: 'warning',
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => $base->whereIn('registered_orders.id', TransitLedger::idsWhere($filters, fn (array $row): bool => $row['supply'] === TransitLedger::SUPPLY_UNKNOWN, 'order_id')),
            offending: fn (): string => '-',
            expected: fn (): string => __(self::LANG.'checks.registered_order.transit_supply_unknown.expected'),
            fix: ['resource' => RegisteredOrderResource::class, 'tab' => 'resources/registeredOrder/strings.form.tab_general', 'field' => 'currency_id'],
        );
    }

    private static function suppliedWithoutEquivalent(): Check
    {
        return new Check(
            key: 'bank_profile.transit_supplied_without_equivalent',
            model: BankProfile::class,
            severity: 'error',
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => $base
                ->whereIn('bank_profiles.registered_order_id', TransitLedger::idsWhere($filters, fn (array $row): bool => true, 'order_id'))
                ->whereIn('bank_profiles.status_id', self::suppliedIds())
                ->where(fn (Builder $zero): Builder => $zero->whereNull('bank_profiles.purchased_equivalent')->orWhere('bank_profiles.purchased_equivalent', 0)->orWhereNull('bank_profiles.purchased_currency_id')),
            offending: fn (BankProfile $profile): string => preciseNumber($profile->purchased_equivalent),
            expected: fn (): string => __(self::LANG.'checks.bank_profile.transit_supplied_without_equivalent.expected'),
            fix: ['resource' => BankProfileResource::class, 'tab' => 'resources/bankProfile/strings.form.tab_details', 'field' => 'purchased_equivalent'],
        );
    }

    private static function suppliedIds(): array
    {
        $stages = app(ReportStages::class);

        return collect([BankProfileStatus::Purchased, BankProfileStatus::Received])
            ->map(fn (BankProfileStatus $status): ?int => $stages->statusByName(BankProfile::TYPE_BANK_PROFILE, $status->value)?->id)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private static function currencies(Shipment $shipment): string
    {
        $row = TransitLedger::row($shipment->id);
        $names = app(ExecutiveReportService::class)->currencyNames();

        return $row ? ($names[$row['invoice_currency_id']] ?? '-').' / '.($names[$row['order_currency_id']] ?? '-') : '-';
    }

    private static function usedCurrency(Shipment $shipment): string
    {
        $row = TransitLedger::row($shipment->id);

        return $row ? app(ExecutiveReportService::class)->currencyNames()[$row['currency_id']] ?? '-' : '-';
    }

    private static function missingRate(Shipment $shipment): string
    {
        $row = TransitLedger::row($shipment->id);
        $names = app(ExecutiveReportService::class)->currencyNames();

        return $row ? delimiter($row['value'], $names[$row['currency_id']] ?? null) : '-';
    }
}
