<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers;

use App\Filament\Resources\CustomResource;
use App\Filament\Resources\Operational\CustomResource\Exports\CustomExporter;
use App\Filament\Resources\Operational\CustomResource\Traits\Filters as CustomFilters;
use App\Filament\Resources\Operational\CustomResource\Traits\Table as CustomTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CustomsRelationManager extends RelationManager
{
    use CustomTable, CustomFilters;

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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('declaration_no')
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
                static::getClearanceStatusFilter(),
                static::getCommitmentStatusFilter(),
                static::getBankGuaranteeStatusFilter(),
                static::getClearanceTypeFilter(),
                static::getCreationDateFilter(),
                static::getTrashedFilter(),
            ])
            ->headerActions([
                Action::make('create')
                    ->label(__('resources/custom/strings.general.add_record'))
                    ->modalWidth('md')
                    ->schema([
                        Select::make('shipment_id')
                            ->label(__('resources/custom/strings.form.shipment'))
                            ->relationship(
                                name: 'shipment',
                                titleAttribute: 'shipment_no',
                                modifyQueryUsing: fn($query) => $query->where('registered_order_id', $this->getOwnerRecord()->getKey())
                            )
                            ->getOptionLabelFromRecordUsing(fn(Model $record) => $record->formatted_name ?? $record->shipment_no)
                            ->required()
                            ->searchable()
                            ->preload()
                    ])
                    ->modalSubmitActionLabel('✔')
                    ->modalCancelActionLabel('✘')
                    ->action(fn(array $data) => redirect(CustomResource::getUrl('create', ['shipment_id' => $data['shipment_id']])))
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->url(fn($record) => CustomResource::getUrl('edit', ['record' => $record])),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(CustomExporter::class),
                ]),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('id', 'desc');
    }
}
