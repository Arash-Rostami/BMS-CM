<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers;

use App\Filament\Resources\Operational\ProformaInvoiceResource\Exports\ProformaInvoiceExporter;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Filters as ProformaInvoiceFilters;
use App\Filament\Resources\Operational\ProformaInvoiceResource\Traits\Table as ProformaInvoiceTable;
use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Status;
use App\Filament\Resources\ProformaInvoiceResource;
use Filament\Infolists\Infolist;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProformaInvoicesRelationManager extends RelationManager
{
    use ProformaInvoiceTable;
    use ProformaInvoiceFilters;

    protected static string $relationship = 'proformaInvoices';

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

    public function infolist(Infolist $infolist): Infolist
    {
        return ProformaInvoiceResource::infolist($infolist);
    }

    public function table(Table $table): Table
    {
        return $table
            ->emptyStateIcon('heroicon-o-bookmark')
            ->recordTitleAttribute('invoice_no')
            ->columns([
                static::showID(),
                static::showInvoiceNo(),
                static::showSellerCompany(),
                static::showConsigneeCompany(),
                static::showTotalAmount(),
                static::showInvoiceDate(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getSellerCompanyFilter(),
                static::getConsigneeCompanyFilter(),
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
                Tables\Actions\Action::make('create')
                    ->label(__('resources/proformaInvoice/strings.general.add_record'))
                    ->visible(fn(): bool => in_array($this->getOwnerRecord()->status?->english_name, ['Approved']))
                    ->url(fn(): string => ProformaInvoiceResource::getUrl('create', ['purchase_order_id' => $this->getOwnerRecord()->getKey()]))
            ])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make()
                        ->url(fn($record): string => ProformaInvoiceResource::getUrl('edit', ['record' => $record])),
                    Tables\Actions\DetachAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(ProformaInvoiceExporter::class),
                ]),
            ])
            ->groups([
                Group::make('sellerCompany.name')
                    ->label(__('resources/proformaInvoice/strings.filters.seller_company'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'sellerCompany')),
                Group::make('consigneeCompany.name')
                    ->label(__('resources/proformaInvoice/strings.filters.consignee_company'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'consigneeCompany')),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('proforma_invoices.id', 'desc');
    }
}
