<?php

namespace App\Services\Reports\Rules;

use App\Filament\Resources\Operational\BankProfileResource\Enums\Status as BankProfileStatus;
use App\Filament\Resources\Operational\CustomResource\Enums\ClearanceStatus;
use App\Filament\Resources\Operational\ShipmentResource\Enums\TrackingStatus;
use App\Models\BankProfile;
use App\Models\Custom;
use App\Models\Shipment;
use App\Models\Status;
use App\Services\Reports\Checks\CustomChecks;
use App\Services\StatusWorkflow;
use Illuminate\Support\Collection;

class ReportStages
{
    protected static array $ordered = [];

    protected static array $byName = [];

    public static function flush(): void
    {
        static::$ordered = [];
        static::$byName = [];
        CustomChecks::flush();
    }

    public function ordered(string $englishType): Collection
    {
        return static::$ordered[$englishType] ??= $this->resolveOrdered($englishType);
    }

    public function index(Status $status): ?int
    {
        $position = $this->sequence($status->english_type)->search(fn (Status $item) => $item->is($status));

        return $position === false ? null : $position;
    }

    public function isAtOrAfter(Status $status, string $englishName): bool
    {
        $current = $this->index($status);
        $target = $this->sequence($status->english_type)->search(fn (Status $item) => $item->english_name === $englishName);

        return $current !== null && $target !== false && $current >= $target;
    }

    public function statusByName(string $englishType, string $englishName): ?Status
    {
        $key = "{$englishType}:{$englishName}";

        if (! array_key_exists($key, static::$byName)) {
            static::$byName[$key] = Status::findBy($englishType, $englishName);
        }

        return static::$byName[$key];
    }

    public function inTransit(): ?Status
    {
        return $this->statusByName(Shipment::TYPE_TRACKING_STATUS, TrackingStatus::InTransit->value);
    }

    public function arrived(): ?Status
    {
        return $this->statusByName(Shipment::TYPE_TRACKING_STATUS, TrackingStatus::ArrivedAtDestination->value);
    }

    protected function fallback(): array
    {
        return [
            BankProfile::TYPE_BANK_PROFILE => [
                BankProfileStatus::ReadyForAllocation->value,
                BankProfileStatus::AwaitingBankReview->value,
                BankProfileStatus::AwaitingSupply->value,
                BankProfileStatus::Allocated->value,
                BankProfileStatus::Purchased->value,
                BankProfileStatus::Received->value,
            ],
            Custom::TYPE_CLEARANCE_STATUS => [
                ClearanceStatus::DocumentsSubmitted->value,
                ClearanceStatus::Declared->value,
                ClearanceStatus::Appraisal->value,
                ClearanceStatus::PendingPayment->value,
                ClearanceStatus::ExitGate->value,
                ClearanceStatus::TenPercentRemaining->value,
                ClearanceStatus::Completed->value,
            ],
            Shipment::TYPE_TRACKING_STATUS => array_map(fn (TrackingStatus $case) => $case->value, TrackingStatus::cases()),
        ];
    }

    protected function separate(): array
    {
        return [
            BankProfile::TYPE_BANK_PROFILE => [
                BankProfileStatus::UnderEditing->value,
                BankProfileStatus::Rejected->value,
                BankProfileStatus::DocumentaryBill->value,
            ],
            Custom::TYPE_CLEARANCE_STATUS => [ClearanceStatus::PendingEditing->value],
        ];
    }

    protected function resolveOrdered(string $englishType): Collection
    {
        $workflow = StatusWorkflow::orderedStatuses($englishType);

        return $workflow->isNotEmpty() ? $this->withRemaining($workflow->values(), $englishType) : $this->fromFallback($englishType);
    }

    protected function withRemaining(Collection $ordered, string $englishType): Collection
    {
        $separate = $this->separate()[$englishType] ?? [];

        return $ordered->concat(
            Status::where('english_type', $englishType)->orderBy('id')->get()
                ->reject(fn (Status $status) => $ordered->contains('id', $status->id) || in_array($status->english_name, $separate, true))
        )->values();
    }

    protected function fromFallback(string $englishType): Collection
    {
        $live = Status::where('english_type', $englishType)->orderBy('id')->get();
        $names = $this->fallback()[$englishType] ?? [];
        $skipped = array_merge($names, $this->separate()[$englishType] ?? []);

        return collect($names)
            ->flatMap(fn (string $name) => $live->where('english_name', $name))
            ->concat($live->reject(fn (Status $status) => in_array($status->english_name, $skipped, true)))
            ->values();
    }

    protected function sequence(string $englishType): Collection
    {
        $separate = $this->separate()[$englishType] ?? [];

        return $this->ordered($englishType)
            ->reject(fn (Status $status) => in_array($status->english_name, $separate, true))
            ->values();
    }
}
