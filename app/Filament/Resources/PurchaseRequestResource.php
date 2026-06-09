<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\FormComponents;
use App\Filament\Resources\General\InfoComponents;
use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Status;
use App\Filament\Resources\Operational\PurchaseRequestResource\Exports\PurchaseRequestExporter;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\CreatePurchaseRequest;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\EditPurchaseRequest;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\ListPurchaseRequests;
use App\Filament\Resources\Operational\PurchaseRequestResource\RelationManagers\ProformaInvoicesRelationManager;
use App\Filament\Resources\Operational\PurchaseRequestResource\RelationManagers\PurchaseOrdersRelationManager;
use App\Filament\Resources\Operational\PurchaseRequestResource\RelationManagers\RegisteredOrderRelationManager;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Filters as PurchaseRequestFilters;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Form as PurchaseRequestForm;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Infolist as PurchaseRequestInfolist;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Table as PurchaseRequestTable;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\TotalCostCalculation;
use App\Filament\Traits\HasExtraAttributesManagement;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\PurchaseRequest;
use App\Services\SmartCacheManager;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Repeater;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class PurchaseRequestResource extends Resource
{
    use PurchaseRequestForm, TotalCostCalculation, PurchaseRequestTable, PurchaseRequestFilters, PurchaseRequestInfolist, HasResourcePermissions, HasExtraAttributesManagement;

    protected static ?string $model = PurchaseRequest::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('PurchaseRequest')
                    ->tabs([
                        Tab::make(__('resources/purchaseRequest/strings.form.tab_general'))
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                \Filament\Schemas\Components\Group::make()
                                    ->schema([
                                        Section::make(__('resources/purchaseRequest/strings.form.request_details'))
                                            ->schema([
                                                static::getPrNumberField(),
                                                static::getCostCenterIdField(),
                                                static::getRequiredByDateField(),
                                                static::getUrgencyLevelField(),
                                                static::getStatusIdField(),
                                            ])
                                            ->columns(3),

                                        Section::make(__('resources/purchaseRequest/strings.form.items'))
                                            ->heading(__('resources/purchaseRequest/strings.form.purchase_items'))
                                            ->schema([
                                                Repeater::make('items')
                                                    ->hiddenLabel()
                                                    ->relationship()
                                                    ->schema([
                                                        Section::make()->schema(
                                                            [
                                                                static::getItemProductIdField(),
                                                                static::getItemStatusIdField(),
                                                                static::getItemQuantityField(),
                                                                static::getItemUnitField(),
                                                                static::getItemEstimatedCostField(),
                                                                static::getItemNotesToggle(),
                                                                static::getItemNotesField(),
                                                            ]
                                                        )->columns(3)
                                                    ])
                                                    ->live(true)
                                                    ->defaultItems(0)
                                                    ->afterStateUpdated(fn(Get $get, Set $set) => self::updateTotalCost($get, $set))
                                                    ->afterStateHydrated(fn(Get $get, Set $set) => static::updateTotalCost($get, $set))
                                                    ->deleteAction(fn($action) => $action->after(fn(Get $get, Set $set) => self::updateTotalCost($get, $set)))
                                                    ->addActionLabel(__('resources/purchaseRequest/strings.form.add_purchase_item'))
                                                    ->label(false)
                                            ]),
                                    ])
                                    ->columnSpan(['lg' => 2]),

                                \Filament\Schemas\Components\Group::make()
                                    ->schema([
                                        Section::make(__('resources/purchaseRequest/strings.form.status_and_notes'))
                                            ->schema([
                                                static::getTotalEstimatedCostField(),
                                                static::getRejectionReasonField(),
                                                static::getApproverIdField(),
                                                static::getApprovalDateField(),
                                                static::getNotesField(),
                                                FormComponents::getAttachmentsField()
                                            ]),
                                    ])
                                    ->columnSpan(['lg' => 1]),
                            ])
                            ->columns(3),
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
                'approver',
                'attachments',
                'extraAttributes',
                'costCenter',
                'department',
                'items',
                'items.attachments',
                'items.product',
                'items.status',
                'proformaInvoices',
                'registeredOrders',
                'purchaseOrders',
                'requester',
                'status',
            ])
            ->withCount([
                'proformaInvoices',
                'registeredOrders',
                'purchaseOrders',
            ])
            ->withCount('proformaInvoices')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $pr = $record->pr_number ?? '—';

        return "🛒 {$pr} (📆 {$date})";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['pr_number'];
    }


    public static function getModelLabel(): string
    {
        return __('resources/purchaseRequest/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'PurchaseRequest',
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
        return __('resources/dashboard/strings.navigation_group.operational_first');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchaseRequests::route('/'),
            'create' => CreatePurchaseRequest::route('/create'),
            'edit' => EditPurchaseRequest::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/purchaseRequest/strings.general.plural_model_label');
    }

    public static function getRelations(): array
    {
        return [
            ProformaInvoicesRelationManager::class,
            RegisteredOrderRelationManager::class,
            PurchaseOrdersRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Details')
                    ->tabs([
                        Tab::make('General')
                            ->label(__('resources/purchaseRequest/strings.infolist.tab_general'))
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Section::make()->schema([
                                    InfoComponents::viewProformaInvoices(),
                                    InfoComponents::viewRegisteredOrders(),
                                    InfoComponents::viewPurchaseOrders(),
                                    static::viewPrNumber(),
                                    static::viewDepartment(),
                                    static::viewCostCenter(),
                                    static::viewRequester(),
                                    static::viewRequiredByDate(),
                                    static::viewUrgency(),
                                    static::viewTotalCost(),
                                    static::viewStatus(),
                                    static::viewApprover(),
                                    static::viewApprovalDate(),
                                    static::viewRejectionReason(),
                                    static::viewNotes(),
                                    static::viewCreator(),
                                    static::viewUpdater(),
                                    static::viewCreatedAt(),
                                    static::viewUpdatedAt(),
                                ])->columns(3),
                            ]),
                        Tab::make('Items')
                            ->label(__('resources/purchaseRequest/strings.infolist.tab_items'))
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                Section::make()->schema([static::viewPurchaseItems()])
                            ]),
                        Tab::make('Documents')
                            ->icon('heroicon-o-paper-clip')
                            ->schema([
                                Section::make()->schema([static::viewAttachments()])
                            ])
                            ->label(fn($record) => tabBadge(
                                __('resources/purchaseRequest/strings.infolist.tab_documents'),
                                $record?->attachments->count() ?? 0,
                                'info'
                            )),
                        static::getExtraAttributesInfolistTab(),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showSource(),
                static::showID(),
                TableComponents::showProformaInvoices(),
                TableComponents::showRegisteredOrders(),
                TableComponents::showPurchaseOrders(),
                static::showPrNumber(),
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
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
