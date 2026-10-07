<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\NotificationSettingResource\Pages\ManageNotificationSettings;
use App\Filament\Resources\Master\NotificationSettingResource\Traits\Filters as NotificationSettingFilters;
use App\Filament\Resources\Master\NotificationSettingResource\Traits\Form as NotificationSettingForm;
use App\Filament\Resources\Master\NotificationSettingResource\Traits\Infolist as NotificationSettingInfolist;
use App\Filament\Resources\Master\NotificationSettingResource\Traits\Table as NotificationSettingTable;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\SmartCacheManager;
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
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Str;

class NotificationSettingResource extends Resource
{
    use NotificationSettingFilters, NotificationSettingForm, NotificationSettingInfolist, NotificationSettingTable;

    protected static ?string $model = NotificationSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static ?int $navigationSort = 12;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::getTableSelector(),
                        static::getActionSelector(),
                        static::getColumnSelector(),
                        static::getColumnValueSelector(),
                        static::getUserSelector(),
                        static::getNotificationChannel(),
                        static::getIsActive(),
                        static::getNotes(),
                    ])
                    ->columns(2)
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

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::isOwnerOrRecipient($record) ? Response::allow() : Response::deny();
    }

    public static function canDelete($record): bool
    {
        return static::getDeleteAuthorizationResponse($record)->allowed();
    }

    protected static function isOwnerOrRecipient(Model $record): bool
    {
        $userId = auth()->id();

        if ($userId === null) {
            return false;
        }

        if ((int) $record->user_id === (int) $userId) {
            return true;
        }

        return in_array((int) $userId, array_map('intval', $record->getUsers()), true);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $details = [];

        if ($columns = $record->getColumns()) {
            $details[] = 'Columns: '.implode(', ', array_map(fn ($c) => Str::headline($c), $columns));
        }

        if ($users = $record->getUsers()) {
            $userNames = User::whereIn('id', $users)->pluck('name')->join(', ');
            $details[] = 'Recipients: '.$userNames;
        }

        return $details;
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $tables = implode(', ', array_map(fn ($t) => Str::headline($t), $record->getTables()));
        $actions = implode(', ', array_map('ucfirst', $record->getActions()));

        return "🔔 {$tables} · {$actions}";
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        $tables = $record->getTables();

        return static::getUrl('index', ['search' => ! empty($tables) ? $tables[0] : '']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['settings->tables', 'settings->actions', 'settings->columns'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/notificationSetting/strings.general.model_label');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'NotificationSetting',
            ['user_id' => auth()->id(), 'type' => 'total_count'],
            3600,
            fn () => static::getModel()::count()
        );

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageNotificationSettings::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/notificationSetting/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::viewTables(),
                        static::viewActions(),
                        static::viewColumns(),
                        static::viewColumnValues(),
                        static::viewUsers(),
                        static::viewNotificationChannel(),
                        static::viewIsActive(),
                        static::viewNotes(),
                        static::viewCreator(),
                        static::viewUpdater(),
                        static::viewCreatedAt(),
                        static::viewUpdatedAt(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->columns([
                static::showTable(),
                static::showColumns(),
                static::showColumnValues(),
                static::showActions(),
                static::showRecipients(),
                static::showNotificationChannel(),
                static::showIsActive(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getActionsFilter(),
                static::getTablesFilter(),
                static::getNotificationChannelFilter(),
                static::getIsActiveFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
                static::getTrashedFilter(),
                static::getMineFilter(),
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
            ->groups([
                Group::make('settings->actions')
                    ->getTitleFromRecordUsing(fn ($record) => implode(', ', $record->getLocalizedActions()))
                    ->label(__('resources/notificationSetting/strings.table.actions'))
                    ->collapsible(),
                Group::make('settings->tables')
                    ->getTitleFromRecordUsing(fn ($record) => implode(', ', $record->getLocalizedTables()))
                    ->label(__('resources/notificationSetting/strings.table.tables'))
                    ->collapsible(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::getExportBulkAction(),
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->defaultSort('id', 'desc'));
    }
}
