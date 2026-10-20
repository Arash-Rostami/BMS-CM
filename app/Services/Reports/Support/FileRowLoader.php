<?php

namespace App\Services\Reports\Support;

use App\Models\BankProfile;
use App\Models\Correspondence;
use App\Models\Custom;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use App\Models\Status;
use App\Models\StatusHistory;
use App\Models\User;
use App\Services\AuditChanges;
use App\Services\Calendar\CalendarPathResolver;
use App\Services\Reports\Rules\ReportStages;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

class FileRowLoader
{
    public function __construct(private readonly ReportStages $stages, private readonly AuditChanges $audit, private readonly CalendarPathResolver $paths) {}

    /**
     * @param  Collection<int, RegisteredOrder>  $orders
     */
    public function load(Collection $orders): Collection
    {
        if (! $orders->first() instanceof RegisteredOrder) {
            return $orders;
        }

        $ids = $orders->modelKeys();
        $profiles = BankProfile::query()->whereIn('registered_order_id', $ids)->get(['registered_order_id', 'status_id'])->groupBy('registered_order_id');
        $shipments = $this->latest(Shipment::class, 'registered_order_id', $ids)->keyBy('registered_order_id');
        $customs = $this->latest(Custom::class, 'registered_order_id', $ids)->keyBy('registered_order_id');
        $mails = $this->lastCorrespondence($ids);
        $statuses = $this->statuses($profiles, $shipments, $customs);
        $events = $this->events($orders);

        foreach ($orders as $order) {
            $order->setAttribute('fx_status', $this->highest($profiles->get($order->id), $statuses));
            $order->setAttribute('latest_shipment', $shipments->get($order->id));
            $order->setAttribute('latest_custom', $customs->get($order->id));
            $order->setAttribute('latest_mail', $mails->get($order->id));
            $order->setAttribute('last_event', $events[$order->id] ?? null);
            $this->attachStatuses($order, $statuses);
        }

        return $orders;
    }

    private function latest(string $class, string $key, array $ids): Collection
    {
        return $class::query()
            ->whereIn('id', $class::query()->selectRaw('max(id)')->whereIn($key, $ids)->groupBy($key))
            ->get();
    }

    private function lastCorrespondence(array $ids): Collection
    {
        return Correspondence::query()
            ->with('recipients:id,name')
            ->whereIn('id', Correspondence::query()->selectRaw('max(id)')->where('correspondable_type', RegisteredOrder::class)->whereIn('correspondable_id', $ids)->groupBy('correspondable_id'))
            ->get()
            ->keyBy('correspondable_id');
    }

    private function statuses(Collection $profiles, Collection $shipments, Collection $customs): Collection
    {
        $ids = $profiles->flatten(1)->pluck('status_id')
            ->merge($shipments->flatMap(fn (Shipment $shipment): array => [$shipment->shipment_status_id, $shipment->operation_status_id, $shipment->doc_status_id]))
            ->merge($customs->pluck('clearance_status_id'));

        return Status::withTrashed()->whereIn('id', $ids->filter()->unique())->get()->keyBy('id');
    }

    private function highest(?Collection $profiles, Collection $statuses): ?Status
    {
        return $profiles?->map(fn (BankProfile $profile): ?Status => $statuses->get($profile->status_id))
            ->filter()
            ->sortByDesc(fn (Status $status): int => $this->stages->index($status) ?? -1)
            ->first();
    }

    private function attachStatuses(RegisteredOrder $order, Collection $statuses): void
    {
        $shipment = $order->latest_shipment;

        $order->setAttribute('shipment_status', $statuses->get($shipment?->shipment_status_id));
        $order->setAttribute('operation_status', $statuses->get($shipment?->operation_status_id));
        $order->setAttribute('doc_status', $statuses->get($shipment?->doc_status_id));
        $order->setAttribute('clearance_status', $statuses->get($order->latest_custom?->clearance_status_id));
    }

    /**
     * @return array<int, array{text: string, by: ?string}>
     */
    private function events(Collection $orders): array
    {
        $parsed = $orders->mapWithKeys(fn (RegisteredOrder $order): array => [$order->id => explode(':', (string) $order->last_ref)])->filter(fn (array $ref): bool => count($ref) === 3);
        $activities = Activity::query()->whereIn('id', $this->refs($parsed, 'a'))->get()->keyBy('id');
        $histories = StatusHistory::query()->with(['fromStatus', 'status'])->whereIn('id', $this->refs($parsed, 's'))->get()->keyBy('id');
        $users = User::withTrashed()->whereIn('id', $parsed->pluck(2)->filter()->unique())->pluck('name', 'id');
        $viewable = $this->audit->viewableModels();

        return $parsed->map(function (array $ref) use ($activities, $histories, $users, $viewable): array {
            $text = $ref[0] === 'a' ? $this->activityText($activities->get((int) $ref[1]), $viewable) : $this->historyText($histories->get((int) $ref[1]), $viewable);

            return ['text' => $text, 'by' => $text === '-' ? null : $users->get((int) $ref[2])];
        })->all();
    }

    private function refs(Collection $parsed, string $kind): array
    {
        return $parsed->filter(fn (array $ref): bool => $ref[0] === $kind)->pluck(1)->map(fn ($id): int => (int) $id)->all();
    }

    private function activityText(?Activity $activity, array $viewable): string
    {
        return $activity && in_array($activity->subject_type, $viewable, true) ? $this->audit->summary($activity) : '-';
    }

    private function historyText(?StatusHistory $history, array $viewable): string
    {
        if (! $history || ! in_array($history->statusable_type, $viewable, true)) {
            return '-';
        }

        return $this->paths->columnLabel($history->statusable_type, $history->field).': '
            .($history->fromStatus?->localized_name ?? '-').' → '.($history->status?->localized_name ?? '-');
    }
}
