<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\RelationManagers;

use App\Filament\Resources\Operational\PurchaseOrderResource\Exports\PurchaseOrderExporter;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Filters as PurchaseOrderFilters;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Table as PurchaseOrderTable;
use App\Filament\Resources\PurchaseOrderResource;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
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

    public function infolist(Infolist $infolist): Infolist
    {
        return PurchaseOrderResource::infolist($infolist);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showID(),
                static::showPoNumber(),
                static::showSupplier(),
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
                static::getSupplierFilter(),
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
                Tables\Actions\Action::make('create')
                    ->label(__('resources/proformaInvoice/strings.general.add_record'))
                    ->visible(fn(): bool => in_array($this->getOwnerRecord()->status?->english_name, ['Authorized', 'Conditional']))
                    ->url(fn(): string => PurchaseOrderResource::getUrl('create', ['purchase_request_id' => $this->getOwnerRecord()->getKey()]))
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DetachAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
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
            ->defaultSort('id', 'desc');
    }
}
