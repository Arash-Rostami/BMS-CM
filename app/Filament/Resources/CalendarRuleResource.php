<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\CalendarRuleResource\Pages\ManageCalendarRules;
use App\Filament\Resources\Master\CalendarRuleResource\Traits\Filters as RuleFilters;
use App\Filament\Resources\Master\CalendarRuleResource\Traits\Form as RuleForm;
use App\Filament\Resources\Master\CalendarRuleResource\Traits\Infolist as RuleInfolist;
use App\Filament\Resources\Master\CalendarRuleResource\Traits\Table as RuleTable;
use App\Filament\Traits\HasDeskReferenceAction;
use App\Filament\Traits\HasGlobalSearchConvention;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\CalendarRule;
use App\Services\Calendar\CalendarModules;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CalendarRuleResource extends Resource
{
    use HasDeskReferenceAction, HasGlobalSearchConvention, HasResourcePermissions, RuleFilters, RuleForm, RuleInfolist, RuleTable;

    protected static ?string $model = CalendarRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?int $navigationSort = 2;

    public static function canEdit($record): bool
    {
        return static::allows('edit') && static::currentUserMayEditRecord($record);
    }

    public static function canDelete($record): bool
    {
        return static::allows('delete') && static::currentUserMayEditRecord($record);
    }

    public static function canRestore($record): bool
    {
        return static::allows('restore') && static::currentUserMayEditRecord($record);
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return static::guardedResponse('edit', $record);
    }

    public static function getUpdateAuthorizationResponse(Model $record): Response
    {
        return static::guardedResponse('edit', $record);
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return static::guardedResponse('delete', $record);
    }

    public static function getRestoreAuthorizationResponse(Model $record): Response
    {
        return static::guardedResponse('restore', $record);
    }

    public static function assertNotDuplicate(array $data, ?int $ignoreId): array
    {
        $existing = static::findDuplicate($data, $ignoreId);

        if ($existing !== null) {
            Notification::make()
                ->title(__('resources/calendarRule/strings.actions.duplicate_exists'))
                ->danger()
                ->persistent()
                ->actions([
                    Action::make('useExisting')
                        ->label(__('resources/calendarRule/strings.actions.use_existing'))
                        ->button()
                        ->url(static::getUrl('index', [
                            'tableAction' => 'edit',
                            'tableActionRecord' => $existing->id,
                        ])),
                ])
                ->send();

            throw new Halt;
        }

        return $data;
    }

    public static function duplicateExists(array $data, ?int $ignoreId): bool
    {
        return static::findDuplicate($data, $ignoreId) !== null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('CalendarRule')
                    ->tabs([
                        Tab::make(__('resources/calendarRule/strings.form.tab_rule'))
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                static::getNameField(),
                                static::getTypeField(),
                                static::getNotificationTypeField(),
                                static::getSubjectField(),
                                static::getDatePathField(),
                                static::getDayShiftField(),
                                static::getLeadTimesField(),
                                static::getVisibilityField(),
                                static::getSharedUsersField(),
                                static::getSharedRolesField(),
                                static::getNotifyEmailsField(),
                                static::getColorField(),
                                static::getOnDayField(),
                                static::getIsActiveField(),
                            ])
                            ->columns(3),
                        Tab::make(__('resources/calendarRule/strings.form.tab_conditions'))
                            ->icon('heroicon-o-funnel')
                            ->schema([
                                static::getTableField()->columnSpanFull(),
                                static::getFiltersField(),
                                static::getPreviewField()->columnSpanFull(),
                                static::getSimilarRuleField()->columnSpanFull(),
                            ])
                            ->columns(3),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['creator', 'updater'])
            ->withCount(['hits' => static::scopedHits(...)])
            ->withMin(['hits' => static::scopedHits(...)], 'event_date')
            ->visibleTo(auth()->user())
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "📅  {$record->name}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('resources/calendarRule/strings.table.subject') => CalendarModules::label($record->subject),
            __('resources/calendarRule/strings.table.type') => $record->type?->getLabel() ?? '—',
            __('resources/calendarRule/strings.table.notification_type') => $record->notification_channel,
        ];
    }

    public static function getModelLabel(): string
    {
        return __('resources/calendarRule/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.alerts');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCalendarRules::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/calendarRule/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::viewName(),
                        static::viewSubject(),
                        static::viewFilters(),
                        static::viewDatePath(),
                        static::viewDayShift(),
                        static::viewLeadTimes(),
                        static::viewOnDay(),
                        static::viewType(),
                        static::viewColor(),
                        static::viewNotificationType(),
                        static::viewVisibility(),
                        static::viewSharedUserIds(),
                        static::viewSharedRoleIds(),
                        static::viewNotifyEmails(),
                        static::viewIsActive(),
                        static::viewHitsCount(),
                        static::viewCreator(),
                        static::viewUpdater(),
                        static::viewCreatedAt(),
                        static::viewUpdatedAt(),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return TableComponents::emptyState($table
            ->columns([
                static::showName(),
                static::showSubject(),
                static::showWatches(),
                static::showType(),
                static::showNotificationType(),
                static::showVisibility(),
                static::showHitsCount(),
                static::showNextDue(),
                static::showIsActive(),
                static::showCreator(),
                static::showUpdater(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getSubjectFilter(),
                static::getTypeFilter(),
                static::getVisibilityFilter(),
                static::getNotificationChannelFilter(),
                static::getCreatorFilter(),
                static::getUpdaterFilter(),
                static::getIsActiveFilter(),
                static::getTrashedFilter(),
            ])->filtersFormColumns(2)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->modalWidth('4xl')
                        ->extraModalFooterActions(fn (): array => [DeleteAction::make(), CreateAction::make()->icon(Heroicon::Plus), static::getEditAction()]),
                    static::getSeeOnCalendarAction(),
                    static::getEditAction(),
                    static::getDuplicateAction(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::getExportBulkAction(),
                    static::getActivateBulkAction(),
                    static::getDeactivateBulkAction(),
                    DeleteBulkAction::make()->authorizeIndividualRecords(),
                    RestoreBulkAction::make()->authorizeIndividualRecords(),
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->defaultSort('id', 'desc'));
    }

    private static function scopedHits(Builder $query): Builder
    {
        return $query->visibleTo(auth()->user())->notPastHeadsUp();
    }

    private static function findDuplicate(array $data, ?int $ignoreId): ?CalendarRule
    {
        $candidate = static::getModel()::make()->forceFill([
            'subject' => (string) ($data['subject'] ?? ''),
            'filters' => (array) ($data['filters'] ?? []),
            'date_path' => (string) ($data['date_path'] ?? ''),
            'day_shift' => (int) ($data['day_shift'] ?? 0),
            'type' => $data['type'] ?? null,
        ]);
        $candidate->computeFingerprints();

        return static::getModel()::query()
            ->visibleTo(auth()->user())
            ->where('fingerprint', $candidate->fingerprint)
            ->when($ignoreId !== null, fn (Builder $query): Builder => $query->whereKeyNot($ignoreId))
            ->orderBy('id')
            ->first();
    }

    private static function currentUserMayEditRecord($record): bool
    {
        $user = auth()->user();

        return $user !== null
            && $record instanceof CalendarRule
            && $record->isEditableBy($user);
    }

    private static function guardedResponse(string $action, Model $record): Response
    {
        return static::allows($action) && static::currentUserMayEditRecord($record)
            ? Response::allow()
            : Response::deny();
    }
}
