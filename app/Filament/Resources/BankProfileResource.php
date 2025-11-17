<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\FormComponents;
use App\Filament\Resources\Operational\BankProfileResource\Exports\BankProfileExporter;
use App\Filament\Resources\Operational\BankProfileResource\Pages\CreateBankProfile;
use App\Filament\Resources\Operational\BankProfileResource\Pages\EditBankProfile;
use App\Filament\Resources\Operational\BankProfileResource\Pages\ListBankProfiles;
use App\Filament\Resources\Operational\BankProfileResource\RelationManagers\RegisteredOrdersRelationManager;
use App\Filament\Resources\Operational\BankProfileResource\Traits\Filters as BankProfileFilters;
use App\Filament\Resources\Operational\BankProfileResource\Traits\Form as BankProfileForm;
use App\Filament\Resources\Operational\BankProfileResource\Traits\Infolist as BankProfileInfolist;
use App\Filament\Resources\Operational\BankProfileResource\Traits\Table as BankProfileTable;
use App\Models\BankProfile;
use App\Models\Category;
use App\Models\Product;
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
use Filament\Schemas\Schema;
use Filament\Tables\Grouping\Group as TableGroup;
use Filament\Tables\Table as FilamentTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletingScope;


class BankProfileResource extends Resource
{
    use BankProfileForm, BankProfileTable, BankProfileFilters, BankProfileInfolist;

    protected static ?string $model = BankProfile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Bank Profile')
                    ->tabs([
                        Tab::make(__('resources/bankProfile/strings.form.tabs.general'))
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/bankProfile/strings.form.section_main'))
                                            ->schema([
                                                static::getRegisteredOrderField(),
                                                static::getTargetableField(),
                                                static::getBpNumberField(),
                                                static::getOrderNumberField(),
                                                static::getBankField(),
                                                static::getSupplySourceField(),
                                                static::getCompanyField(),
                                                static::getStatusField(),
                                            ])->columns(2),
                                    ])
                                    ->columnSpan(['lg' => 2]),
                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/bankProfile/strings.form.section_dates_notes'))
                                            ->schema([
                                                static::getAllocationDateField(),
                                                static::getPurchaseDateField(),
                                                static::getDeliveryDateField(),
                                                static::getNotesField(),
                                                FormComponents::getAttachmentsField()
                                            ])
                                            ->columns(1),
                                    ])
                                    ->columnSpan(['lg' => 1]),
                            ])->columns(3),

                        Tab::make(__('resources/bankProfile/strings.form.tabs.details'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/bankProfile/strings.form.section_financial'))
                                            ->schema([
                                                static::getCurrencyField(),
                                                static::getRequestedAmountField(),
                                                static::getPurchasedEquivalentField(),
                                                static::getDocumentsAmountField(),
                                                static::getCommissionRateField(),
                                            ])->columns(3),

                                        Section::make(__('resources/bankProfile/strings.form.section_rates'))
                                            ->schema([
                                                static::getExchangeRateField(),
                                                static::getEurEquivalentRateField(),
                                            ])->columns(3),
                                    ])
                                    ->columnSpan(['lg' => 2]),
                                Group::make()
                                    ->schema([
                                        Section::make(__('resources/bankProfile/strings.form.section_summary'))
                                            ->schema(static::getSummaryFields())
                                            ->columns(2),
                                    ])
                                    ->columnSpan(['lg' => 1]),
                            ])->columns(3)
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
                'bank',
                'company',
                'currency',
                'targetable' => fn(MorphTo $morphTo) => $morphTo->morphWith([Product::class, Category::class]),
                'registeredOrder',
                'status',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $bp = $record->bp_number ?? $record->id ?? '—';

        return "🏦 {$bp} (📆 {$date})";
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['bp_number'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/bankProfile/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'BankProfile',
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
        return __('resources/dashboard/strings.navigation_group.operational_second');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankProfiles::route('/'),
            'create' => CreateBankProfile::route('/create'),
            'edit' => EditBankProfile::route('/{record}/edit'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/bankProfile/strings.general.plural_model_label');
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Details')->tabs([
                    Tab::make(__('resources/bankProfile/strings.infolist.tab_general'))
                        ->icon('heroicon-o-building-office')
                        ->schema([
                            Section::make()
                                ->schema([
                                    static::viewRegisteredOrder(),
                                    static::viewBpNumber(),
                                    static::viewOrderNumber(),
                                    static::viewCompany(),
                                    static::viewBank(),
                                    static::viewTargetable(),
                                    static::viewSupplySource(),
                                    static::viewStatus(),
                                    static::viewAllocationDate(),
                                    static::viewPurchaseDate(),
                                    static::viewDeliveryDate(),
                                    static::viewNotes(),
                                    static::viewCreator(),
                                    static::viewUpdater(),
                                    static::viewCreatedAt(),
                                    static::viewUpdatedAt(),
                                ])->columns(3),
                        ]),
                    Tab::make(__('resources/bankProfile/strings.infolist.tab_summary'))
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Section::make()
                                ->schema([
                                    static::viewCurrency(),
                                    static::viewRequestedAmount(),
                                    static::viewPurchasedEquivalent(),
                                    static::viewCommissionRate(),
                                    static::viewExchangeRate(),
                                    static::viewFinalRate(),
                                    static::viewEurEquivalentRate(),
                                    static::viewDocumentsAmount(),
                                    static::viewCommissionAmountPurchased(),
                                    static::viewCommissionEquivalentEur(),
                                    static::viewFinalEurEquivalent(),
                                    static::viewRemainingCommitment(),
                                    static::viewTotalUsdRemittance(),
                                    static::viewTotalEurRemittance(),
                                    static::viewTotalRialRemittance(),
                                ])->columns(3),
                        ]),
                    Tab::make(__('resources/bankProfile/strings.infolist.tab_documents'))
                        ->icon('heroicon-o-paper-clip')
                        ->schema([
                            Section::make()->schema([static::viewAttachments()])
                        ])
                        ->badge(fn($record) => $record->attachments->count())
                        ->badgeColor('info'),
                ])->columnSpanFull(),
            ]);
    }

    public static function table(FilamentTable $table): FilamentTable
    {
        return $table
            ->columns([
                static::showId(),
                static::showRegisteredOrder(),
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
                static::getRegisteredOrderFilter(),
                static::getStatusFilter(),
                static::getBankFilter(),
                static::getCompanyFilter(),
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
                        ->exporter(BankProfileExporter::class),
                ]),
            ])
            ->groups([
                TableGroup::make('bank.name')
                    ->label(__('resources/bankProfile/strings.groups.bank'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => $record->bank?->getLocalizedNameAttribute()),
                TableGroup::make('company.name')
                    ->label(__('resources/bankProfile/strings.groups.company'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => $record->company?->getLocalizedNameAttribute()),
                TableGroup::make('registeredOrder.ro_number')
                    ->label(__('resources/bankProfile/strings.groups.registered_order'))
                    ->getTitleFromRecordUsing(fn(Model $record): ?string => $record->registeredOrder?->formatted_name ?? '-'),
                TableGroup::make('targetable_type')
                    ->label(__('resources/bankProfile/strings.groups.targetable'))
                    ->getTitleFromRecordUsing(fn (Model $record): ?string => $record->getTargetableFormatted('table')),
            ])
            ->striped()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RegisteredOrdersRelationManager::class,
        ];
    }
}

