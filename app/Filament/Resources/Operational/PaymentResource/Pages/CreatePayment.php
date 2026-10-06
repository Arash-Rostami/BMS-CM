<?php

namespace App\Filament\Resources\Operational\PaymentResource\Pages;

use App\Filament\Pages\CreateRecord;
use App\Filament\Resources\Operational\PaymentResource\Traits\PreparePaymentFromTargetable;
use App\Filament\Resources\PaymentResource;
use App\Models\Payment;
use Filament\Notifications\Notification;

class CreatePayment extends CreateRecord
{
    use PreparePaymentFromTargetable;

    protected static string $resource = PaymentResource::class;

    protected static bool $canCreateAnother = false;

    public function afterFill(): void
    {
        self::afterFillFromTargetable();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = PaymentResource::applyInitialStatusOnCreate($data);

        PaymentResource::assertStatusTransitionAllowed(null, 'status_id', $data['status_id'] ?? null);

        $this->warnIfRecentDuplicateExists($data['payee_id'] ?? null, $data['targetable_type'] ?? null, $data['targetable_id'] ?? null);

        return $data;
    }

    protected function warnIfRecentDuplicateExists(?int $payeeId, ?string $targetableType, ?int $targetableId): void
    {
        if (! $payeeId || ! $targetableType || ! $targetableId) {
            return;
        }

        $exists = Payment::where('targetable_type', $targetableType)
            ->where('targetable_id', $targetableId)
            ->where('payee_id', $payeeId)
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if (! $exists) {
            return;
        }

        Notification::make()
            ->title(__('resources/payment/strings.notifications.duplicate_title'))
            ->body(__('resources/payment/strings.notifications.duplicate_body'))
            ->warning()
            ->send();
    }
}
