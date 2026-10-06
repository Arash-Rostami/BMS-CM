<?php

namespace App\Filament\Resources\Operational\PurchaseOrderResource\RelationManagers;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Status;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Filters as PurchaseRequestFilters;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Table as PurchaseRequestTable;
use App\Filament\Resources\PurchaseRequestResource;
use App\Filament\Traits\HandlesActionExceptions;
use Filament\Actions\ActionGroup;
use Filament\Actions\AttachAction;
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

class PurchaseRequestsRelationManager extends RelationManager
{
    use HandlesActionExceptions;
    use PurchaseRequestFilters, PurchaseRequestTable;

    protected static string $relationship = 'purchaseRequests';

    protected static ?string $relatedResource = PurchaseRequestResource::class;

    public static function getModelLabel(): string
    {
        return __('resources/purchaseRequest/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/purchaseRequest/strings.general.plural_model_label');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/purchaseRequest/strings.general.plural_model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return PurchaseRequestResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->modifyQueryUsing(fn ($query) => $query->with(PurchaseRequestResource::eagerRelations())->withCount('proformaInvoices'))
            ->recordTitleAttribute('formatted_name')
            ->columns([
                static::showID(),
                static::showRequester(),
                static::showDepartment(),
                static::showCostCenter(),
                static::showUrgency(),
                static::showStatus(),
                static::showTotalCost(),
                static::showRequiredByDate(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
                static::showProformaInvoiceCount(),
            ])
            ->filters([
                static::getDepartmentFilter(),
                static::getStatusFilter(),
                static::getUrgencyFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
                static::getTrashedFilter(),
                static::getCreationDateFilter(),
            ])
            ->filtersFormColumns(2)
            ->headerActions([
                AttachAction::make()
                    ->label(__('resources/general/strings.actions.attach_record'))
                    ->tooltip(__('resources/general/strings.actions.attach_record_tooltip'))
                    ->color('primary')
                    ->visible(fn (): bool => in_array($this->getOwnerRecord()->status?->english_name, ['Approved'])),
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
                    PurchaseRequestResource::getExportBulkAction(),
                ]),
            ])
            ->groups([
                Group::make('requester.name')
                    ->label(__('resources/purchaseRequest/strings.filters.requester')),
                Group::make('department.name')
                    ->label(__('resources/purchaseRequest/strings.filters.department'))
                    ->getTitleFromRecordUsing(fn (Model $record): ?string => getLocalizedName($record, 'department')),
                Group::make('status.english_name')
                    ->label(__('resources/purchaseRequest/strings.filters.status'))
                    ->getTitleFromRecordUsing(fn ($record): ?string => Status::tryFrom($record->status?->english_name)?->getLabel() ?? $record->status?->name),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('purchase_requests.id', 'desc'));
    }
}
