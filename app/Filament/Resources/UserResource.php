<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Master\UserResource\Exports\UserExporter;
use App\Filament\Resources\Master\UserResource\Traits\Form as UserForm;
use App\Filament\Resources\Master\UserResource\Traits\Table as TableTrait;
use App\Filament\Resources\Master\UserResource\Traits\InfoList as UserInfolist;
use App\Filament\Resources\Master\UserResource\Traits\Filters;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class UserResource extends Resource
{
    use UserForm, TableTrait, UserInfolist, Filters;

    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        static::getName(),
                        static::getPhoneInput(),
                        static::getEmail(),
                        static::getCompany(),
                        static::getDepartment(),
                        static::getPosition(),
                        static::getPassword(),
                        static::getPasswordConfirmation(),
                        static::getRole(),
                        static::getStatus(),
                        static::getImage(),
                        static::getIP(),
                        static::getLastLogIn(),
                        static::getLastLogOut(),
                    ])->columns(2),

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
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                    Tables\Actions\RestoreAction::make(),
//                    Tables\Actions\Action::make('showModal')
//                        ->label('Show Modal')
//                        ->icon('heroicon-o-information-circle')
//                        ->action(function ( $action, $record) {
//                            session()->flash('user', $record->name);
//                            return redirect()->to(route('filament.dashboard.resources.users.index'));
//                        }),
                ])
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(UserExporter::class)
                ]),
            ])
            ->striped()
            ->defaultSort('id', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make()
                    ->schema([
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
                        static::viewImage(),
                    ])->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Master\UserResource\Pages\ManageUsers::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getModelLabel(): string
    {
        return __('resources/user/strings.general.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('resources/user/strings.general.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('resources/user/strings.general.navigation_group');
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "👨🏻‍💻 " . $record->name;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
}
