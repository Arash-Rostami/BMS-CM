<?php

namespace App\Services\Reports\Checks;

use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\GuaranteeStatus;
use App\Filament\Resources\Operational\ShipmentResource\Enums\TrackingStatus;
use App\Filament\Resources\ShipmentResource;
use App\Models\Custom;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\Status;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Support\ReportDates;
use App\Services\Reports\Support\ReportFilters;
use App\Services\Reports\Support\SqlToday;
use Closure;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CustomChecks
{
    public const STUCK_DAYS = 30;

    public const HISTORY_AT = '(select max(created_at) from status_histories where statusable_type = ? and statusable_id = customs.id and field = ?)';

    private static ?array $validIds = null;

    private static array $cleared = [];

    private const REPORTS = ['customs'];

    private const FORM = 'resources/custom/strings.form.';

    private const LANG = 'resources/report/strings.attention.';

    /**
     * @return array<int, Check>
     */
    public static function all(): array
    {
        return [
            self::clearedBeforeArrival(),
            self::clearedWithoutClearanceDate(),
            self::missingDocSubmissionDate(),
            self::openOverThreshold(),
            self::tenPercentOpenWithoutExitDate(),
            self::guaranteeNotReturnedAfterCompletion(),
            self::clearanceTypeMissing(),
            self::shipmentNotArrived(),
            self::withoutInvoice(),
            self::stuckInStage(),
            self::missingDeclarationNo(),
            self::docSubmissionAfterClearance(),
        ];
    }

    private static function validStatusIds(): Builder
    {
        return Status::query()
            ->select('id')
            ->where('english_type', Custom::TYPE_CLEARANCE_STATUS)
            ->whereIn('english_name', array_map(fn (ClearanceStatus $case): string => $case->value, ClearanceStatus::cases()));
    }

    private static function enumNames(): array
    {
        return array_map(fn (ClearanceStatus $case): string => $case->value, ClearanceStatus::cases());
    }

    public static function flush(): void
    {
        self::$validIds = null;
        self::$cleared = [];
    }

    public static function validIdList(): array
    {
        return self::$validIds ??= Status::query()
            ->where('english_type', Custom::TYPE_CLEARANCE_STATUS)
            ->whereIn('english_name', self::enumNames())
            ->pluck('id')
            ->all();
    }

    public static function statusId(string $englishType, string $englishName): ?int
    {
        return app(ReportStages::class)->statusByName($englishType, $englishName)?->id;
    }

    public static function basisExpression(ReportFilters $filters): string
    {
        return match ($filters->arrivalBasis) {
            'warehouse_date' => 's.warehouse_date',
            'eta' => 's.eta',
            default => 'customs.doc_submission_date',
        };
    }

    public static function clearedIds(ReportFilters $filters): array
    {
        return self::$cleared[(int) $filters->clearedFromStage] ??= self::resolveClearedIds($filters);
    }

    private static function resolveClearedIds(ReportFilters $filters): array
    {
        $stages = app(ReportStages::class);
        $completed = $stages->statusByName(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Completed->value);
        $chosen = $filters->clearedFromStage
            ? Status::query()->where('english_type', Custom::TYPE_CLEARANCE_STATUS)->find($filters->clearedFromStage)
            : null;
        $position = collect([$chosen, $completed])->filter()->map(fn (Status $status): ?int => $stages->index($status))->first(fn (?int $index): bool => $index !== null);

        if ($position === null) {
            return [];
        }

        return $stages->ordered(Custom::TYPE_CLEARANCE_STATUS)
            ->filter(fn (Status $status): bool => in_array($status->english_name, self::enumNames(), true) && $stages->index($status) >= $position)
            ->pluck('id')
            ->all();
    }

    public static function open(Builder $query, ReportFilters $filters): Builder
    {
        $cleared = self::clearedIds($filters);

        return $query->when($cleared !== [], fn (Builder $group): Builder => $group->where(fn (Builder $open): Builder => $open
            ->whereNull('customs.clearance_status_id')
            ->orWhereNotIn('customs.clearance_status_id', $cleared)));
    }

    public static function scoped(Builder $query, ReportFilters $filters): Builder
    {
        $query
            ->when($filters->registeredOrderId, fn (Builder $customs, int $id): Builder => $customs->where('customs.registered_order_id', $id))
            ->when($filters->sellerId, fn (Builder $customs, int $id): Builder => $customs->whereHas('registeredOrder', fn (Builder $order): Builder => $order->where('registered_orders.seller_id', $id)))
            ->when($filters->buyerId, fn (Builder $customs, int $id): Builder => $customs->whereHas('registeredOrder', fn (Builder $order): Builder => $order->where('registered_orders.buyer_id', $id)))
            ->when($filters->clearanceType, fn (Builder $customs, string $type): Builder => $customs->where('customs.clearance_type', $type))
            ->when($filters->clearanceStatusIds, fn (Builder $customs, array $ids): Builder => $customs->whereIn('customs.clearance_status_id', $ids))
            ->when($filters->bankGuaranteeStatusId, fn (Builder $customs, int $id): Builder => $customs->where('customs.bank_guarantee_status_id', $id))
            ->when($filters->commitmentStatusId, fn (Builder $customs, int $id): Builder => $customs->where('customs.commitment_status_id', $id))
            ->when($filters->productId, fn (Builder $customs, int $id): Builder => $customs->where(fn (Builder $items): Builder => $items
                ->whereHas('shipment.items', fn (Builder $item): Builder => $item->where('shipment_items.product_id', $id))
                ->orWhereHas('registeredOrder.items', fn (Builder $item): Builder => $item->where('registered_order_items.product_id', $id))))
            ->when($filters->categoryId, function (Builder $customs) use ($filters): Builder {
                $products = Product::query()->whereIn('category_id', $filters->categoryIds())->pluck('id')->all();

                return $customs->where(fn (Builder $items): Builder => $items
                    ->whereHas('shipment.items', fn (Builder $item): Builder => $item->whereIn('shipment_items.product_id', $products))
                    ->orWhereHas('registeredOrder.items', fn (Builder $item): Builder => $item->whereIn('registered_order_items.product_id', $products)));
            })
            ->when($filters->docStatusId, fn (Builder $customs, int $id): Builder => $customs->whereHas('shipment', fn (Builder $shipment): Builder => $shipment->where('shipments.doc_status_id', $id)))
            ->when($filters->companyId, fn (Builder $customs, int $id): Builder => $customs->whereHas('shipment', fn (Builder $shipment): Builder => $shipment->where('shipments.company_id', $id)));

        return ReportDates::apply($query, $filters, self::dateBases(), 'doc_submission_date');
    }

    /**
     * @return array<string, string|Expression>
     */
    public static function dateBases(): array
    {
        $shipment = fn (string $column): Expression => DB::raw("(select sd.{$column} from shipments sd where sd.id = customs.shipment_id and sd.deleted_at is null)");

        return [
            'doc_submission_date' => 'customs.doc_submission_date',
            'clearance_date' => 'customs.clearance_date',
            'warehouse_date' => $shipment('warehouse_date'),
            'eta' => $shipment('eta'),
            'created_at' => 'customs.created_at',
        ];
    }

    private static function withBasis(Builder $query, ReportFilters $filters): Builder
    {
        return $query
            ->select('customs.*')
            ->selectRaw(self::basisExpression($filters).' as arrival_basis_date')
            ->leftJoin('shipments as s', fn ($join) => $join->on('s.id', '=', 'customs.shipment_id')->whereNull('s.deleted_at'));
    }

    private static function withStatusName(Builder $query): Builder
    {
        $column = app()->getLocale() === 'fa' ? 'name' : 'english_name';

        return $query
            ->select('customs.*')
            ->addSelect(['status_name' => Status::withTrashed()->select($column)->whereColumn('statuses.id', 'customs.clearance_status_id')]);
    }

    private static function clearedBeforeArrival(): Check
    {
        return self::make('cleared_before_arrival', 'error', 'clearance_date', 'tab_general',
            fn (Builder $query, ReportFilters $filters): Builder => self::withBasis($query, $filters)
                ->whereNotNull('customs.clearance_date')
                ->whereRaw('customs.clearance_date < '.self::basisExpression($filters)),
            fn (Custom $custom): string => self::date($custom->clearance_date).' < '.self::date($custom->arrival_basis_date));
    }

    private static function clearedWithoutClearanceDate(): Check
    {
        return self::make('cleared_without_clearance_date', 'warning', 'clearance_date', 'tab_general',
            fn (Builder $query, ReportFilters $filters): Builder => self::withStatusName($query)
                ->whereIn('customs.clearance_status_id', self::clearedIds($filters))
                ->whereNull('customs.clearance_date'),
            fn (Custom $custom): string => (string) ($custom->status_name ?? '-'));
    }

    private static function missingDocSubmissionDate(): Check
    {
        return self::make('missing_doc_submission_date', 'warning', 'doc_submission_date', 'tab_general',
            fn (Builder $query, ReportFilters $filters): Builder => self::open($query, $filters)->whereNull('customs.doc_submission_date'),
            fn (): string => '-');
    }

    private static function openOverThreshold(): Check
    {
        return new Check(
            key: 'custom.open_over_threshold',
            model: Custom::class,
            severity: 'warning',
            reports: self::REPORTS,
            query: function (Builder $base, ReportFilters $filters): Builder {
                $basis = self::basisExpression($filters);
                $threshold = $filters->threshold('stuck');

                return self::withBasis(self::open(self::scoped($base, $filters), $filters), $filters)
                    ->whereRaw("({$basis}) is not null")
                    ->whereRaw('DATEDIFF(COALESCE(customs.clearance_date, '.SqlToday::literal()."), {$basis}) > {$threshold}")
                    ->selectRaw('DATEDIFF(COALESCE(customs.clearance_date, '.SqlToday::literal()."), {$basis}) as days_in_customs")
                    ->selectRaw("({$basis}) is null as basis_missing");
            },
            offending: fn (Custom $custom): string => __(self::LANG.'days', ['count' => (int) $custom->days_in_customs]),
            expected: fn (?Custom $custom = null, ?ReportFilters $filters = null): string => __(self::LANG.'checks.custom.open_over_threshold.expected', ['days' => ($filters ?? new ReportFilters)->threshold('stuck')]),
            fix: ['resource' => CustomResource::class, 'tab' => self::FORM.'tab_status_financial', 'field' => 'clearance_status_id'],
        );
    }

    private static function tenPercentOpenWithoutExitDate(): Check
    {
        return self::make('ten_percent_open_without_exit_date', 'warning', 'ten_percent_exit_date', 'tab_general',
            fn (Builder $query): Builder => $query->when(
                self::statusId(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::TenPercentRemaining->value),
                fn (Builder $customs, int $id): Builder => $customs->where('customs.clearance_status_id', $id)->whereNull('customs.ten_percent_exit_date'),
                fn (Builder $customs): Builder => $customs->whereRaw('0 = 1')),
            fn (): string => '-');
    }

    private static function guaranteeNotReturnedAfterCompletion(): Check
    {
        return self::make('guarantee_not_returned_after_completion', 'warning', 'bank_guarantee_status_id', 'tab_status_financial',
            fn (Builder $query): Builder => $query
                ->when(self::statusId(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Completed->value), fn (Builder $customs, int $id): Builder => $customs->where('customs.clearance_status_id', $id), fn (Builder $customs): Builder => $customs->whereRaw('0 = 1'))
                ->when(self::statusId(Custom::TYPE_BANK_GUARANTEE_STATUS, GuaranteeStatus::NotReturned->value), fn (Builder $customs, int $id): Builder => $customs->where('customs.bank_guarantee_status_id', $id), fn (Builder $customs): Builder => $customs->whereRaw('0 = 1')),
            fn (): string => '-');
    }

    private static function clearanceTypeMissing(): Check
    {
        return self::make('clearance_type_missing', 'warning', 'clearance_type', 'tab_general',
            fn (Builder $query): Builder => $query->whereNull('customs.clearance_type'),
            fn (): string => '-');
    }

    private static function shipmentNotArrived(): Check
    {
        return new Check(
            key: 'custom.shipment_not_arrived',
            model: Custom::class,
            severity: 'warning',
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => self::scoped($base, $filters)
                ->select('customs.*')
                ->selectRaw('s.shipment_no as shipment_number')
                ->leftJoin('shipments as s', fn ($join) => $join->on('s.id', '=', 'customs.shipment_id')->whereNull('s.deleted_at'))
                ->whereNotNull('s.id')
                ->whereNull('s.warehouse_date')
                ->tap(function (Builder $customs): void {
                    $arrived = self::arrivedOrLaterIds();

                    $arrived === []
                        ? $customs->whereRaw('0 = 1')
                        : $customs->where(fn (Builder $tracking): Builder => $tracking->whereNull('s.shipment_status_id')->orWhereNotIn('s.shipment_status_id', $arrived));
                }),
            offending: fn (Custom $custom): string => (string) ($custom->shipment_number ?? '-'),
            expected: fn (): string => __(self::LANG.'checks.custom.shipment_not_arrived.expected'),
            fix: ['resource' => ShipmentResource::class, 'tab' => 'resources/shipment/strings.form.tab_logistics', 'field' => 'shipment_status_id'],
        );
    }

    public static function arrivedOrLaterIds(): array
    {
        $stages = app(ReportStages::class);
        $arrived = $stages->arrived();

        if ($arrived === null) {
            return [];
        }

        $known = array_map(fn (TrackingStatus $case): string => $case->value, TrackingStatus::cases());

        return $stages->ordered(Shipment::TYPE_TRACKING_STATUS)
            ->filter(fn (Status $status): bool => in_array($status->english_name, $known, true) && $stages->index($status) >= $stages->index($arrived))
            ->pluck('id')
            ->all();
    }

    private static function withoutInvoice(): Check
    {
        return new Check(
            key: 'custom.without_invoice',
            model: Custom::class,
            severity: 'warning',
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => self::scoped($base, $filters)
                ->whereHas('shipment', fn (Builder $shipment): Builder => $shipment->whereDoesntHave('items')),
            offending: fn (): string => '-',
            expected: fn (): string => __(self::LANG.'checks.custom.without_invoice.expected'),
            fix: ['resource' => ShipmentResource::class, 'tab' => 'resources/shipment/strings.invoice.tab_label', 'field' => 'items'],
        );
    }

    private static function stuckInStage(): Check
    {
        return self::make('stuck_in_stage', 'warning', 'clearance_status_id', 'tab_status_financial',
            function (Builder $query, ReportFilters $filters): Builder {
                $cleared = self::clearedIds($filters);

                return $query
                    ->select('customs.*')
                    ->selectRaw(self::HISTORY_AT.' as history_at', [Custom::class, 'clearance_status_id'])
                    ->selectRaw('DATEDIFF('.SqlToday::literal().', COALESCE('.self::HISTORY_AT.', customs.updated_at, customs.created_at)) as days_in_stage', [Custom::class, 'clearance_status_id'])
                    ->whereNotNull('customs.clearance_status_id')
                    ->whereIn('customs.clearance_status_id', self::validStatusIds())
                    ->when($cleared !== [], fn (Builder $customs): Builder => $customs->whereNotIn('customs.clearance_status_id', $cleared))
                    ->whereRaw('DATEDIFF('.SqlToday::literal().', COALESCE('.self::HISTORY_AT.', customs.updated_at, customs.created_at)) > ?', [Custom::class, 'clearance_status_id', $filters->threshold('stuck')]);
            },
            fn (Custom $custom): string => self::stuckText($custom),
            fn (ReportFilters $filters): array => ['days' => $filters->threshold('stuck')]);
    }

    private static function missingDeclarationNo(): Check
    {
        return self::make('missing_declaration_no', 'info', 'declaration_no', 'tab_general',
            fn (Builder $query, ReportFilters $filters): Builder => self::open($query, $filters)
                ->where(fn (Builder $customs): Builder => $customs->whereNull('customs.declaration_no')->orWhere('customs.declaration_no', '')),
            fn (): string => '-');
    }

    private static function docSubmissionAfterClearance(): Check
    {
        return self::make('doc_submission_after_clearance', 'error', 'doc_submission_date', 'tab_general',
            fn (Builder $query): Builder => $query
                ->whereNotNull('customs.doc_submission_date')
                ->whereNotNull('customs.clearance_date')
                ->whereColumn('customs.doc_submission_date', '>', 'customs.clearance_date'),
            fn (Custom $custom): string => self::date($custom->doc_submission_date).' > '.self::date($custom->clearance_date));
    }

    private static function stuckText(Custom $custom): string
    {
        $text = __(self::LANG.'days', ['count' => (int) $custom->days_in_stage]);

        return $custom->history_at === null ? $text.' '.__(self::LANG.'no_history_note') : $text;
    }

    private static function date(mixed $date): string
    {
        return $date ? adaptiveDate($date) : '-';
    }

    private static function make(string $key, string $severity, string $field, string $tab, Closure $where, Closure $offending, array|Closure $expected = []): Check
    {
        return new Check(
            key: "custom.{$key}",
            model: Custom::class,
            severity: $severity,
            reports: self::REPORTS,
            query: fn (Builder $base, ReportFilters $filters): Builder => $where(self::scoped($base, $filters), $filters),
            offending: $offending,
            expected: fn (?Custom $custom = null, ?ReportFilters $filters = null): string => __(self::LANG."checks.custom.{$key}.expected", $expected instanceof Closure ? $expected($filters ?? new ReportFilters) : $expected),
            fix: ['resource' => CustomResource::class, 'tab' => self::FORM.$tab, 'field' => $field],
        );
    }
}
