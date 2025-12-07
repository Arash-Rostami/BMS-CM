<?php

namespace App\Filament\Resources\Operational\ShipmentResource\Traits;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Storage;

trait Infolist
{
    public static function viewAttachments(): RepeatableEntry
    {
        return RepeatableEntry::make('attachments')
            ->label(__('resources/shipment/strings.form.attachments'))
            ->schema([
                TextEntry::make('path')
                    ->hiddenLabel()
                    ->formatStateUsing(fn(string $state): string => basename($state))
                    ->tooltip(fn($record) => $record->name ?? '')
                    ->icon('heroicon-m-paper-clip')
                    ->color('primary')
                    ->url(fn($record) => Storage::disk('public')->url($record->path), shouldOpenInNewTab: true),
            ])
            ->columns(1);
    }

    public static function viewBlNumber(): TextEntry
    {
        return TextEntry::make('bl_number')
            ->label(__('resources/shipment/strings.form.bl_number'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-document-text')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewBookingNo(): TextEntry
    {
        return TextEntry::make('booking_no')
            ->label(__('resources/shipment/strings.form.booking_no'))
            ->badge()
            ->color('info')
            ->icon('heroicon-m-ticket')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewCarrier(): TextEntry
    {
        return TextEntry::make('carrier.name')
            ->label(__('resources/shipment/strings.form.carrier'))
            ->formatStateUsing(fn($record) => $record->carrier?->getLocalizedNameAttribute())
            ->icon('heroicon-m-truck')
            ->placeholder('-');
    }

    public static function viewContainerNo(): TextEntry
    {
        return TextEntry::make('container_no')
            ->label(__('resources/shipment/strings.form.container_no'))
            ->icon('heroicon-m-cube')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewContainerStatus(): TextEntry
    {
        return TextEntry::make('containerStatus.name')
            ->label(__('resources/shipment/strings.form.container_status'))
            ->formatStateUsing(fn($record) => $record->containerStatus?->getLocalizedNameAttribute())
            ->badge()
            ->placeholder('-');
    }

    public static function viewContainerType(): TextEntry
    {
        return TextEntry::make('container_type')
            ->label(__('resources/shipment/strings.form.container_type'))
            ->formatStateUsing(fn($record) => ($record->container_type ? __('resources/shipment/strings.form.container_types.' . $record->container_type) : '-'))
            ->icon('heroicon-m-cube-transparent')
            ->placeholder('-');
    }

    public static function viewContractNo(): TextEntry
    {
        return TextEntry::make('contract_no')
            ->label(__('resources/shipment/strings.form.contract_no'))
            ->icon('heroicon-m-clipboard-document-check')
            ->copyable()
            ->placeholder('-');
    }

    public static function viewCreatedAt(): TextEntry
    {
        return TextEntry::make('created_at')
            ->label(__('resources/shipment/strings.table.created_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewCreator(): TextEntry
    {
        return TextEntry::make('creator.name')
            ->label(__('resources/shipment/strings.table.created_by'))
            ->icon('heroicon-m-user-circle')
            ->placeholder('-');
    }

    public static function viewCustomsQuantity(): TextEntry
    {
        return TextEntry::make('customs_quantity')
            ->label(__('resources/shipment/strings.form.customs_quantity'))
            ->formatStateUsing(fn($state) => $state !== null ? number_format($state) : '-')
            ->icon('heroicon-m-scale')
            ->placeholder('-');
    }

    public static function viewDocStatus(): TextEntry
    {
        return TextEntry::make('docStatus.name')
            ->label(__('resources/shipment/strings.form.doc_status'))
            ->formatStateUsing(fn($record) => $record->docStatus?->getLocalizedNameAttribute())
            ->badge()
            ->placeholder('-');
    }

    public static function viewDocs(): RepeatableEntry
    {
        $defaults = __('resources/shipment/strings.form.docs_options');

        return RepeatableEntry::make('docs')
            ->label(__('resources/shipment/strings.form.docs'))
            ->schema([
                TextEntry::make('name')
                    ->hiddenLabel()
                    ->formatStateUsing(fn($state) => is_string($state) && array_key_exists($state, $defaults)
                        ? $defaults[$state]
                        : $state
                    )
                    ->icon('heroicon-m-document')
                    ->label(__('resources/shipment/strings.form.doc_name')),
                IconEntry::make('received')
                    ->hiddenLabel()
                    ->boolean()
                    ->trueIcon('heroicon-m-check-circle')
                    ->falseIcon('heroicon-m-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->grid(4);
    }

    public static function viewEta(): TextEntry
    {
        return TextEntry::make('eta')
            ->label(__('resources/shipment/strings.form.eta'))
            ->jalaliDate()
            ->icon('heroicon-m-clock')
            ->placeholder('-');
    }

    public static function viewEtd(): TextEntry
    {
        return TextEntry::make('etd')
            ->label(__('resources/shipment/strings.form.etd'))
            ->jalaliDate()
            ->icon('heroicon-m-clock')
            ->placeholder('-');
    }

    public static function viewExitDate(): TextEntry
    {
        return TextEntry::make('exit_date')
            ->label(__('resources/shipment/strings.form.exit_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }

    public static function viewNotes(): TextEntry
    {
        return TextEntry::make('notes')
            ->label(__('resources/shipment/strings.form.notes'))
            ->markdown()
            ->prose()
            ->columnSpanFull()
            ->placeholder('-');
    }

    public static function viewOperationStatus(): TextEntry
    {
        return TextEntry::make('operationStatus.name')
            ->label(__('resources/shipment/strings.form.operation_status'))
            ->formatStateUsing(fn($record) => $record->operationStatus?->getLocalizedNameAttribute())
            ->badge()
            ->placeholder('-');
    }

    public static function viewPart(): TextEntry
    {
        return TextEntry::make('part')
            ->label(__('resources/shipment/strings.form.part'))
            ->icon('heroicon-m-puzzle-piece')
            ->placeholder('-');
    }

    public static function viewRegisteredOrder(): TextEntry
    {
        return TextEntry::make('registeredOrder.formatted_name')
            ->label(__('resources/shipment/strings.form.registered_order'))
            ->wrap()
            ->html()
            ->columnSpanFull()
            ->listWithLineBreaks()
            ->icon('heroicon-m-clipboard-document-list')
            ->copyable();
    }

    public static function viewRemittanceAmount(): TextEntry
    {
        return TextEntry::make('remittance_amount')
            ->label(__('resources/shipment/strings.form.remittance_amount'))
            ->formatStateUsing(fn($state) => $state ? delimiter($state) : '-')
            ->color('success')
            ->placeholder('-');
    }

    public static function viewShipmentNo(): TextEntry
    {
        return TextEntry::make('shipment_no')
            ->label(__('resources/shipment/strings.form.shipment_no'))
            ->badge()
            ->color('primary')
            ->icon('heroicon-m-hashtag')
            ->copyable();
    }

    public static function viewShippedQuantity(): TextEntry
    {
        return TextEntry::make('shipped_quantity')
            ->label(__('resources/shipment/strings.form.shipped_quantity'))
            ->formatStateUsing(fn($state) => $state !== null ? number_format($state) : '-')
            ->icon('heroicon-m-cube')
            ->placeholder('-');
    }

    public static function viewStatus(): TextEntry
    {
        return TextEntry::make('status.name')
            ->label(__('resources/shipment/strings.form.status'))
            ->formatStateUsing(fn($record) => $record->status?->getLocalizedNameAttribute())
            ->badge()
            ->color('primary');
    }

    public static function viewTrackingStatus(): TextEntry
    {
        return TextEntry::make('trackingStatus.name')
            ->label(__('resources/shipment/strings.form.shipment_status'))
            ->formatStateUsing(fn($record) => $record->trackingStatus?->getLocalizedNameAttribute())
            ->badge()
            ->placeholder('-');
    }

    public static function viewUpdatedAt(): TextEntry
    {
        return TextEntry::make('updated_at')
            ->label(__('resources/shipment/strings.table.updated_at'))
            ->dateTime('M Y | D: H:i:s')
            ->color('gray')
            ->placeholder('-');
    }

    public static function viewUpdater(): TextEntry
    {
        return TextEntry::make('updater.name')
            ->label(__('resources/shipment/strings.table.updated_by'))
            ->icon('heroicon-m-pencil-square')
            ->placeholder('-');
    }

    public static function viewWarehouseDate(): TextEntry
    {
        return TextEntry::make('warehouse_date')
            ->label(__('resources/shipment/strings.form.warehouse_date'))
            ->jalaliDate()
            ->icon('heroicon-m-calendar-days')
            ->placeholder('-');
    }
}
