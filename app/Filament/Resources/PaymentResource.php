<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\FormComponents;
use App\Filament\Resources\Operational\PaymentResource\Exports\PaymentExporter;
use App\Filament\Resources\Operational\PaymentResource\Pages\CreatePayment;
use App\Filament\Resources\Operational\PaymentResource\Pages\EditPayment;
use App\Filament\Resources\Operational\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\Operational\PaymentResource\RelationManagers\PurchaseOrderRelationManager;
use App\Filament\Resources\Operational\PaymentResource\RelationManagers\RegisteredOrderRelationManager;
use App\Filament\Resources\Operational\PaymentResource\Traits\Filters as PaymentFilters;
use App\Filament\Resources\Operational\PaymentResource\Traits\Form as PaymentForm;
use App\Filament\Resources\Operational\PaymentResource\Traits\Infolist as PaymentInfolist;
use App\Filament\Resources\Operational\PaymentResource\Traits\Table as PaymentTable;
use App\Filament\Resources\Operational\PaymentResource\Traits\VisibilityCheck;
use App\Models\BankProfile;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\RegisteredOrder;
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
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class PaymentResource extends Resource
{
    use PaymentForm, PaymentTable, PaymentFilters, PaymentInfolist, VisibilityCheck;

    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Operational';


    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Payment')
                    ->afterStateHydrated(fn(Get $get, Set $set) => static::updateComputations($get, $set))
                    ->tabs([
                        Tab::make(__('resources/payment/strings.form.tabs.general'))
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/payment/strings.form.section_main'))
                                            ->schema([
                                                static::getTargetableField(),
                                                Group::make()
                                                    ->schema([
                                                        static::getPaymentNoField(),
                                                        static::getStatusField(),
                                                        static::getPaymentDateField(),
                                                        static::getPaymentDeadlineField(),
                                                        static::getPayorField(),
                                                        static::getPayeeField(),
                                                    ])
                                                    ->columns(2)
                                                    ->columnSpanFull()
                                                    ->disabled(fn(Get $get): bool => !static::isTargetSelected($get))
                                                    ->hidden(fn(Get $get): bool => !static::isTargetSelected($get))
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpan(['lg' => 2]),

                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/payment/strings.form.section_notes_attachments'))
                                            ->schema([
                                                static::getNotesField(),
                                                FormComponents::getAttachmentsField(),
                                            ])
                                            ->disabled(fn(Get $get): bool => !static::isTargetSelected($get))
                                            ->hidden(fn(Get $get): bool => !static::isTargetSelected($get))
                                            ->columns(1),
                                    ])
                                    ->columnSpan(['lg' => 1]),
                            ])->columns(3),

                        Tab::make(__('resources/payment/strings.form.tabs.financials'))
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/payment/strings.form.section_financial'))
                                            ->schema([
                                                static::getCurrencyField(),
                                                static::getPayableAmountField(),
                                                static::getBankChargesField(),
                                                static::getExchangeRateField(),
                                                static::getTotalAmountField(),
                                            ])->columns(3),

                                        Section::make(__('resources/payment/strings.form.section_beneficiary_bank'))
                                            ->schema([
                                                static::getBankIdField(),
                                                static::getAccountNoField(),
                                                static::getBeneficiaryNameField(),
                                                static::getSwiftField(),
                                                static::getIbanField(),
                                                static::getBeneficiaryAddressField(),
                                                static::getBankAddressField(),
                                            ])->columns(3),
                                    ])
                                    ->disabled(fn(Get $get): bool => !static::isTargetSelected($get))
                                    ->hidden(fn(Get $get): bool => !static::isTargetSelected($get))
                                    ->columnSpan(['lg' => 2]),

                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/payment/strings.form.section_summary'))
                                            ->schema(static::getSummaryFields())
                                            ->columns(1),
                                    ])
                                    ->disabled(fn(Get $get): bool => !static::isTargetSelected($get))
                                    ->hidden(fn(Get $get): bool => !static::isTargetSelected($get))
                                    ->columnSpan(['lg' => 1]),
                            ])->columns(3),
                    ])
                    ->columnSpan('full'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
                'attachments',
                'payor',
                'payee',
                'currency',
                'targetable' => fn(MorphTo $morphTo) => $morphTo->morphWith([
                    PurchaseOrder::class,
                    RegisteredOrder::class,
                    BankProfile::class,
                ]),
                'status',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $payNo = $record->payment_no ?? $record->id ?? '—';
        return "💳 {$payNo} (📆 {$date})";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['payment_no'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/payment/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'Payment',
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
        return __('resources/dashboard/strings.navigation_group.operational_third');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'edit' => EditPayment::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/payment/strings.general.plural_model_label');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RegisteredOrderRelationManager::class,
            PurchaseOrderRelationManager::class,
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Tabs::make('Details')->tabs([
                    Tab::make(__('resources/payment/strings.infolist.tab_general'))
                        ->icon('heroicon-o-clipboard-document-list')
                        ->schema([
                            Section::make()
                                ->schema([
                                    static::viewTargetables(),
                                    static::viewPaymentNo(),
                                    static::viewStatus(),
                                    static::viewPaymentDate(),
                                    static::viewPaymentDeadline(),
                                    static::viewPayor(),
                                    static::viewPayee(),
                                    static::viewBank(),
                                    static::viewBeneficiaryName(),
                                    static::viewAccountNo(),
                                    static::viewSwift(),
                                    static::viewIban(),
                                    static::viewBeneficiaryAddress(),
                                    static::viewBankAddress(),
                                    static::viewNotes(),
                                    static::viewCreator(),
                                    static::viewUpdater(),
                                    static::viewCreatedAt(),
                                    static::viewUpdatedAt(),
                                ])->columns(3),
                        ]),
                    Tab::make(__('resources/payment/strings.infolist.tab_summary'))
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Section::make()
                                ->schema([
                                    static::viewCurrency(),
                                    static::viewPayableAmount(),
                                    static::viewBankCharges(),
                                    static::viewExchangeRate(),
                                    static::viewTotalAmount(),
                                    static::viewCalculatedTotal(),
                                    static::viewTotalRatio(),
                                ])->columns(3),
                        ]),
                    Tab::make(__('resources/payment/strings.infolist.tab_documents'))
                        ->icon('heroicon-o-paper-clip')
                        ->schema([
                            Section::make()->schema([static::viewAttachments()])
                        ])
                        ->label(fn($record) => tabBadge(
                            __('resources/payment/strings.infolist.tab_documents'),
                            $record?->attachments->count() ?? 0,
                            'info'
                        )),
                ])->columnSpanFull(),
            ]);
    }

    public static function table(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                static::showTargetable(),
                static::showId(),
                static::showTargetableType(),
                static::showPaymentNo(),
                static::showPaymentDate(),
                static::showPayor(),
                static::showPayee(),
                static::showTotalAmount(),
                static::showStatus(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),

            ])
            ->filters([
                static::getTargetableFilter(),
                static::getStatusFilter(),
                static::getPayorFilter(),
                static::getPayeeFilter(),
                static::getCurrencyFilter(),
                static::getCreatorFilter(),
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
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(PaymentExporter::class)
                ]),
            ])
            ->groups([
                TableGroup::make('targetable_type')
                    ->label(__('resources/payment/strings.groups.targetable'))
                    ->getTitleFromRecordUsing(fn(Model $record) => $record->getTargetableDisplay()),
                TableGroup::make('payor.name')
                    ->label(__('resources/payment/strings.groups.payor'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => $record->payor?->getLocalizedNameAttribute()),
                TableGroup::make('payee.name')
                    ->label(__('resources/payment/strings.groups.payee'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => $record->payee?->getLocalizedNameAttribute()),
                TableGroup::make('beneficiary_name')
                    ->label(__('resources/payment/strings.groups.beneficiary_name')),
                TableGroup::make('status.name')
                    ->label(__('resources/payment/strings.groups.status'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => $record->status?->getLocalizedNameAttribute()),
                TableGroup::make('currency.name')
                    ->label(__('resources/payment/strings.groups.currency'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => $record->currency?->getLocalizedNameAttribute()),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
