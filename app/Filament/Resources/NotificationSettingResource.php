<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\NotificationSettingResource\Pages\ManageNotificationSettings;
use App\Filament\Resources\Master\NotificationSettingResource\Traits\Filters as NotificationSettingFilters;
use App\Filament\Resources\Master\NotificationSettingResource\Traits\Form as NotificationSettingForm;
use App\Filament\Resources\Master\NotificationSettingResource\Traits\Infolist as NotificationSettingInfolist;
use App\Filament\Resources\Master\NotificationSettingResource\Traits\Table as NotificationSettingTable;
use App\Filament\Traits\HasDeskReferenceAction;
use App\Filament\Traits\HasGlobalSearchConvention;
use App\Models\NotificationSetting;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NotificationSettingResource extends Resource
{
    use HasDeskReferenceAction, HasGlobalSearchConvention, NotificationSettingFilters, NotificationSettingForm, NotificationSettingInfolist, NotificationSettingTable;

    protected static ?string $model = NotificationSetting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bell';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::getTableSelector(),
                        static::getActionSelector(),
                        static::getColumnSelector(),
                        static::getColumnValueSelectors(),
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

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::ownerResponse($record);
    }

    public static function getUpdateAuthorizationResponse(Model $record): Response
    {
        return static::ownerResponse($record);
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::ownerResponse($record);
    }

    public static function getRestoreAuthorizationResponse(Model $record): Response
    {
        return static::ownerResponse($record);
    }

    public static function canEdit($record): bool
    {
        return static::getEditAuthorizationResponse($record)->allowed();
    }

    public static function canDelete($record): bool
    {
        return static::getDeleteAuthorizationResponse($record)->allowed();
    }

    public static function canRestore($record): bool
    {
        return static::getRestoreAuthorizationResponse($record)->allowed();
    }

    protected static function ownerResponse(Model $record): Response
    {
        return static::isOwnerOrRecipient($record) ? Response::allow() : Response::deny();
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

    public static function withPerColumnValues(array $data, Model $record): array
    {
        $data['settings'] = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $data['settings']['values'] = $record->getColumnValues();

        return $data;
    }

    public static function withSanitizedSettings(array $data): array
    {
        $data['settings'] = NotificationSetting::sanitizeSettings($data['settings'] ?? []);

        return $data;
    }

    protected static function restrictGlobalSearch(Builder $query): Builder
    {
        return $query->ownedOrReceivedBy(auth()->id());
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('resources/notificationSetting/strings.table.notification_type') => $record->notification_channel,
            __('resources/notificationSetting/strings.table.actions') => implode(', ', $record->getLocalizedActions()) ?: '—',
            __('resources/notificationSetting/strings.table.columns') => implode(', ', NotificationSetting::columnLabels($record->getTables(), $record->getColumns())) ?: '—',
        ];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return '🔔  '.(implode(', ', $record->getLocalizedTables()) ?: '—');
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index', ['search' => $record->getTables()[0] ?? '']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['settings->tables', 'settings->actions', 'settings->columns'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/notificationSetting/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.alerts');
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
                    ViewAction::make()
                        ->extraModalFooterActions(fn (): array => [DeleteAction::make(), CreateAction::make()->icon(Heroicon::Plus), static::getEditAction()]),
                    static::getEditAction(),
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
                    RestoreBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->searchDebounce('1000ms')
            ->recordUrl(null)
            ->defaultSort('id', 'desc'));
    }
}
