<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\CompanyResource\Pages\ManageCompanies;
use App\Filament\Resources\Master\CompanyResource\Traits\Filters as CompanyFilters;
use App\Filament\Resources\Master\CompanyResource\Traits\Form as CompanyForm;
use App\Filament\Resources\Master\CompanyResource\Traits\Infolist as CompanyInfolist;
use App\Filament\Resources\Master\CompanyResource\Traits\Table as CompanyTable;
use App\Filament\Traits\HandleActivation;
use App\Filament\Traits\HasGlobalSearchConvention;
use App\Filament\Traits\HasResourcePermissions;
use App\Filament\Traits\HasUsageGuard;
use App\Models\Company;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class CompanyResource extends Resource
{
    use CompanyFilters, CompanyForm, CompanyInfolist, CompanyTable, HandleActivation, HasGlobalSearchConvention, HasResourcePermissions, HasUsageGuard;

    protected static ?string $model = Company::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Company')
                    ->tabs([
                        Tab::make(__('resources/company/strings.form.tab_general'))
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make(__('resources/company/strings.form.basic_information'))
                                    ->schema([
                                        static::getName(),
                                        static::getEnglishName(),
                                        static::getDescription(),
                                        static::getIsActive(),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make(__('resources/company/strings.form.tab_classification'))
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Section::make(__('resources/company/strings.form.company_classification'))
                                    ->schema([
                                        static::getCompanyTypes(),
                                    ])
                                    ->description(__('resources/company/strings.form.classification_description'))
                                    ->columnSpanFull(),
                            ]),
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
            ])
            ->withCount(static::usageRelations())
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $name = $record->getLocalizedNameAttribute() ?? '-';

        return "🏢    {$name} (📆 {$date})";
    }

    protected static function globalSearchRelations(): array
    {
        return ['creator'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('resources/company/strings.table.english_name') => $record->english_name ?? '—',
            __('resources/company/strings.table.description') => Str::limit($record->description, 40) ?: '—',
            __('resources/company/strings.table.creator') => $record->creator?->name ?? '—',
        ];
    }

    public static function getModelLabel(): string
    {
        return __('resources/company/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCompanies::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/company/strings.general.plural_model_label');
    }

    protected static function usageRelations(): array
    {
        return [
            'proformaInvoicesAsSeller', 'proformaInvoicesAsBuyer',
            'purchaseOrdersAsSeller', 'purchaseOrdersAsBuyer',
            'registeredOrdersAsSeller', 'registeredOrdersAsBuyer',
            'paymentsAsPayor', 'paymentsAsPayee',
            'bankProfiles', 'shipments',
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::viewName(),
                        static::viewEnglishName(),
                        static::viewCompanyTypes(),
                        static::viewDescription(),
                        static::viewIsActive(),
                        static::viewCreator(),
                        static::viewUpdater(),
                        static::viewCreatedAt(),
                        static::viewUpdatedAt(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->columns([
                static::showName(),
                static::showEnglishName(),
                static::showCompanyTypes(),
                static::showDescription(),
                static::showIsActive(),
                static::getInUseCountColumn(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getActiveFilter(),
                static::getCompanyTypeFilter(),
                static::getNoTypesFilter(),
                static::getThrashedFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    static::guardRecordAction(DeleteAction::make()),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([

                BulkActionGroup::make([
                    static::getExportBulkAction(),
                    static::getActivateBulkAction(),
                    static::guardBulkAction(static::getDeactivateBulkAction()),
                    static::guardBulkAction(DeleteBulkAction::make()),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->defaultSort('id', 'desc'));
    }
}
