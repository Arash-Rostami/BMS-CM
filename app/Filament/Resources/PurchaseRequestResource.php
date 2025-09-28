<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Operational\PurchaseRequestResource\Enums\Status;
use App\Filament\Resources\Operational\PurchaseRequestResource\Exports\PurchaseRequestExporter;
use App\Filament\Resources\Operational\PurchaseRequestResource\Pages\ViewPurchaseRequest;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Filters as PurchaseRequestFilters;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Form as PurchaseRequestForm;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Infolist as PurchaseRequestInfolist;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\Table as PurchaseRequestTable;
use App\Filament\Resources\Operational\PurchaseRequestResource\Traits\TotalCostCalculation;
use App\Models\PurchaseRequest;
use App\Services\SmartCacheManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Components\Tabs as InfoTabs;
use Filament\Infolists\Components\Tabs\Tab;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class PurchaseRequestResource extends Resource
{
    use PurchaseRequestForm, TotalCostCalculation, PurchaseRequestTable, PurchaseRequestFilters, PurchaseRequestInfolist;

    protected static ?string $model = PurchaseRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 2;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('resources/purchaseRequest/strings.form.request_details'))
                            ->schema([
                                static::getCostCenterIdField(),
                                static::getRequiredByDateField(),
                                static::getUrgencyLevelField(),
                                static::getTotalEstimatedCostField(),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make(__('resources/purchaseRequest/strings.form.items'))
                            ->heading(__('resources/purchaseRequest/strings.form.purchase_items'))
                            ->schema([
                                Forms\Components\Repeater::make('items')
                                    ->relationship()
                                    ->schema([
                                        Forms\Components\Section::make()->schema(
                                            [
                                                static::getItemProductIdField(),
                                                static::getItemStatusIdField(),
                                                static::getItemQuantityField(),
                                                static::getItemUnitField(),
                                                static::getItemEstimatedCostField(),
                                                static::getItemNotesToggle(),
                                                static::getItemAttachmentsToggle(),
                                                static::getItemNotesField(),
//                                                static::getItemAttachmentsField(),
                                            ]
                                        )->columns(3)

                                    ])
                                    ->live(true)
                                    ->afterStateUpdated(fn(Forms\Get $get, Forms\Set $set) => self::updateTotalCost($get, $set))
                                    ->deleteAction(fn($action) => $action->after(fn(Forms\Get $get, Forms\Set $set) => self::updateTotalCost($get, $set)))
                                    ->addActionLabel(__('resources/purchaseRequest/strings.form.add_purchase_item'))
                                    ->label(false)
                            ]),
                    ])
                    ->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('resources/purchaseRequest/strings.form.status_and_notes'))
                            ->schema([
                                static::getStatusIdField(),
                                static::getRejectionReasonField(),
                                static::getApproverIdField(),
                                static::getApprovalDateField(),
                                static::getNotesField(),
                                static::getAttachmentsField()
                                    ->hintIconTooltip(fn($record) => $record?->attachments()->latest('id')->implode('name', "\n") ?? '')

                            ]),
                    ])
                    ->columnSpan(['lg' => 1]),
            ])
            ->columns(3);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
                'approver',
                'attachments',
                'costCenter',
                'department',
                'items',
                'items.attachments',
                'items.product',
                'items.status',
                'proformaInvoices',
                'purchaseOrders',
                'requester',
                'status',
            ])
            ->withCount('proformaInvoices')
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "🛒 " . $record->id;
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
        return __('resources/purchaseRequest/strings.general.navigation_group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Operational\PurchaseRequestResource\Pages\ListPurchaseRequests::route('/'),
            'create' => Operational\PurchaseRequestResource\Pages\CreatePurchaseRequest::route('/create'),
            'edit' => Operational\PurchaseRequestResource\Pages\EditPurchaseRequest::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/purchaseRequest/strings.general.plural_model_label');
    }

    public static function getRelations(): array
    {
        return [
            Operational\PurchaseRequestResource\RelationManagers\PurchaseOrdersRelationManager::class,
            Operational\PurchaseRequestResource\RelationManagers\ProformaInvoicesRelationManager::class,
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfoTabs::make('Details')
                    ->tabs([
                        Tab::make('General')
                            ->label(__('resources/purchaseRequest/strings.infolist.tab_general'))
                            ->icon('heroicon-o-shopping-cart')
                            ->schema([
                                Components\Section::make()->schema([
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
                                Components\Section::make()->schema([static::viewPurchaseItems()])
                            ]),
                        Tab::make('Documents')
                            ->label(__('resources/purchaseRequest/strings.infolist.tab_documents'))
                            ->icon('heroicon-o-paper-clip')
                            ->schema([
                                Components\Section::make()->schema([static::viewAttachments()])
                            ])
                            ->badge(fn($record) => $record->attachments->count())
                            ->badgeColor('info'),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
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
            ->defaultSort('id', 'desc');
    }
}
