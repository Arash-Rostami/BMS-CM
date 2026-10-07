<?php

namespace App\Filament\Resources;

use App\Filament\Resources\General\TableComponents;
use App\Filament\Resources\Master\UserResource\Enums\UserStatus;
use App\Filament\Resources\Master\UserResource\Pages\ManageUsers;
use App\Filament\Resources\Master\UserResource\Traits\Filters;
use App\Filament\Resources\Master\UserResource\Traits\Form as UserForm;
use App\Filament\Resources\Master\UserResource\Traits\Infolist as UserInfolist;
use App\Filament\Resources\Master\UserResource\Traits\Table as TableTrait;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\User;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    use Filters, HasResourcePermissions, TableTrait, UserForm, UserInfolist;

    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 9;

    public static function getDeleteAuthorizationResponse($record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('User')
                    ->tabs([
                        Tab::make(__('resources/user/strings.form.tab_general'))
                            ->icon('heroicon-o-user')
                            ->schema([
                                static::getName(),
                                static::getPhoneInput(),
                                static::getEmail(),
                                static::getStatus(),
                                static::getPassword(),
                                static::getPasswordConfirmation(),
                                static::getIP(),
                                static::getLastLogIn(),
                                static::getLastLogOut(),
                            ])
                            ->columns(2),
                        Tab::make(__('resources/user/strings.form.tab_details'))
                            ->icon('heroicon-o-identification')
                            ->schema([
                                static::getCompany(),
                                static::getDepartment(),
                                static::getPosition(),
                                static::getRoles(),
                                Section::make('🔗')
                                    ->hiddenLabel()
                                    ->schema([static::getImage()])
                                    ->columnSpanFull()
                                    ->collapsed(),
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
                'department',
                'attachments',
            ])
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        $date = toYmdDate($record);
        $name = $record->name ?? '-';

        return "👨🏻‍💻 {$name} (📆 {$date})";
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index', ['search' => $record->name ?? '']);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getModelLabel(): string
    {
        return __('resources/user/strings.general.model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/dashboard/strings.navigation_group.base');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/user/strings.general.plural_model_label');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::viewImage(),
                        static::viewName(),
                        static::viewEmail(),
                        static::viewPhone(),
                        static::viewCompany(),
                        static::viewDepartment(),
                        static::viewPosition(),
                        static::viewRole(),
                        static::viewStatus(),
                        static::viewIP(),
                        static::viewLastLogIn(),
                        static::viewLastLogOut(),
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
                static::showImage(),
                static::showName(),
                static::showEmail(),
                static::showPhone(),
                static::showCompany(),
                static::showDepartment(),
                static::showPosition(),
                static::showRole(),
                static::showStatus(),
                static::showIP(),
                static::showLastLogIn(),
                static::showLastLogout(),
                static::showDeletionTime(),
                static::showCreationTime(),
                static::showUpdateTime(),
            ])
            ->filters([
                static::getCompanyFilter(),
                static::getDepartmentFilter(),
                static::getRoleFilter(),
                static::getPositionFilter(),
                static::getStatusFilter(),
                static::getStaleLoginFilter(),
                static::getThrashedFilter(),
            ])->filtersFormColumns(2)
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    static::getExportBulkAction(),
                    static::getActivateBulkAction(),
                    static::getDeactivateBulkAction(),
                ]),
            ])
            ->striped()
            ->recordClasses(fn (Model $record): ?string => ($record->status?->value ?? $record->status) === UserStatus::INACTIVE->value ? 'user-row-inactive' : null)
            ->reorderableColumns()
            ->defaultSort('id', 'desc'));
    }
}
