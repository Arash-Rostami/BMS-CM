<?php

namespace App\Filament\Resources\Master\UserResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\PositionStatus;
use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Filament\Resources\Master\UserResource\Enums\UserStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Rawilk\FilamentPasswordInput\Password;
use Str;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

trait Form
{
    public static function getName(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/user/strings.form.name'))
            ->required()
            ->maxLength(255);
    }

    public static function getPhoneInput(): PhoneInput
    {
        return PhoneInput::make('phone')
            ->label(__('resources/user/strings.form.phone'))
            ->unique(ignoreRecord: true)
            ->ipLookup(fn() => rescue(fn() => Http::get('http://ip-api.com/json/')->json('country'), null, report: false))
            ->defaultCountry('IR')
            ->showFlags(true)
            ->autoPlaceholder('polite')
            ->required();
    }


    public static function getEmail(): TextInput
    {
        return TextInput::make('email')
            ->label(__('resources/user/strings.form.email'))
            ->email()
            ->required()
            ->maxLength(255)
            ->unique(ignoreRecord: true);
    }


    public static function getCompany(): TextInput
    {
        return TextInput::make('company')
            ->label(__('resources/user/strings.form.company'))
            ->maxLength(255)
            ->nullable();
    }


    public static function getDepartment(): Select
    {
        return Select::make('department_id')
            ->label(__('resources/user/strings.form.department'))
            ->relationship(
                name: 'department',
                titleAttribute: fn() => app()->getLocale() === 'fa' ? ('name' ?? 'english_name') : 'english_name')
            ->nullable()
            ->searchable()
            ->preload();
    }


    public static function getPosition(): Select
    {
        return Select::make('position')
            ->label(__('resources/user/strings.form.position'))
            ->options(PositionStatus::class)
            ->nullable();
    }


    public static function getPassword(): Password
    {
        return Password::make('password')
            ->label(__('resources/user/strings.form.password'))
            ->password()
            ->copyable()
            ->copyMessage(__('resources/user/strings.form.password_copy'))
            ->regeneratePassword()
            ->dehydrated(fn(?string $state): bool => filled($state))
            ->required(fn(string $operation): bool => $operation === 'create')
            ->minLength(8)
            ->maxLength(255)
            ->visibleOn('create');
    }


    public static function getPasswordConfirmation(): Password
    {
        return Password::make('password_confirmation')
            ->label(__('resources/user/strings.form.password_confirmation'))
            ->password()
            ->minLength(8)
            ->maxLength(255)
            ->same('password')
            ->required(fn(string $operation): bool => $operation === 'create')
            ->visibleOn('create');
    }

    public static function getRole(): Select
    {
        return Select::make('role')
            ->label(__('resources/user/strings.form.role'))
            ->options(UserRole::class)
            ->required();
    }


    public static function getStatus(): Select
    {
        return Select::make('status')
            ->label(__('resources/user/strings.form.status'))
            ->options(UserStatus::class)
            ->required();
    }


    public static function getImage(): FileUpload
    {
        return FileUpload::make('image')
            ->label('User Photo')
            ->image()
            ->disk('public')
            ->directory('user-images')
            ->visibility('public')
            ->downloadable()
            ->openable()
            ->previewable()
            ->maxSize(2048)
            ->imageEditor()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'])
            ->nullable()
            ->getUploadedFileNameForStorageUsing(
                fn(TemporaryUploadedFile $file): string =>
                    'BMS-' . Str::uuid() . '.' . $file->getClientOriginalExtension()
            );
    }


    public static function getIP(): TextInput
    {
        return TextInput::make('ip')
            ->label(__('resources/user/strings.form.ip'))
            ->formatStateUsing(fn($state, ?Model $record): string => ($state && $record) ? $record->user_country : '🌎')
            ->disabled()
            ->hiddenOn('create')
            ->visibleOn('view');
    }


    public static function getLastLogIn(): DateTimePicker
    {
        return DateTimePicker::make('last_log_in')
            ->label(__('resources/user/strings.form.last_log_in'))
            ->disabled()
            ->visibleOn('view')
            ->displayFormat('d/m/Y');
    }


    public static function getLastLogOut(): DateTimePicker
    {
        return DateTimePicker::make('last_log_out')
            ->label(__('resources/user/strings.form.last_log_out'))
            ->disabled()
            ->visibleOn('view');
    }
}
