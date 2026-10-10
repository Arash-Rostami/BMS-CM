<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\StatusResource\Pages\ManageStatuses;
use App\Filament\Resources\Master\StatusResource\Traits\Filters as StatusFilters;
use App\Filament\Resources\Master\StatusResource\Traits\Form as StatusForm;
use App\Filament\Resources\Master\StatusResource\Traits\Infolist as StatusInfolist;
use App\Filament\Resources\Master\StatusResource\Traits\Table as StatusTable;
use App\Filament\Traits\HasGlobalSearchConvention;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\Status;
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

class StatusResource extends Resource
{
    use HasGlobalSearchConvention, HasResourcePermissions, StatusFilters, StatusForm, StatusInfolist, StatusTable;

    protected static ?string $model = Status::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Status')
                    ->tabs([
                        Tab::make(__('resources/status/strings.form.tab_general'))
                            ->icon('heroicon-o-tag')
                            ->schema([
                                static::getType(),
                                static::getCustomType(),
                                static::getTypeCustomField(),
                                static::getEnglishType(),
                                static::getCustomEnglishType(),
                                static::getEnglishTypeCustomField(),
                                static::getName(),
                                static::getEnglishName(),
                            ])
                            ->columns(2),
                        Tab::make(__('resources/status/strings.form.tab_approval_workflow'))
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                static::getStageOrderField(),
                                static::getRequiresApprovalField(),
                                static::getApprovalUsersField(),
                            ])
                            ->columns(2),
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
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $name = $record->getLocalizedNameAttribute() ?? '-';

        return __('resources/status/strings.general.global_search_title', [
            'name' => $name,
            'date' => $date,
        ]);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('resources/status/strings.table.type') => $record->type ?? '—',
            __('resources/status/strings.table.english_name') => $record->english_name ?? '—',
            __('resources/status/strings.table.stage_order') => $record->stage_order ?? '—',
        ];
    }

    public static function getModelLabel(): string
    {
        return __('resources/status/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStatuses::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/status/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::viewType(),
                        static::viewEnglishType(),
                        static::viewName(),
                        static::viewEnglishName(),
                        static::viewStageOrder(),
                        static::viewApprovalGate(),
                        static::viewApprovalUsers(),
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
                static::showType(),
                static::showEnglishType(),
                static::showName(),
                static::showEnglishName(),
                static::showStageOrder(),
                static::showApprovalGate(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getTypeFilter(),
                static::getThrashedFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make()
                        ->mutateDataUsing(fn (array $data, ?Status $record): array => static::processApprovalWorkflow($data, $record))
                        ->after(fn (array $data) => static::syncApprovalUsers($data)),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::getExportBulkAction(),
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->defaultSort('id', 'desc'));
    }
}
