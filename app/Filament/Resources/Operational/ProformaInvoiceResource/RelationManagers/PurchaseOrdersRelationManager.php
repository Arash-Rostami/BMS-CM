<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\RelationManagers;

use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\RegisteredOrderResource;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ExportBulkAction;
use App\Filament\Resources\Operational\PurchaseOrderResource\Exports\PurchaseOrderExporter;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Filters as PurchaseOrderFilters;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Table as PurchaseOrderTable;
use App\Filament\Resources\PurchaseRequestResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrdersRelationManager extends RelationManager
{
    use PurchaseOrderTable, PurchaseOrderFilters;

    protected static string $relationship = 'purchaseOrders';

    public static function getModelLabel(): string
    {
        return __('resources/purchaseOrder/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/purchaseOrder/strings.general.plural_model_label');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/purchaseOrder/strings.general.plural_model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return PurchaseOrderResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('formatted_name')
            ->columns([
                static::showID(),
                static::showPoNumber(),
                static::showSeller(),
                static::showBuyer(),
                static::showStatus(),
                static::showTotalAmount(),
                static::showOrderDate(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getSellerFilter(),
                static::getBuyerFilter(),
                static::getStatusFilter(),
                static::getIncotermsFilter(),
                static::getCurrencyFilter(),
                static::getCreatorFilter(),
                static::getTrashedFilter(),
                static::getCreationDateFilter(),
            ])
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('create')
                    ->label(__('resources/purchaseOrder/strings.general.add_record'))
                    ->url(fn(): string => PurchaseOrderResource::getUrl('create', ['proforma_invoice_id' => $this->getOwnerRecord()->getKey()]))
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DetachAction::make(),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(PurchaseOrderExporter::class),
                ]),
            ])
            ->groups([
                Group::make('buyer.name')
                    ->label(__('resources/purchaseOrder/strings.filters.buyer'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'buyer')),
                Group::make('supplier.name')
                    ->label(__('resources/purchaseOrder/strings.filters.supplier'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'supplier')),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('purchase_orders.id', 'desc');
    }
}
