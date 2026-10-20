<?php

namespace App\Services\Reports\Rules;

use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Status as PurchaseRequestStatus;
use App\Models\Custom;
use App\Models\PurchaseRequest;
use App\Services\Reports\Checks\CustomChecks;
use App\Services\Reports\Checks\InTransitChecks;
use App\Services\Reports\Support\ReportFilters;

final class RequesterStages
{
    public const STAGES = ['reviewing', 'sourcing', 'awaiting', 'in_transit', 'arrived', 'at_customs', 'cleared'];

    public const COLORS = [
        'reviewing' => 'gray',
        'sourcing' => 'info',
        'awaiting' => 'warning',
        'in_transit' => 'primary',
        'arrived' => 'success',
        'at_customs' => 'warning',
        'cleared' => 'success',
    ];

    private const LANG = 'resources/report/strings.requester.stages.';

    public static function label(string $stage): string
    {
        $stages = app(ReportStages::class);

        return match ($stage) {
            'reviewing' => $stages->statusByName(PurchaseRequest::TYPE_PURCHASE_REQUEST, PurchaseRequestStatus::UnderReview->value)?->localized_name ?: __('resources/purchaseRequest/strings.general.status.UnderReview'),
            'sourcing' => __(self::LANG.'sourcing'),
            'awaiting' => __(self::LANG.'awaiting'),
            'in_transit' => $stages->inTransit()?->localized_name ?: __(self::LANG.'in_transit'),
            'arrived' => $stages->arrived()?->localized_name ?: __(self::LANG.'arrived'),
            'at_customs' => __('resources/custom/strings.general.model_label'),
            default => $stages->statusByName(Custom::TYPE_CLEARANCE_STATUS, ClearanceStatus::Completed->value)?->localized_name ?: ClearanceStatus::Completed->getLabel(),
        };
    }

    public static function linkSql(string $requestColumn = 'purchase_request_items.purchase_request_id'): string
    {
        return "exists (select 1 from registered_order_purchase_request l join registered_orders o on o.id = l.registered_order_id and o.deleted_at is null where l.purchase_request_id = {$requestColumn})";
    }

    public static function matchSql(?ReportFilters $filters = null): string
    {
        $live = InTransitChecks::voidSqlFor($filters ?? new ReportFilters);
        $void = str_replace('shipments.', 'x.', $live);

        return '(shipments.deleted_at is null and '.$live.' and exists (select 1 from registered_order_purchase_request l join registered_orders o on o.id = l.registered_order_id and o.deleted_at is null where l.purchase_request_id = purchase_request_items.purchase_request_id and l.registered_order_id = shipments.registered_order_id)'
            .' and (exists (select 1 from shipment_items m where m.shipment_id = shipments.id and m.product_id = purchase_request_items.product_id)'
            .' or not exists (select 1 from shipment_items n join shipments x on x.id = n.shipment_id and x.deleted_at is null and '.$void.' where x.registered_order_id = shipments.registered_order_id)))';
    }

    public static function shipmentExists(string $condition = '', ?ReportFilters $filters = null): string
    {
        return 'exists (select 1 from '.self::pivotFrom().' where '.self::pivotWhere($filters).($condition === '' ? '' : " and {$condition}").')';
    }

    public static function lineExists(?ReportFilters $filters = null): string
    {
        return 'exists (select 1 from '.self::pivotFrom().' join shipment_items u on u.shipment_id = shipments.id where '.self::pivotWhere($filters).' and u.product_id = purchase_request_items.product_id)';
    }

    private static function pivotFrom(): string
    {
        return 'registered_order_purchase_request l join registered_orders o on o.id = l.registered_order_id and o.deleted_at is null join shipments on shipments.registered_order_id = l.registered_order_id';
    }

    private static function pivotWhere(?ReportFilters $filters): string
    {
        $live = InTransitChecks::voidSqlFor($filters ?? new ReportFilters);
        $void = str_replace('shipments.', 'x.', $live);

        return 'l.purchase_request_id = purchase_request_items.purchase_request_id and shipments.deleted_at is null and '.$live
            .' and (exists (select 1 from shipment_items m where m.shipment_id = shipments.id and m.product_id = purchase_request_items.product_id)'
            .' or not exists (select 1 from shipment_items n join shipments x on x.id = n.shipment_id and x.deleted_at is null and '.$void.' where x.registered_order_id = shipments.registered_order_id))';
    }

    public static function sharedSql(): string
    {
        return 'exists (select 1 from purchase_request_items b join purchase_requests pb on pb.id = b.purchase_request_id and pb.deleted_at is null'
            .' join registered_order_purchase_request lb on lb.purchase_request_id = b.purchase_request_id'
            .' join registered_order_purchase_request la on la.registered_order_id = lb.registered_order_id'
            .' where la.purchase_request_id = purchase_request_items.purchase_request_id and b.deleted_at is null and b.product_id = purchase_request_items.product_id and b.purchase_request_id <> purchase_request_items.purchase_request_id)';
    }

    public static function shippedAboveRequestedSql(?ReportFilters $filters = null): string
    {
        $match = self::matchSql($filters);

        return '(not '.self::sharedSql()
            .' and (select coalesce(sum(u.quantity), 0) from shipment_items u join shipments on shipments.id = u.shipment_id where u.product_id = purchase_request_items.product_id and '.$match.') > purchase_request_items.quantity'
            .' and not exists (select 1 from shipment_items z join shipments on shipments.id = z.shipment_id where z.product_id = purchase_request_items.product_id and '.$match
            ." and lower(trim(coalesce(z.unit, ''))) <> lower(trim(coalesce(purchase_request_items.unit, '')))))";
    }

    public static function stageSql(?ReportFilters $filters = null): string
    {
        $filters ??= new ReportFilters;
        $cleared = array_map('intval', CustomChecks::clearedIds($filters));
        $clearedSql = $cleared === [] ? '(0 = 1)' : 'customs.clearance_status_id in ('.implode(',', $cleared).')';
        $customs = 'exists (select 1 from customs where customs.shipment_id = shipments.id and customs.deleted_at is null';

        return 'case'
            .' when '.self::shipmentExists("{$customs} and {$clearedSql})", $filters)." then 'cleared'"
            .' when '.self::shipmentExists("{$customs})", $filters)." then 'at_customs'"
            .' when '.self::shipmentExists(InTransitChecks::arrivedSql(), $filters)." then 'arrived'"
            .' when '.self::shipmentExists(InTransitChecks::inTransitSql($filters), $filters)." then 'in_transit'"
            .' when '.self::shipmentExists('', $filters)." then 'awaiting'"
            .' when '.self::linkSql()." then 'sourcing'"
            ." else 'reviewing' end";
    }

    public static function lateBefore(?ReportFilters $filters = null): string
    {
        return "'".today()->subDays(($filters ?? new ReportFilters)->threshold('late_grace'))->toDateString()."'";
    }

    public static function lateSql(?ReportFilters $filters = null): string
    {
        $filters ??= new ReportFilters;

        return '(pr.required_by_date is not null and pr.required_by_date < '.self::lateBefore($filters).' and ('.self::stageSql($filters).") <> 'cleared')";
    }

    /**
     * @return array<string, string>
     */
    public static function flags(?ReportFilters $filters = null): array
    {
        $filters ??= new ReportFilters;
        $stage = '('.self::stageSql($filters).')';

        return [
            'late' => self::lateSql($filters),
            'arriving' => "({$stage} = 'in_transit' and ".self::shipmentExists(InTransitChecks::arrivingSql(0, $filters->threshold('arrive_1'), $filters), $filters).')',
            'no_eta' => "({$stage} = 'in_transit' and not ".self::shipmentExists(InTransitChecks::inTransitSql($filters).' and shipments.eta is not null', $filters).')',
            'weight_unknown' => '('.self::shipmentExists('', $filters).' and not '.self::lineExists($filters).')',
            'weight_unknown_unshipped' => '(not '.self::shipmentExists('', $filters).')',
        ];
    }
}
