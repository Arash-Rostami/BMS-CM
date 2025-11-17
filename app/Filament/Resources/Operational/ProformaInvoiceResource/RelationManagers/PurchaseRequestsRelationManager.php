<?php

namespace App\Filament\Resources\Operational\ProformaInvoiceResource\RelationManagers;

use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Status;
use App\Filament\Resources\Operational\PurchaseRequestResource\Exports\PurchaseRequestExporter;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Filters as PurchaseRequestFilters;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Table as PurchaseRequestTable;
use App\Filament\Resources\PurchaseRequestResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;


class PurchaseRequestsRelationManager extends RelationManager
{
    use  PurchaseRequestTable, PurchaseRequestFilters;

    protected static string $relationship = 'purchaseRequests';

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
        return $table
            ->emptyStateIcon('heroicon-o-bookmark')
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
                        ->exporter(PurchaseRequestExporter::class),
                ]),
            ])
            ->groups([
                Group::make('requester.name')
                    ->label(__('resources/purchaseRequest/strings.filters.requester')),
                Group::make('department.name')
                    ->label(__('resources/purchaseRequest/strings.filters.department'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => getLocalizedName($record, 'department')),
                Group::make('status.english_name')
                    ->label(__('resources/purchaseRequest/strings.filters.status'))
                    ->getTitleFromRecordUsing(fn($record): ?string => Status::tryFrom($record->status?->english_name)?->getLabel() ?? $record->status?->name),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('purchase_requests.id', 'desc');
    }
}
