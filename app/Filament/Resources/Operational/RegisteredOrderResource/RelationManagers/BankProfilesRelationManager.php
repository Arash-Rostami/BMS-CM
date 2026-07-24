<?php

namespace App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers;

use App\Filament\Resources\BankProfileResource;
use App\Filament\Resources\Operational\BankProfileResource\Exports\BankProfileExporter;
use App\Filament\Resources\Operational\BankProfileResource\Traits\Filters as BankProfileFilters;
use App\Filament\Resources\Operational\BankProfileResource\Traits\Table as BankProfileTable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BankProfilesRelationManager extends RelationManager
{
    use BankProfileFilters, BankProfileTable;

    protected static string $relationship = 'bankProfiles';

    public static function getModelLabel(): string
    {
        return __('resources/bankProfile/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/bankProfile/strings.general.plural_model_label');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('resources/bankProfile/strings.general.plural_model_label');
    }

    public function infolist(Schema $schema): Schema
    {
        return BankProfileResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('bp_number')
            ->columns([
                static::showId(),
                static::showBpNumber(),
                static::showTargetable(),
                static::showBank(),
                static::showCompany(),
                static::showStatus(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getStatusFilter(),
                static::getBankFilter(),
                static::getCompanyFilter(),
                static::getCreatorFilter(),
                static::getTrashedFilter(),
                static::getCreationDateFilter(),
            ])
            ->filtersFormColumns(3)
            ->headerActions([
                Action::make('create')
                    ->label(__('resources/general/strings.actions.add_record'))
                    ->url(fn (): string => BankProfileResource::getUrl('create', ['registered_order_id' => $this->getOwnerRecord()->getKey()])),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->url(fn ($record): string => BankProfileResource::getUrl('edit', ['record' => $record])), // Correct URL for edit
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(BankProfileExporter::class),
                ]),
            ])
            ->striped()
            ->recordUrl(null)
            ->defaultSort('bank_profiles.id', 'desc');
    }
}
