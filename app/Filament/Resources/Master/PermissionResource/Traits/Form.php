<?php

namespace App\Filament\Resources\Master\PermissionResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;

trait Form
{
    public static function getName(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/permission/strings.form.name'))
            ->required()
            ->unique(ignoreRecord: true)
            ->maxLength(255);
    }

    public static function getRoles(): Select
    {
        return Select::make('roles')
            ->label(__('resources/permission/strings.form.roles'))
            ->multiple()
            ->relationship('roles', 'name')
            ->getOptionLabelFromRecordUsing(fn (Model $record) => UserRole::tryFrom($record->name)?->getLabel() ?? $record->name)
            ->preload()
            ->searchable();
    }

    public static function getUsers(): Select
    {
        return Select::make('users')
            ->label(__('resources/permission/strings.form.users'))
            ->multiple()
            ->relationship('users', 'name')
            ->preload()
            ->searchable();
    }
}
