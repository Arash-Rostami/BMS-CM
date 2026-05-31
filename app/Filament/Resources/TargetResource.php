<?php

namespace App\Filament\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ExportBulkAction;
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
use Filament\Infolists\Components;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TargetResource extends Resource
{
    use TargetForm, TargetTable, TargetInfolist, TargetFilters;

    protected static ?string $model = Target::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected static ?int $navigationSort = 7;


    public static function form(Schema $schema): Schema
    {

        return $schema
            ->components([
                Section::make()
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
                    ])
                    ->columnSpanFull()
                    ->columns(3),
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

    public static function getModelLabel(): string
    {
        return __('resources/target/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
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
                    ])
                    ->columnSpanFull()
                    ->columns(3),
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
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
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
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
