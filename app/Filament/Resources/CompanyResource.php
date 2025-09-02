<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\CompanyResource\Exports\CompanyExporter;
use App\Filament\Resources\Master\CompanyResource\Traits\Filters as CompanyFilters;
use App\Filament\Resources\Master\CompanyResource\Traits\Form as CompanyForm;
use App\Filament\Resources\Master\CompanyResource\Traits\Infolist as CompanyInfolist;
use App\Filament\Resources\Master\CompanyResource\Traits\Table as CompanyTable;
use App\Models\Company;
use App\Services\SmartCacheManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CompanyResource extends Resource
{
    use CompanyForm, CompanyTable, CompanyInfolist, CompanyFilters;

    protected static ?string $model = Company::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 3;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('resources/company/strings.form.basic_information'))
                    ->schema([
                        static::getName(),
                        static::getEnglishName(),
                        static::getDescription(),
                        static::getIsActive(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('resources/company/strings.form.company_classification'))
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
                'soldProformaInvoices',
                'consignedProformaInvoices',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "🏢 " . $record->getLocalizedNameAttribute();
    }

    public static function getModelLabel(): string
    {
        return __('resources/company/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'Company',
            ['user_id' => auth()->id(), 'type' => 'total_count'],
            3600,
            fn() => static::getModel()::active()->count()
        );

        return $count > 0 ? (string)$count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/company/strings.general.navigation_group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Master\CompanyResource\Pages\ManageCompanies::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/company/strings.general.plural_model_label');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make()
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
                    ])->columns(2),
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
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(CompanyExporter::class)
                ]),
            ])
            ->striped()
            ->defaultSort('id', 'desc');
    }
}
