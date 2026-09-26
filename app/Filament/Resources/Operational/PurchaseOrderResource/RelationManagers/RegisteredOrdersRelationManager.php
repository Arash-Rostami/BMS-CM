<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Filters as RegisteredOrderFilters;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Table as RegisteredOrderTable;
use App\Filament\Resources\RegisteredOrderResource;
use App\Filament\Traits\HandlesActionExceptions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RegisteredOrdersRelationManager extends RelationManager
{
    use HandlesActionExceptions;
    use RegisteredOrderFilters, RegisteredOrderTable;

    protected static string $relationship = 'registeredOrders';

    protected static ?string $relatedResource = RegisteredOrderResource::class;

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
        return TableComponents::gatedEmptyState($table
            ->modifyQueryUsing(fn ($query) => $query->with(RegisteredOrderResource::eagerRelations()))
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
                    ->label(__('resources/general/strings.actions.add_record'))
                    ->tooltip(__('resources/general/strings.actions.add_record_tooltip'))
                    ->visible(fn (): bool => in_array($this->getOwnerRecord()->status?->english_name, ['Approved']))
                    ->url(fn (): string => RegisteredOrderResource::getUrl('create', ['purchase_order_id' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DetachAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    RegisteredOrderResource::getExportBulkAction(),
                ]),
            ])
            ->groups([
                TableGroup::make('buyerCompany.name')
                    ->label(__('resources/registeredOrder/strings.filters.buyer'))
                    ->getTitleFromRecordUsing(fn (Model $record): ?string => getLocalizedName($record, 'buyerCompany')),
                TableGroup::make('sellerCompanyExclusive.name')
                    ->label(__('resources/registeredOrder/strings.filters.seller'))
                    ->getTitleFromRecordUsing(fn (Model $record): ?string => getLocalizedName($record, 'sellerCompanyExclusive')),
                TableGroup::make('supplierCompanyExclusive.name')
                    ->label(__('resources/registeredOrder/strings.filters.supplier'))
                    ->getTitleFromRecordUsing(fn (Model $record): ?string => getLocalizedName($record, 'supplierCompanyExclusive')),
                TableGroup::make('manufacturerCompanyExclusive.name')
                    ->label(__('resources/registeredOrder/strings.filters.manufacturer'))
                    ->getTitleFromRecordUsing(fn (Model $record): ?string => getLocalizedName($record, 'manufacturerCompanyExclusive')),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('registered_orders.id', 'desc'));
    }
}
