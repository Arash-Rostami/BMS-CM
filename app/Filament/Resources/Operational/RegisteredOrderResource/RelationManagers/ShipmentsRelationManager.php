<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers;

use App\Filament\Resources\Operational\ShipmentResource\Exports\ShipmentExporter;
use App\Filament\Resources\Operational\ShipmentResource\Traits\Filters as ShipmentFilters;
use App\Filament\Resources\Operational\ShipmentResource\Traits\Table as ShipmentTable;
use App\Filament\Resources\ShipmentResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ShipmentsRelationManager extends RelationManager
{
    use ShipmentTable, ShipmentFilters;

    protected static string $relationship = 'shipments';

    public static function getModelLabel(): string
    {
        return __('resources/shipment/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/shipment/strings.general.plural_model_label');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/shipment/strings.general.plural_model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return ShipmentResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showId(),
                static::showShipmentNo(),
                static::showCarrier(),
                static::showPart(),
                static::showBlNumber(),
                static::showStatus(),
                static::showTrackingStatus(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getCarrierFilter(),
                static::getStatusFilter(),
                static::getTrackingStatusFilter(),
                static::getContainerStatusFilter(),
                static::getDocStatusFilter(),
                static::getOperationStatusFilter(),
                static::getEtaFilter(),
                static::getCreationDateFilter(),
                static::getTrashedFilter(),
            ])
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('create')
                    ->label(__('resources/shipment/strings.general.add_record'))
                    ->url(fn(): string => ShipmentResource::getUrl('create', ['registered_order_id' => $this->getOwnerRecord()->getKey()]))
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->url(fn($record): string => ShipmentResource::getUrl('edit', ['record' => $record])),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(ShipmentExporter::class),
                ]),
            ])
            ->groups([
                TableGroup::make('carrier.name')
                    ->label(__('resources/shipment/strings.form.carrier'))
                    ->getTitleFromRecordUsing(fn(Model $record) => $record->carrier?->getLocalizedNameAttribute()),
                TableGroup::make('status.name')
                    ->label(__('resources/shipment/strings.form.status'))
                    ->getTitleFromRecordUsing(fn(Model $record) => $record->status?->getLocalizedNameAttribute()),
                TableGroup::make('trackingStatus.name')
                    ->label(__('resources/shipment/strings.form.shipment_status'))
                    ->getTitleFromRecordUsing(fn(Model $record) => $record->trackingStatus?->getLocalizedNameAttribute()),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('id', 'desc');
    }
}
