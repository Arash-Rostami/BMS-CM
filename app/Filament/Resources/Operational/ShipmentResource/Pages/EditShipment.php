<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Pages;

use App\Filament\Pages\EditRecord;
use App\Filament\Resources\Operational\ShipmentResource\Traits\HandlesDocumentChecklistForm;
use App\Filament\Resources\Operational\ShipmentResource\Traits\SyncsDocumentChecklist;
use App\Filament\Resources\ShipmentResource;
use App\Models\BankProfile;
use App\Models\EntityAttribute;
use App\Models\Shipment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;

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
                ->sum(fn ($bp) => $bp->total_purchased_remittance);
            $data['remittance_amount'] = $total > 0 ? round((float) $total, 2) : null;
        }

        return $this->hydrateInvoiceData($data);
    }

    protected function hydrateInvoiceData(array $data): array
    {
        $attr = EntityAttribute::where('entity_type', Shipment::class)
            ->where('entity_id', $this->record->id)
            ->where('key', 'commercial_invoice')
            ->first();

        if ($attr && is_array($attr->value)) {
            $inv = $attr->value;
            $data['_inv_pi_id'] = $inv['proforma_invoice_id'] ?? null;
            $data['_inv_invoice_no'] = $inv['invoice_no'] ?? null;
            $data['_inv_invoice_date'] = $inv['invoice_date'] ?? null;
            $data['_inv_seller_name'] = $inv['seller_name'] ?? null;
            $data['_inv_seller_address'] = $inv['seller_address'] ?? null;
            $data['_inv_buyer_name'] = $inv['buyer_name'] ?? null;
            $data['_inv_buyer_address'] = $inv['buyer_address'] ?? null;
            $data['_inv_buyer_comm_card_no'] = $inv['buyer_comm_card_no'] ?? null;
            $data['_inv_currency'] = $inv['currency'] ?? null;
            $data['_inv_payment_terms'] = $inv['payment_terms'] ?? null;
            $data['_inv_transport_mode'] = $inv['transport_mode'] ?? null;
            $data['_inv_incoterms'] = $inv['incoterms'] ?? null;
            $data['_inv_port_of_loading'] = $inv['port_of_loading'] ?? null;
            $data['_inv_port_of_discharge'] = $inv['port_of_discharge'] ?? null;
            $data['_inv_origin_country'] = $inv['origin_country'] ?? null;
            $data['_inv_destination_country'] = $inv['destination_country'] ?? null;
            $data['_inv_bl_number'] = $inv['bl_number'] ?? $data['bl_number'] ?? null;
            $data['_inv_etd'] = $inv['etd'] ?? $data['etd'] ?? null;
            $data['_inv_eta'] = $inv['eta'] ?? $data['eta'] ?? null;
            $data['_inv_items'] = $inv['items'] ?? [];
            $data['_inv_subtotal'] = $inv['subtotal'] ?? 0;
            $data['_inv_discount'] = $inv['discount'] ?? 0;
            $data['_inv_freight_charges'] = $inv['freight_charges'] ?? 0;
            $data['_inv_other_charges'] = $inv['other_charges'] ?? 0;
            $data['_inv_grand_total'] = $inv['grand_total'] ?? 0;
            $data['_inv_total_net_weight'] = $inv['total_net_weight'] ?? 0;
            $data['_inv_total_gross_weight'] = $inv['total_gross_weight'] ?? 0;
            $data['_inv_notes'] = $inv['notes'] ?? null;
        } else {
            $data['_inv_pi_id'] = null;
            $data['_inv_invoice_no'] = null;
            $data['_inv_invoice_date'] = null;
            $data['_inv_seller_name'] = null;
            $data['_inv_seller_address'] = null;
            $data['_inv_buyer_name'] = null;
            $data['_inv_buyer_address'] = null;
            $data['_inv_buyer_comm_card_no'] = null;
            $data['_inv_currency'] = null;
            $data['_inv_payment_terms'] = null;
            $data['_inv_transport_mode'] = null;
            $data['_inv_incoterms'] = null;
            $data['_inv_port_of_loading'] = null;
            $data['_inv_port_of_discharge'] = null;
            $data['_inv_origin_country'] = null;
            $data['_inv_destination_country'] = null;
            $data['_inv_bl_number'] = $data['bl_number'] ?? null;
            $data['_inv_etd'] = $data['etd'] ?? null;
            $data['_inv_eta'] = $data['eta'] ?? null;
            $data['_inv_items'] = [];
            $data['_inv_subtotal'] = 0;
            $data['_inv_discount'] = 0;
            $data['_inv_freight_charges'] = 0;
            $data['_inv_other_charges'] = 0;
            $data['_inv_grand_total'] = 0;
            $data['_inv_total_net_weight'] = 0;
            $data['_inv_total_gross_weight'] = 0;
            $data['_inv_notes'] = null;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        foreach (Shipment::statusHistoryColumns() as $column) {
            ShipmentResource::assertStatusTransitionAllowed($record, $column, $data[$column] ?? null);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('saveInvoice')
                ->label(__('resources/shipment/strings.invoice.action_save'))
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('success')
                ->tooltip(__('resources/shipment/strings.invoice.action_save_tooltip'))
                ->disabled(fn () => blank($this->data['_inv_pi_id'] ?? null))
                ->action(function () {
                    ShipmentResource::persistInvoiceToEav($this->data, $this->record);
                    Notification::make()
                        ->title(__('resources/shipment/strings.invoice.saved_notification'))
                        ->success()
                        ->send();
                }),

            Action::make('printInvoice')
                ->label(__('resources/shipment/strings.invoice.action_print'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->tooltip(__('resources/shipment/strings.invoice.action_print_tooltip'))
                ->disabled(fn () => blank($this->data['_inv_pi_id'] ?? null))
                ->action(function () {
                    ShipmentResource::persistInvoiceToEav($this->data, $this->record);

                    if ($this->record?->id) {
                        $this->js('window.open('.json_encode(route('shipments.invoice.pdf', ['shipment' => $this->record->id])).", '_blank')");
                    }
                }),

            Action::make('resetInvoice')
                ->label(__('resources/shipment/strings.invoice.action_reset'))
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->tooltip(__('resources/shipment/strings.invoice.action_reset_tooltip'))
                ->requiresConfirmation()
                ->action(function () {
                    $this->form->fill($this->hydrateInvoiceData($this->data));
                    Notification::make()
                        ->title(__('resources/shipment/strings.invoice.reset_notification'))
                        ->success()
                        ->send();
                }),

            $this->getSaveFormAction(),
            $this->getCancelFormAction(),
        ];
    }
}
