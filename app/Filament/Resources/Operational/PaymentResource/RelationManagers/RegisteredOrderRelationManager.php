<?php

namespace App\Filament\Resources\Operational\PaymentResource\RelationManagers;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Filters as RegisteredOrderFilters;
use App\Filament\Resources\Operational\RegisteredOrderResource\Traits\Table as RegisteredOrderTable;
use App\Filament\Resources\RegisteredOrderResource;
use App\Filament\Traits\HandlesActionExceptions;
use App\Models\RegisteredOrder;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class RegisteredOrderRelationManager extends RelationManager
{
    use HandlesActionExceptions;
    use RegisteredOrderFilters, RegisteredOrderTable;

    protected static string $relationship = 'registeredOrder';

    protected static ?string $relatedResource = RegisteredOrderResource::class;

    public static function getModelLabel(): string
    {
        return __('resources/registeredOrder/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/registeredOrder/strings.general.plural_model_label');
    }

    public function getRelationship(): Relation|Builder
    {
        $payment = $this->getOwnerRecord();

        return $payment->targetable_type === RegisteredOrder::class
            ? RegisteredOrder::query()->where('id', $payment->targetable_id)
            : RegisteredOrder::query()->whereRaw('1 = 0');
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
        return TableComponents::emptyState($table
            ->query(fn () => $this->getRelationship()->with(RegisteredOrderResource::eagerRelations())->withCount('purchaseOrders')->withCount('proformaInvoices')->withCount('purchaseRequests'))
            ->recordTitleAttribute('ro_number')
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
                        ->url(fn ($record) => RegisteredOrderResource::getUrl('edit', ['record' => $record])),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    RegisteredOrderResource::getExportBulkAction(),
                ]),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc'));
    }
}
