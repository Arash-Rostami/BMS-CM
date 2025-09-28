<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\BankResource\Exports\BankExporter;
use App\Filament\Resources\Master\BankResource\Traits\Filters as BankFilters;
use App\Filament\Resources\Master\BankResource\Traits\Form as BankForm;
use App\Filament\Resources\Master\BankResource\Traits\Infolist as BankInfolist;
use App\Filament\Resources\Master\BankResource\Traits\Table as BankTable;
use App\Filament\Traits\HandleActivation;
use App\Models\Bank;
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

class BankResource extends Resource
{
    use BankForm, BankTable, BankInfolist, BankFilters, HandleActivation;

    protected static ?string $model = Bank::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?int $navigationSort = 4;


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        static::getName(),
                        static::getEnglishName(),
                        static::getDescription(),
                        static::getIsActive(),
                    ])->columns(2),
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
        return "🏦 " . $record->getLocalizedNameAttribute();
    }

    public static function getModelLabel(): string
    {
        return __('resources/bank/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'Bank',
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
        return __('resources/bank/strings.general.navigation_group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Master\BankResource\Pages\ManageBanks::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/bank/strings.general.plural_model_label');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make()
                    ->schema([
                        static::viewName(),
                        static::viewEnglishName(),
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
                static::showDescription(),
                static::showIsActive(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getActiveFilter(),
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
                    static::getActivateBulkAction(),
                    static::getDeactivateBulkAction(),
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(BankExporter::class)
                ]),
            ])
            ->striped()
            ->defaultSort('id', 'desc');
    }
}
