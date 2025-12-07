<?php

namespace App\Filament\Resources\Operational\CustomResource\RelationManagers;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Operational\RegisteredOrderResource\Exports\RegisteredOrderExporter;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Filters as RegisteredOrderFilters;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Table as RegisteredOrderTable;
use App\Filament\Resources\RegisteredOrderResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Model;

class RegisteredOrderRelationManager extends RelationManager
{
    use RegisteredOrderTable, RegisteredOrderFilters;

    protected static string $relationship = 'registeredOrder';

    protected bool $canAssociate = false;
    protected bool $canCreate = false;
    protected bool $canDelete = false;
    protected bool $canDissociate = false;
    protected bool $canEdit = false;

    public static function getModelLabel(): string
    {
        return __('resources/registeredOrder/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/registeredOrder/strings.general.plural_model_label');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/registeredOrder/strings.general.model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return RegisteredOrderResource::infolist($schema);
    }

    public function table(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                static::showSource(),
                static::showId(),
                TableComponents::showPurchaseRequests(),
                TableComponents::showProformaInvoices(),
                TableComponents::showPurchaseOrders(),
                static::showRoNumber(),
                static::showCtNumber(),
                static::showOfficialRegistrationNo(),
                static::showSeller(),
                static::showBuyer(),
                static::showStatus(),
                static::showOrderDate(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
                static::showPurchaseOrdersCount(),
            ])
            ->filters([
                static::getSellerFilter(),
                static::getBuyerFilter(),
                static::getStatusFilter(),
                static::getCreatorFilter(),
                static::getTrashedFilter(),
                static::getCreationDateFilter(),
            ])
            ->filtersFormColumns(3)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->url(fn($record) => RegisteredOrderResource::getUrl('edit', ['record' => $record])),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkActionGroup::make([
                        ExportBulkAction::make()
                            ->exporter(RegisteredOrderExporter::class),
                    ]),
                ]),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
