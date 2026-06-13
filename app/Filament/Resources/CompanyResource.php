<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\CompanyResource\Exports\CompanyExporter;
use App\Filament\Resources\Master\CompanyResource\Pages\ManageCompanies;
use App\Filament\Resources\Master\CompanyResource\Traits\Filters as CompanyFilters;
use App\Filament\Resources\Master\CompanyResource\Traits\Form as CompanyForm;
use App\Filament\Resources\Master\CompanyResource\Traits\Infolist as CompanyInfolist;
use App\Filament\Resources\Master\CompanyResource\Traits\Table as CompanyTable;
use App\Filament\Traits\HandleActivation;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Company;
use App\Services\SmartCacheManager;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CompanyResource extends Resource
{
    use CompanyForm, CompanyTable, CompanyInfolist, CompanyFilters, HandleActivation, HasResourcePermissions;

    protected static ?string $model = Company::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 3;


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('resources/company/strings.form.basic_information'))
                    ->schema([
                        static::getName(),
                        static::getEnglishName(),
                        static::getDescription(),
                        static::getIsActive(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),

                Section::make(__('resources/company/strings.form.company_classification'))
                    ->schema([
                        static::getCompanyTypes(),
                    ])
                    ->collapsed()
                    ->persistCollapsed()
                    ->description(__('resources/company/strings.form.classification_description')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'creator',
                'updater',
            ])
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

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'english_name'];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index', ['search' => $record->english_name ?? $record->name ?? '']);
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
        return $table
            ->columns([
                static::showName(),
                static::showEnglishName(),
                static::showCompanyTypes(),
                static::showDescription(),
                static::showIsActive(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getActiveFilter(),
                static::getCompanyTypeFilter(),
                static::getThrashedFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
            ])
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
                    static::getActivateBulkAction(),
                    static::getDeactivateBulkAction(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(CompanyExporter::class)
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(25);
    }
}
