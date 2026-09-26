<?php

namespace App\Filament\Resources\Operational\PurchaseRequestResource\RelationManagers;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Filters as ProformaInvoiceFilters;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Table as ProformaInvoiceTable;
use App\Filament\Resources\ProformaInvoiceResource;
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
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProformaInvoicesRelationManager extends RelationManager
{
    use HandlesActionExceptions;
    use ProformaInvoiceFilters;
    use ProformaInvoiceTable;

    protected static string $relationship = 'proformaInvoices';

    protected static ?string $relatedResource = ProformaInvoiceResource::class;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/proformaInvoice/strings.general.plural_model_label');
    }

    public static function getModelLabel(): string
    {
        return __('resources/proformaInvoice/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/proformaInvoice/strings.general.plural_model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return ProformaInvoiceResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return TableComponents::gatedEmptyState($table
            ->modifyQueryUsing(fn ($query) => $query->with(ProformaInvoiceResource::eagerRelations()))
            ->recordTitleAttribute('invoice_no')
            ->columns([
                static::showID(),
                static::showInvoiceNo(),
                static::showSellerCompany(),
                static::showBuyerCompany(),
                static::showTotalAmount(),
                static::showInvoiceDate(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getSellerCompanyFilter(),
                static::getBuyerCompanyFilter(),
                static::getDeliveryTermsFilter(),
                static::getTransportModeFilter(),
                static::getMainCurrencyFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
                static::getTrashedFilter(),
                static::getInvoiceDateFilter(),
            ])
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('create')
                    ->label(__('resources/general/strings.actions.add_record'))
                    ->tooltip(__('resources/general/strings.actions.add_record_tooltip'))
                    ->visible(fn (): bool => in_array($this->getOwnerRecord()->status?->english_name, ['Authorized', 'Conditional']))
                    ->url(fn (): string => ProformaInvoiceResource::getUrl('create', ['purchase_request_id' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->url(fn ($record): string => ProformaInvoiceResource::getUrl('edit', ['record' => $record])),
                    DetachAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ProformaInvoiceResource::getExportBulkAction(),
                ]),
            ])
            ->groups([
                Group::make('sellerCompany.name')
                    ->label(__('resources/proformaInvoice/strings.filters.seller_company'))
                    ->getTitleFromRecordUsing(fn (Model $record): ?string => getLocalizedName($record, 'sellerCompany')),
                Group::make('buyerCompany.name')
                    ->label(__('resources/proformaInvoice/strings.filters.buyer_company'))
                    ->getTitleFromRecordUsing(fn (Model $record): ?string => getLocalizedName($record, 'buyerCompany')),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('proforma_invoices.id', 'desc'));
    }
}
