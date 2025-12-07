<?php

namespace App\Filament\Resources\Operational\CustomResource\RelationManagers;

use App\Filament\Resources\Operational\ShipmentResource\Exports\ShipmentExporter;
use App\Filament\Resources\Operational\ShipmentResource\Traits\Filters as ShipmentFilters;
use App\Filament\Resources\Operational\ShipmentResource\Traits\Table as ShipmentTable;
use App\Filament\Resources\ShipmentResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Model;

class ShipmentRelationManager extends RelationManager
{
    use ShipmentTable, ShipmentFilters;

    protected static string $relationship = 'shipment';

    protected bool $canAssociate = false;
    protected bool $canCreate = false;
    protected bool $canDelete = false;
    protected bool $canDissociate = false;
    protected bool $canEdit = false;

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
        return __('resources/shipment/strings.general.model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return ShipmentResource::infolist($schema);
    }

    public function table(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                static::showId(),
                static::showRegisteredOrder(),
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
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->url(fn($record) => ShipmentResource::getUrl('edit', ['record' => $record])),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(ShipmentExporter::class),
                ]),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
