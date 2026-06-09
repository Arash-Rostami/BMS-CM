<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Pages;

use App\Filament\Resources\Operational\ShipmentResource\Traits\HandlesDocumentChecklistForm;
use App\Filament\Resources\Operational\ShipmentResource\Traits\SyncsDocumentChecklist;
use App\Filament\Resources\ShipmentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use App\Filament\Pages\EditRecord;
use App\Models\BankProfile;

class EditShipment extends EditRecord
{
    use HandlesDocumentChecklistForm, SyncsDocumentChecklist;

    protected static string $resource = ShipmentResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($id = $data['registered_order_id'] ?? null) {
            $total = BankProfile::where('registered_order_id', $id)
                ->whereNull('deleted_at')
                ->get()
                ->sum(fn($bp) => $bp->total_purchased_remittance);
            $data['remittance_amount'] = $total > 0 ? round((float) $total, 2) : null;
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
