<?php

namespace App\Filament\Resources\Operational\ShipmentResource\RelationManagers;

use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\CustomResource\Exports\CustomExporter;
use App\Filament\Resources\Operational\CustomResource\Traits\Filters as CustomFilters;
use App\Filament\Resources\Operational\CustomResource\Traits\Table as CustomTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Model;

class CustomsRelationManager extends RelationManager
{
    use CustomFilters, CustomTable;

    protected static string $relationship = 'customs';

    public static function getModelLabel(): string
    {
        return __('resources/custom/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/custom/strings.general.plural_model_label');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/custom/strings.general.plural_model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return CustomResource::infolist($schema);
    }

    public function table(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                static::showShipment(),
                static::showCustomNo(),
                static::showDeclarationNo(),
                static::showContractNo(),
                static::showClearanceType(),
                static::showClearanceStatus(),
                static::showClearanceDate(),
                static::showBankGuaranteeStatus(),
                static::showCommitmentStatus(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getContractNoFilter(),
                static::getRegisteredOrderFilter(),
                static::getClearanceStatusFilter(),
                static::getCommitmentStatusFilter(),
                static::getBankGuaranteeStatusFilter(),
                static::getClearanceTypeFilter(),
                static::getCreationDateFilter(),
                static::getTrashedFilter(),
            ])
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('create')
                    ->label(__('resources/general/strings.actions.add_record'))
                    ->url(fn (): string => CustomResource::getUrl('create', ['shipment_id' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->url(fn ($record) => CustomResource::getUrl('edit', ['record' => $record])),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(CustomExporter::class),
                ]),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
