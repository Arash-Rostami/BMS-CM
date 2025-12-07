<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\RelationManagers;

use App\Filament\Resources\Operational\RegisteredOrderResource\Exports\RegisteredOrderExporter;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Filters as RegisteredOrderFilters;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Table as RegisteredOrderTable;
use App\Filament\Resources\RegisteredOrderResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RegisteredOrderRelationManager extends RelationManager
{
    use RegisteredOrderTable, RegisteredOrderFilters;

    protected static string $relationship = 'RegisteredOrders';

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
        return __('resources/registeredOrder/strings.general.plural_model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return RegisteredOrderResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showId(),
                static::showRoNumber(),
                static::showSeller(),
                static::showBuyer(),
                static::showStatus(),
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
                static::getCreatorFilter(),
                static::getTrashedFilter(),
                static::getCreationDateFilter(),
            ])
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('create')
                    ->label(__('resources/registeredOrder/strings.general.add_record'))
                    ->url(fn(): string => RegisteredOrderResource::getUrl('create', ['proforma_invoice_id' => $this->getOwnerRecord()->getKey()]))
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->url(fn($record) => RegisteredOrderResource::getUrl('edit', ['record' => $record])),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                        RestoreBulkAction::make(),
                        ExportBulkAction::make()
                            ->exporter(RegisteredOrderExporter::class),
                    ]),
                ]),
            ])
            ->groups([
                TableGroup::make('buyerCompany.name')
                    ->label(__('resources/registeredOrder/strings.filters.buyer'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'buyerCompany')),
                TableGroup::make('sellerCompanyExclusive.name')
                    ->label(__('resources/registeredOrder/strings.filters.seller'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'sellerCompanyExclusive')),
                TableGroup::make('supplierCompanyExclusive.name')
                    ->label(__('resources/registeredOrder/strings.filters.supplier'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'supplierCompanyExclusive')),
                TableGroup::make('manufacturerCompanyExclusive.name')
                    ->label(__('resources/registeredOrder/strings.filters.manufacturer'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'manufacturerCompanyExclusive')),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('registered_orders.id', 'desc');
    }
}
