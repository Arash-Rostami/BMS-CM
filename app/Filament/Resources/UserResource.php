<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\UserResource\Exports\UserExporter;
use App\Filament\Resources\Master\UserResource\Pages\ManageUsers;
use App\Filament\Resources\Master\UserResource\Traits\Filters;
use App\Filament\Resources\Master\UserResource\Traits\Form as UserForm;
use App\Filament\Resources\Master\UserResource\Traits\Infolist as UserInfolist;
use App\Filament\Resources\Master\UserResource\Traits\Table as TableTrait;
use App\Filament\Traits\HasResourcePermissions;
use App\Models\User;
use App\Services\SmartCacheManager;
use BackedEnum;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
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

class UserResource extends Resource
{
    use UserForm, TableTrait, UserInfolist, Filters, HasResourcePermissions;

    protected static ?string $model = User::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 8;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        static::getName(),
                        static::getPhoneInput(),
                        static::getEmail(),
                        static::getCompany(),
                        static::getDepartment(),
                        static::getPosition(),
                        static::getPassword(),
                        static::getPasswordConfirmation(),
                        static::getRoles(),
                        static::getStatus(),
                        static::getIP(),
                        static::getLastLogIn(),
                        static::getLastLogOut(),
                        Section::make('🔗')
                            ->hiddenLabel()
                            ->schema([static::getImage()])
                            ->columnSpanFull()
                            ->collapsed(),
                    ])
                    ->columnSpanFull()
                    ->columns(2),
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

    public static function getNavigationBadge(): ?string
    {
        $count = SmartCacheManager::remember(
            'User',
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
        return $table
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
                static::getThrashedFilter()
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
                    RestoreBulkAction::make(),
                    ExportBulkAction::make()
                        ->exporter(UserExporter::class)
                ]),
            ])
            ->striped()
            ->reorderableColumns()
            ->defaultSort('id', 'desc');
    }
}
