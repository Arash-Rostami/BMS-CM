<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\FormComponents;
use App\Filament\Resources\Operational\CustomResource\Exports\CustomExporter;
use App\Filament\Resources\Operational\CustomResource\Pages\CreateCustom;
use App\Filament\Resources\Operational\CustomResource\Pages\EditCustom;
use App\Filament\Resources\Operational\CustomResource\Pages\ListCustoms;
use App\Filament\Resources\Operational\CustomResource\RelationManagers\RegisteredOrderRelationManager;
use App\Filament\Resources\Operational\CustomResource\RelationManagers\ShipmentRelationManager;
use App\Filament\Resources\Operational\CustomResource\Traits\Filters as CustomFilters;
use App\Filament\Resources\Operational\CustomResource\Traits\Form as CustomForm;
use App\Filament\Resources\Operational\CustomResource\Traits\Infolist as CustomInfolist;
use App\Filament\Resources\Operational\CustomResource\Traits\Table as CustomTable;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\CorrespondenceRelationManager;
use App\Filament\Traits\HasDeskReferenceAction;
use App\Filament\Traits\HasExtraAttributesManagement;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Custom;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomResource extends Resource
{
    use CustomFilters, CustomForm, CustomInfolist, CustomTable, HasDeskReferenceAction, HasExtraAttributesManagement, HasResourcePermissions;

    protected static ?string $model = Custom::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Customs Tabs')
                    ->tabs([
                        Tab::make(__('resources/custom/strings.form.tab_general'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/custom/strings.form.section_general'))
                                            ->schema([
                                                static::getShipmentField(),
                                                static::getRegisteredOrderField(),
                                                static::getContractNoField(),
                                                static::getCustomNoField(),
                                                static::getDeclarationNoField(),
                                                static::getClearanceTypeField(),
                                            ])->columns(2),

                                        Section::make(__('resources/custom/strings.form.section_dates'))
                                            ->schema([
                                                static::getClearanceDateField(),
                                                static::getDocSubmissionDateField(),
                                                static::getTenPercentExitDateField(),
                                                static::getRialReturnDateField(),
                                            ])->columns(3),
                                    ])->columnSpan(['lg' => 2]),
                                Group::make()
                                    ->schema([

                                        Section::make(__('resources/custom/strings.form.section_notes'))
                                            ->schema([
                                                static::getNotesField(),
                                                FormComponents::getAttachmentsField(),
                                            ])
                                            ->collapsible(),
                                    ])->columnSpan(['lg' => 1]),
                            ])->columns(3),

                        Tab::make(__('resources/custom/strings.form.tab_status_financial'))
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make(__('resources/custom/strings.form.section_financial'))
                                    ->schema([
                                        static::getCommitmentBalanceField(),
                                    ])->columnSpan(['lg' => 2]),

                                Section::make(__('resources/custom/strings.form.section_status'))
                                    ->schema([
                                        static::getClearanceStatusField(),
                                        static::getBankGuaranteeStatusField(),
                                        static::getCommitmentStatusField(),
                                    ])->columnSpan(['lg' => 2]),

                            ])->columns(4),
                        static::getExtraAttributesFormTab(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
                'extraAttributes',
                'registeredOrder',
                'clearanceStatus',
                'bankGuaranteeStatus',
                'commitmentStatus',
                'attachments',
                'shipment',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()
            ->with(['shipment', 'clearanceStatus']);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('resources/custom/strings.form.contract_no') => $record->contract_no ?? '—',
            __('resources/custom/strings.form.shipment') => $record->shipment?->shipment_no ?? '—',
            __('resources/custom/strings.form.clearance_status') => $record->clearanceStatus?->localized_name ?? '—',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return '🛃 '.($record->declaration_no ?? $record->custom_no ?? '—');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['declaration_no', 'shipment_no', 'contract_no', 'custom_no'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/custom/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.operational_fourth');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustoms::route('/'),
            'create' => CreateCustom::route('/create'),
            'edit' => EditCustom::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/custom/strings.general.plural_model_label');
    }

    public static function getRelations(): array
    {
        return [
            CorrespondenceRelationManager::class,
            RegisteredOrderRelationManager::class,
            ShipmentRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Details')
                    ->tabs([
                        Tab::make(__('resources/custom/strings.infolist.tab_general'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        static::viewRegisteredOrder(),
                                        static::viewShipment(),
                                        static::viewCustomNo(),
                                        static::viewDeclarationNo(),
                                        static::viewContractNo(),
                                        static::viewClearanceType(),
                                        static::viewCommitmentBalance(),
                                        static::viewNotes(),
                                        static::viewCreator(),
                                        static::viewUpdater(),
                                        static::viewCreatedAt(),
                                        static::viewUpdatedAt(),
                                    ])->columns(3),
                            ]),

                        Tab::make(__('resources/custom/strings.infolist.tab_dates_status'))
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                Section::make('')
                                    ->hiddenLabel()
                                    ->schema([
                                        Grid::make(3)->schema([
                                            static::viewClearanceStatus(),
                                            static::viewBankGuaranteeStatus(),
                                            static::viewCommitmentStatus(),
                                        ]),
                                        Grid::make(3)->schema([
                                            static::viewClearanceDate(),
                                            static::viewDocSubmissionDate(),
                                            static::viewTenPercentExitDate(),
                                        ])->extraAttributes(['style' => 'margin-top: 1.5rem;']),
                                        Grid::make(3)->schema([
                                            static::viewRialReturnDate(),
                                        ])->extraAttributes(['style' => 'margin-top: 1.5rem;']),
                                    ]),
                            ]),

                        Tab::make(__('resources/custom/strings.infolist.tab_documents'))
                            ->icon('heroicon-o-paper-clip')
                            ->schema([
                                Section::make()->schema([static::viewAttachments()]),
                            ])
                            ->label(fn ($record) => tabBadge(
                                __('resources/custom/strings.infolist.tab_documents'),
                                $record?->attachments->count() ?? 0,
                                'info'
                            )),
                        static::getExtraAttributesInfolistTab(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(FilamentTable $table): FilamentTable
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
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    RestoreAction::make(),
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
            ->groups([
                TableGroup::make('clearanceStatus.name')
                    ->label(__('resources/custom/strings.filters.clearance_status'))
                    ->getTitleFromRecordUsing(fn (Model $record) => $record->clearanceStatus?->getLocalizedNameAttribute()),
                TableGroup::make('registeredOrder.ro_number')
                    ->label(__('resources/custom/strings.filters.registered_order'))
                    ->getTitleFromRecordUsing(fn (Model $record) => $record->registeredOrder?->formatted_name_without_date),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
