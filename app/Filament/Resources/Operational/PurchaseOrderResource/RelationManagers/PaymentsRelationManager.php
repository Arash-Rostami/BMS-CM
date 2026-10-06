<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Operational\PaymentResource\Traits\Filters as PaymentFilters;
use App\Filament\Resources\Operational\PaymentResource\Traits\Table as PaymentTable;
use App\Filament\Resources\PaymentResource;
use App\Filament\Traits\HandlesActionExceptions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    use HandlesActionExceptions;
    use PaymentFilters, PaymentTable;

    protected static string $relationship = 'payments';

    protected static ?string $relatedResource = PaymentResource::class;

    public static function getModelLabel(): string
    {
        return __('resources/payment/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/payment/strings.general.plural_model_label');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/payment/strings.general.plural_model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return PaymentResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return TableComponents::gatedEmptyState($table
            ->modifyQueryUsing(fn ($query) => $query->with(PaymentResource::eagerRelations()))
            ->recordTitleAttribute('payment_no')
            ->columns([
                static::showId(),
                static::showPaymentNo(),
                static::showPaymentDate(),
                static::showPayor(),
                static::showPayee(),
                static::showTotalAmount(),
                static::showStatus(),
                static::showCreator(),
            ])
            ->filters([
                static::getStatusFilter(),
                static::getPayorFilter(),
                static::getPayeeFilter(),
                static::getCurrencyFilter(),
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
                    ->url(fn (): string => PaymentResource::getUrl('create', ['purchase_order_id' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->url(fn ($record): string => PaymentResource::getUrl('edit', ['record' => $record])),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    PaymentResource::getExportBulkAction(),
                ]),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('payments.id', 'desc'));
    }
}
