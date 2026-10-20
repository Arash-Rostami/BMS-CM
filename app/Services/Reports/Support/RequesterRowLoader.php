<?php

namespace App\Services\Reports\Support;

use App\Models\PurchaseRequestItem;
use App\Models\Status;
use App\Services\Reports\Checks\InTransitChecks;
use App\Services\Reports\Rules\ReportStages;
use App\Services\Reports\Rules\RequesterStages;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RequesterRowLoader
{
    public function __construct(private readonly ReportStages $stages) {}

    /**
     * @param  Collection<int, PurchaseRequestItem>  $items
     * @return Collection<int, PurchaseRequestItem>
     */
    public function load(Collection $items, ?ReportFilters $filters = null): Collection
    {
        if ($items->isEmpty()) {
            return $items;
        }

        $matches = $this->matches($items->pluck('id')->all(), $filters);
        $shipmentIds = $matches->flatten(1)->pluck('id')->unique()->all();
        $lines = $this->lines($shipmentIds);
        $customs = $this->customs($shipmentIds);
        $orders = $this->orders($items->pluck('purchase_request_id')->unique()->all());
        $statuses = $this->statuses($items, $matches, $customs, $orders);

        return $items->each(fn (PurchaseRequestItem $item) => $this->fill($item, $matches->get($item->id, collect()), $lines, $customs, $orders->get($item->purchase_request_id, collect()), $statuses));
    }

    private function matches(array $itemIds, ?ReportFilters $filters): Collection
    {
        return DB::table('purchase_request_items')
            ->join('registered_order_purchase_request as link', 'link.purchase_request_id', '=', 'purchase_request_items.purchase_request_id')
            ->join('shipments', 'shipments.registered_order_id', '=', 'link.registered_order_id')
            ->whereIn('purchase_request_items.id', $itemIds)
            ->whereRaw(RequesterStages::matchSql($filters))
            ->select('purchase_request_items.id as item_id', 'shipments.id', 'shipments.shipment_no', 'shipments.part', 'shipments.shipment_status_id', 'shipments.eta')
            ->selectRaw(InTransitChecks::arrivedSql().' as arrived')
            ->orderBy('shipments.id')
            ->get()
            ->groupBy('item_id');
    }

    private function lines(array $shipmentIds): Collection
    {
        return DB::table('shipment_items')
            ->whereIn('shipment_id', $shipmentIds)
            ->select('shipment_id', 'product_id', 'unit', 'quantity', 'net_weight', 'gross_weight')
            ->get()
            ->groupBy('shipment_id');
    }

    private function customs(array $shipmentIds): Collection
    {
        return DB::table('customs')
            ->whereNull('deleted_at')
            ->whereIn('shipment_id', $shipmentIds)
            ->select('shipment_id', 'clearance_status_id')
            ->get()
            ->groupBy('shipment_id');
    }

    private function orders(array $requestIds): Collection
    {
        return DB::table('registered_order_purchase_request as link')
            ->join('registered_orders as o', fn ($join) => $join->on('o.id', '=', 'link.registered_order_id')->whereNull('o.deleted_at'))
            ->whereIn('link.purchase_request_id', $requestIds)
            ->orderBy('o.id')
            ->select('link.purchase_request_id', 'o.id', 'o.status_id')
            ->get()
            ->groupBy('purchase_request_id');
    }

    private function statuses(Collection $items, Collection $matches, Collection $customs, Collection $orders): Collection
    {
        $ids = collect([$items->pluck('pr_status_id'), $matches->flatten(1)->pluck('shipment_status_id'), $customs->flatten(1)->pluck('clearance_status_id'), $orders->flatten(1)->pluck('status_id')])
            ->flatten()
            ->filter()
            ->unique()
            ->all();

        return Status::query()->whereIn('id', $ids)->get(['id', 'name', 'english_name', 'english_type'])->keyBy('id');
    }

    private function fill(PurchaseRequestItem $item, Collection $shipments, Collection $lines, Collection $customs, Collection $orders, Collection $statuses): void
    {
        $own = $shipments->mapWithKeys(fn (object $shipment): array => [$shipment->id => $lines->get($shipment->id, collect())->where('product_id', $item->product_id)]);
        $matched = $own->flatten(1);
        $unspecific = $own->contains(fn (Collection $rows): bool => $rows->isEmpty());
        $mismatch = $matched->contains(fn (object $line): bool => $this->unit($line->unit) !== $this->unit($item->unit));
        $open = $shipments->reject(fn (object $shipment): bool => (bool) $shipment->arrived);
        $latestEta = $open->pluck('eta')->filter()->max();

        $item->shipment_labels = $shipments->map(fn (object $shipment): string => trim($shipment->shipment_no.($shipment->part ? " ({$shipment->part})" : '')))->all();
        $item->chain_unspecific = $unspecific;
        $item->unit_mismatch = $mismatch;
        $item->shipped_quantity = $this->shipped($shipments, $matched, $unspecific, $mismatch);
        $item->remaining_quantity = $item->shipped_quantity === null ? null : (float) $item->quantity - $item->shipped_quantity;
        $item->net_weight = $this->sum($matched, 'net_weight');
        $item->gross_weight = $this->sum($matched, 'gross_weight');
        $item->latest_eta = $latestEta;
        $item->eta_missing = $open->isNotEmpty() && $latestEta === null;
        $item->pr_status = $statuses->get($item->pr_status_id);
        $item->latest_status = $this->latestStatus($shipments, $customs, $orders, $statuses) ?? $item->pr_status;
    }

    private function shipped(Collection $shipments, Collection $matched, bool $unspecific, bool $mismatch): ?float
    {
        return match (true) {
            $shipments->isEmpty() => 0.0,
            $unspecific, $mismatch => null,
            default => (float) $matched->sum('quantity'),
        };
    }

    private function sum(Collection $lines, string $column): ?float
    {
        $values = $lines->pluck($column)->filter(fn ($value): bool => $value !== null);

        return $values->isEmpty() ? null : (float) $values->sum();
    }

    private function unit(?string $unit): string
    {
        return mb_strtolower(trim((string) $unit));
    }

    private function latestStatus(Collection $shipments, Collection $customs, Collection $orders, Collection $statuses): ?Status
    {
        $files = $shipments->flatMap(fn (object $shipment) => $customs->get($shipment->id, collect()))->pluck('clearance_status_id');
        $tracking = $shipments->pluck('shipment_status_id');
        $order = $orders->pluck('status_id')->filter()->last();

        return $this->furthest($files, $statuses)
            ?? $this->furthest($tracking, $statuses)
            ?? ($order ? $statuses->get($order) : null);
    }

    private function furthest(Collection $ids, Collection $statuses): ?Status
    {
        return $ids->filter()->map(fn (int $id): ?Status => $statuses->get($id))->filter()
            ->sortByDesc(fn (Status $status): int => $this->stages->index($status) ?? -1)
            ->first();
    }
}
