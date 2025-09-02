<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Operational\TargetResource\Enums\Status;
use App\Filament\Resources\Operational\TargetResource\Exports\TargetExporter;
use App\Filament\Resources\Operational\TargetResource\Pages\ManageTargets;
use App\Filament\Resources\Operational\TargetResource\Traits\Filters as TargetFilters;
use App\Filament\Resources\Operational\TargetResource\Traits\Form as TargetForm;
use App\Filament\Resources\Operational\TargetResource\Traits\Infolist as TargetInfolist;
use App\Filament\Resources\Operational\TargetResource\Traits\Table as TargetTable;
use App\Models\Target;
use App\Services\SmartCacheManager;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TargetResource extends Resource
{
    use TargetForm, TargetTable, TargetInfolist, TargetFilters;

    protected static ?string $model = Target::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 1;


    public static function form(Form $form): Form
    {

        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        static::getTargetableField(),
                        static::getStatusField(),
                        static::getYearField(),
                        static::getStartFromField(),
                        static::getEndInField(),
                        static::getQuantityField(),
                        static::getMetricsField(),
                        static::getAmountField(),
                        static::getAchievedQuantityField(),
                        static::getAchievedAmountField(),
                        static::getDescriptionField(),
                        static::getTagField(),
                    ])->columns(3),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'targetable',
                'creator',
                'updater'
            ])
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return " 🎯  " .
            $record->id;
    }

    public static function getModelLabel(): string
    {
        return __('resources/target/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'Target',
            ['user_id' => auth()->id(), 'type' => 'total_count'],
            3600,
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
        return __('resources/target/strings.general.navigation_group');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTargets::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/target/strings.general.plural_model_label');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make()
                    ->schema([
                        static::viewTargetable(),
                        static::viewYear(),
                        static::viewStartFrom(),
                        static::viewEndIn(),
                        static::viewQuantity(),
                        static::viewAmount(),
                        static::viewMetrics(),
                        static::viewStatus(),
                        static::viewAchievedQuantity(),
                        static::viewAchievedAmount(),
                        static::viewDescription(),
                        static::viewTagsJson(),
                        static::viewCreator(),
                        static::viewUpdater(),
                        static::viewCreatedAt(),
                        static::viewUpdatedAt(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::showTargetable(),
                static::showYear(),
                static::showStartFrom(),
                static::showEndIn(),
                static::showQuantity(),
                static::showAmount(),
                static::showMetrics(),
                static::showTags(),
                static::showStatus(),
                static::showAchievedQuantity(),
                static::showAchievedAmount(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getYearFilter(),
                static::getStatusFilter(),
                static::getQuantityFilter(),
                static::getMetricsFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
                static::getTrashedFilter(),
            ])->filtersFormColumns(2)
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
                        ->exporter(TargetExporter::class),
                ])
            ])
            ->groups([
                Group::make('year')
                    ->label(__('resources/target/strings.table.year')),
                Group::make('status')
                    ->label(__('resources/target/strings.table.status'))
                    ->getTitleFromRecordUsing(fn(Target $record) => Status::tryFrom($record->status)?->getLabel()),
                Group::make('metrics')
                    ->label(__('resources/target/strings.table.metrics')),
            ])
            ->striped()
            ->defaultSort('id', 'desc');
    }
}
