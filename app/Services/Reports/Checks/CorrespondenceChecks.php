<?php

namespace App\Services\Reports\Checks;

use App\Filament\Resources\CorrespondenceResource;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Models\Correspondence;
use App\Models\Custom;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use App\Services\Reports\Support\ReportFilters;
use Illuminate\Database\Eloquent\Builder;

final class CorrespondenceChecks
{
    public const PRIORITIES = [Priority::HIGH, Priority::URGENT];

    public const CLOSED_STATUSES = ['Approved', 'Archived'];

    /**
     * @return array<int, Check>
     */
    public static function all(): array
    {
        return [self::openHighPriority()];
    }

    public static function onOpenFiles(Builder $query, ReportFilters $filters): Builder
    {
        $orders = RegisteredOrderChecks::openOrders($filters)->select('registered_orders.id');

        return $query
            ->whereIn('correspondences.priority', array_map(fn (Priority $priority): string => $priority->value, self::PRIORITIES))
            ->where(fn (Builder $open) => $open->whereNull('correspondences.status_id')->orWhereNotIn('correspondences.status_id', self::closedStatusIds()))
            ->where(fn (Builder $file) => $file
                ->where(fn (Builder $own) => $own->where('correspondences.correspondable_type', RegisteredOrder::class)->whereIn('correspondences.correspondable_id', $orders))
                ->orWhere(fn (Builder $shipment) => $shipment->where('correspondences.correspondable_type', Shipment::class)->whereIn('correspondences.correspondable_id', Shipment::query()->select('id')->whereIn('registered_order_id', $orders)))
                ->orWhere(fn (Builder $custom) => $custom->where('correspondences.correspondable_type', Custom::class)->whereIn('correspondences.correspondable_id', Custom::query()->select('id')->whereIn('registered_order_id', $orders))));
    }

    private static function closedStatusIds(): Builder
    {
        return Status::query()
            ->select('id')
            ->where('english_type', Correspondence::TYPE_CORRESPONDENCE_STATUS)
            ->whereIn('english_name', self::CLOSED_STATUSES);
    }

    private static function openHighPriority(): Check
    {
        return new Check(
            key: 'correspondence.open_high_priority',
            model: Correspondence::class,
            severity: 'warning',
            reports: ['operations'],
            query: fn (Builder $base, ReportFilters $filters): Builder => self::onOpenFiles($base, $filters),
            offending: fn (Correspondence $correspondence) => Priority::tryFrom((string) $correspondence->priority)?->getLabel() ?? '-',
            expected: fn (): string => __('resources/report/strings.attention.checks.correspondence.open_high_priority.expected'),
            fix: ['resource' => CorrespondenceResource::class, 'tab' => 'resources/correspondence/strings.infolist.tab_general', 'field' => 'status_id'],
        );
    }
}
