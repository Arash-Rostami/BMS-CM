<?php

namespace App\Filament\Resources\Operational\PaymentResource\RelationManagers;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Operational\PurchaseOrderResource\Exports\PurchaseOrderExporter;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Filters as PurchaseOrderFilters;
use App\Filament\Resources\Operational\PurchaseOrderResource\Traits\Table as PurchaseOrderTable;
use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class PurchaseOrderRelationManager extends RelationManager
{
    use PurchaseOrderFilters, PurchaseOrderTable;

    protected static string $relationship = 'purchaseOrder';

    protected bool $canAssociate = false;

    protected bool $canCreate = false;

    protected bool $canDelete = false;

    protected bool $canDissociate = false;

    protected bool $canEdit = false;

    public static function getModelLabel(): string
    {
        return __('resources/purchaseOrder/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/purchaseOrder/strings.general.plural_model_label');
    }

    public function getRelationship(): Relation|Builder
    {
        $payment = $this->getOwnerRecord();

        return $payment->targetable_type === PurchaseOrder::class
            ? PurchaseOrder::query()->where('id', $payment->targetable_id)
            : PurchaseOrder::query()->whereRaw('1 = 0');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/purchaseOrder/strings.general.model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return PurchaseOrderResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getRelationship())
            ->recordTitleAttribute('po_number')
            ->columns([
                static::showSource(),
                static::showID(),
                TableComponents::showPurchaseRequests(),
                TableComponents::showProformaInvoices(),
                TableComponents::showRegisteredOrders(),
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
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->url(fn ($record) => PurchaseOrderResource::getUrl('edit', ['record' => $record])),

                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PurchaseOrderExporter::class),
                ]),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('purchase_orders.id', 'desc');
    }
}
