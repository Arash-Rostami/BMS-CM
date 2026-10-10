<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\CurrencyResource\Pages\ManageCurrencies;
use App\Filament\Resources\Master\CurrencyResource\Traits\Filters as CurrencyFilters;
use App\Filament\Resources\Master\CurrencyResource\Traits\Form as CurrencyForm;
use App\Filament\Resources\Master\CurrencyResource\Traits\Infolist as CurrencyInfolist;
use App\Filament\Resources\Master\CurrencyResource\Traits\Table as CurrencyTable;
use App\Filament\Traits\HandleActivation;
use App\Filament\Traits\HasGlobalSearchConvention;
use App\Filament\Traits\HasResourcePermissions;
use App\Filament\Traits\HasUsageGuard;
use App\Models\Currency;
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
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Str;

class CurrencyResource extends Resource
{
    use CurrencyFilters, CurrencyForm, CurrencyInfolist, CurrencyTable, HandleActivation, HasGlobalSearchConvention, HasResourcePermissions, HasUsageGuard;

    protected static ?string $model = Currency::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::getName(),
                        static::getEnglishName(),
                        static::getDescription(),
                        static::getIsActive(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
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

        return "💰    {$name} (📆 {$date})";
    }

    protected static function globalSearchRelations(): array
    {
        return ['creator'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('resources/currency/strings.table.english_name') => $record->english_name ?? '—',
            __('resources/currency/strings.table.description') => Str::limit($record->description, 40) ?: '—',
            __('resources/currency/strings.table.creator') => $record->creator?->name ?? '—',
        ];
    }

    public static function getModelLabel(): string
    {
        return __('resources/currency/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCurrencies::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/currency/strings.general.plural_model_label');
    }

    protected static function usageRelations(): array
    {
        return [
            'proformaInvoicesAsMain',
            'proformaInvoicesAsSecondary',
            'bankProfilesAsRequested',
            'bankProfilesAsPurchased',
            'payments',
            'purchaseOrders',
            'registeredOrders',
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
                static::getInUseFilter(),
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
