<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\FormComponents;
use App\Filament\Resources\Operational\RegisteredOrderResource\RelationManagers\CorrespondenceRelationManager;
use App\Filament\Resources\Operational\ShipmentResource\Exports\ShipmentExporter;
use App\Filament\Resources\Operational\ShipmentResource\Pages\CreateShipment;
use App\Filament\Resources\Operational\ShipmentResource\Pages\EditShipment;
use App\Filament\Resources\Operational\ShipmentResource\Pages\ListShipments;
use App\Filament\Resources\Operational\ShipmentResource\RelationManagers\CustomsRelationManager;
use App\Filament\Resources\Operational\ShipmentResource\RelationManagers\RegisteredOrderRelationManager;
use App\Filament\Resources\Operational\ShipmentResource\Traits\Filters as ShipmentFilters;
use App\Filament\Resources\Operational\ShipmentResource\Traits\Form as ShipmentForm;
use App\Filament\Resources\Operational\ShipmentResource\Traits\Infolist as ShipmentInfolist;
use App\Filament\Resources\Operational\ShipmentResource\Traits\Table as ShipmentTable;
use App\Filament\Traits\HasExtraAttributesManagement;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Shipment;
use App\Services\SmartCacheManager;
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


class ShipmentResource extends Resource
{
    use ShipmentForm, ShipmentTable, ShipmentFilters, ShipmentInfolist, HasResourcePermissions, HasExtraAttributesManagement;

    protected static ?string $model = Shipment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Shipment Tabs')
                    ->tabs([
                        Tab::make(__('resources/shipment/strings.form.tabs.general'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/shipment/strings.form.section_general'))
                                            ->schema([
                                                static::getRegisteredOrderField(),
                                                static::getContractNoField(),
                                                static::getShipmentNoField(),
                                                static::getPartField(),
                                                static::getCompanyField(),
                                            ])->columns(2),
                                        Section::make(__('resources/shipment/strings.form.docs'))
                                            ->schema([
                                                static::getSmartTracerField(),
                                                static::getDocsField(),
                                            ])
                                            ->collapsible()
                                            ->collapsed(fn($operation) => $operation !== 'edit')
                                            ->columns(2),
                                    ])->columnSpan(['lg' => 2]),

                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/shipment/strings.form.section_docs_notes'))
                                            ->schema([
                                                static::getNotesField(),
                                                FormComponents::getAttachmentsField(),
                                            ]),
                                    ])->columnSpan(['lg' => 1]),
                            ])->columns(3),

                        Tab::make(__('resources/shipment/strings.form.tabs.logistics'))
                            ->icon('heroicon-o-map')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/shipment/strings.form.section_dates'))
                                            ->schema([
                                                static::getWarehouseDateField(),
                                                static::getExitDateField(),
                                                static::getEtdField(),
                                                static::getEtaField(),
                                            ])
                                            ->columns(4),

                                        Section::make(__('resources/shipment/strings.form.section_logistics'))
                                            ->schema([
                                                static::getContainerNoField(),
                                                static::getContainerTypeField(),
                                                static::getBlNumberField(),
                                                static::getBookingNoField(),
                                            ])->columns(2),

                                        Section::make(__('resources/shipment/strings.form.section_amounts'))
                                            ->schema([
                                                static::getRemittanceAmountField(),
                                                static::getCustomsQuantityField(),
                                                static::getShippedQuantityField(),
                                            ])->columns(3),
                                    ])->columnSpan(['lg' => 2]),

                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/shipment/strings.form.section_status'))
                                            ->schema([
                                                static::getStatusField(),
                                                static::getShipmentStatusField(),
                                                static::getOperationStatusField(),
                                                static::getContainerStatusField(),
                                                static::getDocStatusField(),
                                            ])->columns(1),
                                    ])->columnSpan(['lg' => 1]),
                            ])->columns(3),
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
                'carrier',
                'status',
                'containerStatus',
                'operationStatus',
                'trackingStatus',
                'docStatus',
                'attachments',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $shipment = $record->shipment_no ?? $record->bl_number ?? '—';

        return "🚢 {$shipment} (📆 {$date})";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['shipment_no', 'bl_number', 'booking_no', 'container_no'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/shipment/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'Shipment',
            ['user_id' => auth()->id(), 'type' => 'total_count'],
            150,
            fn() => static::getModel()::count()
        );

        return $count > 0 ? (string)$count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }


    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.operational_fourth');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShipments::route('/'),
            'create' => CreateShipment::route('/create'),
            'edit' => EditShipment::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/shipment/strings.general.plural_model_label');
    }

    public static function getRelations(): array
    {
        return [
            CorrespondenceRelationManager::class,
            RegisteredOrderRelationManager::class,
            CustomsRelationManager::class
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Details')
                    ->tabs([
                        Tab::make(__('resources/shipment/strings.infolist.tab_general'))
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        static::viewRegisteredOrder(),
                                        static::viewContractNo(),
                                        static::viewShipmentNo(),
                                        static::viewPart(),
                                        static::viewCarrier(),
                                        static::viewRemittanceAmount(),
                                        static::viewShippedQuantity(),
                                        static::viewCustomsQuantity(),
                                        static::viewStatus(),
                                        static::viewNotes(),
                                        static::viewCreator(),
                                        static::viewUpdater(),
                                        static::viewCreatedAt(),
                                        static::viewUpdatedAt(),
                                    ])->columns(3),
                            ]),
                        Tab::make(__('resources/shipment/strings.infolist.tab_logistics'))
                            ->icon('heroicon-o-map')
                            ->schema([
                                Section::make()
                                    ->hiddenLabel()
                                    ->schema([
                                        Grid::make(4)
                                            ->schema([
                                                static::viewBlNumber(),
                                                static::viewBookingNo(),
                                                static::viewContainerNo(),
                                                static::viewContainerType(),
                                            ]),
                                        Grid::make(4)
                                            ->schema([
                                                static::viewWarehouseDate(),
                                                static::viewExitDate(),
                                                static::viewEtd(),
                                                static::viewEta(),
                                            ])
                                            ->extraAttributes(['style' => 'margin-top: 2rem;']),
                                        Grid::make(4)
                                            ->schema([
                                                static::viewTrackingStatus(),
                                                static::viewOperationStatus(),
                                                static::viewContainerStatus(),
                                                static::viewDocStatus(),
                                            ])
                                            ->extraAttributes(['style' => 'margin-top: 2rem;']),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make(__('resources/shipment/strings.infolist.tab_docs'))
                            ->label(fn($record) => tabBadge(
                                __('resources/shipment/strings.infolist.tab_docs'),
                                collect($record->docs)->count(),
                                'warning'
                            ))
                            ->icon('heroicon-o-document')
                            ->schema([
                                Section::make(' ')
                                    ->hiddenLabel()
                                    ->schema([static::viewDocs()])->columnSpanFull(),
                            ]),
                        Tab::make(__('resources/shipment/strings.infolist.tab_documents'))
                            ->label(fn($record) => tabBadge(
                                __('resources/shipment/strings.infolist.tab_documents'),
                                collect($record->docs['items'] ?? [])->count(),
                                'info'
                            ))
                            ->icon('heroicon-o-paper-clip')
                            ->schema([Section::make()->schema([static::viewAttachments()])]),
                        static::getExtraAttributesInfolistTab(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                static::showId(),
                static::showRegisteredOrder(),
                static::showShipmentNo(),
                static::showCarrier(),
                static::showPart(),
                static::showBlNumber(),
                static::showStatus(),
                static::showTrackingStatus(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getCarrierFilter(),
                static::getStatusFilter(),
                static::getTrackingStatusFilter(),
                static::getContainerStatusFilter(),
                static::getDocStatusFilter(),
                static::getOperationStatusFilter(),
                static::getEtaFilter(),
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
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(ShipmentExporter::class),
                ]),
            ])
            ->groups([
                TableGroup::make('registeredOrder.ro_number')
                    ->label(__('resources/shipment/strings.form.registered_order'))
                    ->getTitleFromRecordUsing(fn(Model $record) => $record->registeredOrder?->formatted_name_without_date),
                TableGroup::make('carrier.name')
                    ->label(__('resources/shipment/strings.form.carrier'))
                    ->getTitleFromRecordUsing(fn(Model $record) => $record->carrier?->getLocalizedNameAttribute()),
                TableGroup::make('status.name')
                    ->label(__('resources/shipment/strings.form.status'))
                    ->getTitleFromRecordUsing(fn(Model $record) => $record->status?->getLocalizedNameAttribute()),
                TableGroup::make('trackingStatus.name')
                    ->label(__('resources/shipment/strings.form.shipment_status'))
                    ->getTitleFromRecordUsing(fn(Model $record) => $record->trackingStatus?->getLocalizedNameAttribute()),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
