<?php

namespace App\Filament\Resources\Master\UserResource\Traits;

use App\Filament\Resources\Master\UserResource\Enums\PositionStatus;
use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Filament\Resources\Master\UserResource\Enums\UserStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Str;
use Ysfkaya\FilamentPhoneInput\Forms\PhoneInput;

trait Form
{
    public static function getCompany(): TextInput
    {
        return TextInput::make('company')
            ->label(__('resources/user/strings.form.company'))
            ->maxLength(255)
            ->nullable()
            ->validationAttribute(__('resources/user/strings.form.company'))
            ->validationMessages([
                'max' => __('resources/user/strings.form.validation_company_max'),
            ]);
    }

    public static function getDepartment(): Select
    {
        return Select::make('department_id')
            ->label(__('resources/user/strings.form.department'))
            ->relationship(
                name: 'department',
                titleAttribute: fn () => app()->getLocale() === 'fa' ? ('name' ?? 'english_name') : 'english_name',
                modifyQueryUsing: fn (Builder $query, ?Model $record) => $query->where(fn ($q) => $q
                    ->active()
                    ->when($record?->department_id, fn ($q) => $q->orWhere('id', $record->department_id))),
            )
            ->nullable()
            ->searchable()
            ->preload()
            ->validationMessages([
                'in' => __('resources/user/strings.form.validation_department_in'),
            ])
            ->helperText(__('resources/user/strings.form.helper_department'));
    }

    public static function getEmail(): TextInput
    {
        return TextInput::make('email')
            ->label(__('resources/user/strings.form.email'))
            ->email()
            ->required()
            ->maxLength(255)
            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())
            ->validationAttribute(__('resources/user/strings.form.email'))
            ->validationMessages([
                'email' => __('resources/user/strings.form.validation_email_email'),
                'required' => __('resources/user/strings.form.validation_email_required'),
                'unique' => __('resources/user/strings.form.validation_email_unique'),
                'max' => __('resources/user/strings.form.validation_email_max'),
            ]);
    }

    public static function getIP(): TextInput
    {
        return TextInput::make('ip')
            ->label(__('resources/user/strings.form.ip'))
            ->formatStateUsing(fn ($state, ?Model $record): string => ($state && $record) ? $record->user_country : '🌎')
            ->disabled()
            ->hiddenOn('create')
            ->visibleOn('view');
    }

    public static function getImage(): FileUpload
    {
        return FileUpload::make('image')
            ->label('🖼️')
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
            ->validationAttribute(__('resources/user/strings.form.image'))
            ->validationMessages([
                'max' => __('resources/user/strings.form.validation_image_max'),
                'mimetypes' => __('resources/user/strings.form.validation_image_mimetypes'),
            ])
            ->getUploadedFileNameForStorageUsing(
                fn (TemporaryUploadedFile $file): string => 'BMS-'.Str::uuid().'.'.$file->getClientOriginalExtension()
            );
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

    public static function getName(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/user/strings.form.name'))
            ->required()
            ->maxLength(255)
            ->validationAttribute(__('resources/user/strings.form.name'))
            ->validationMessages([
                'required' => __('resources/user/strings.form.validation_name_required'),
                'max' => __('resources/user/strings.form.validation_name_max'),
            ]);
    }

    public static function getPassword(): TextInput
    {
        return TextInput::make('password')
            ->label(__('resources/user/strings.form.password'))
            ->password()
            ->revealable()
            ->copyable(copyMessage: __('resources/user/strings.form.password_copy'), copyMessageDuration: 1500)
            ->dehydrated(fn (?string $state): bool => filled($state))
            ->required(fn (string $operation): bool => $operation === 'create')
            ->minLength(8)
            ->maxLength(255)
            ->helperText(__('resources/user/strings.form.helper_password'))
            ->validationAttribute(__('resources/user/strings.form.password'))
            ->validationMessages([
                'required' => __('resources/user/strings.form.validation_password_required'),
                'min' => __('resources/user/strings.form.validation_password_min'),
                'max' => __('resources/user/strings.form.validation_password_max'),
            ])
            ->visibleOn('create');
    }

    public static function getPasswordConfirmation(): TextInput
    {
        return TextInput::make('password_confirmation')
            ->label(__('resources/user/strings.form.password_confirmation'))
            ->password()
            ->revealable()
            ->minLength(8)
            ->maxLength(255)
            ->same('password')
            ->required(fn (string $operation): bool => $operation === 'create')
            ->helperText(__('resources/user/strings.form.helper_password_confirmation'))
            ->validationAttribute(__('resources/user/strings.form.password_confirmation'))
            ->validationMessages([
                'required' => __('resources/user/strings.form.validation_password_confirmation_required'),
                'min' => __('resources/user/strings.form.validation_password_confirmation_min'),
                'same' => __('resources/user/strings.form.validation_password_confirmation_same'),
                'max' => __('resources/user/strings.form.validation_password_confirmation_max'),
            ])
            ->visibleOn('create');
    }

    public static function getPhoneInput(): PhoneInput
    {
        return PhoneInput::make('phone')
            ->label(__('resources/user/strings.form.phone'))
            ->unique(ignoreRecord: true, modifyRuleUsing: fn ($rule) => $rule->withoutTrashed())
            ->ipLookup(fn () => rescue(fn () => Http::get('http://ip-api.com/json/')->json('country'), null, report: false))
            ->defaultCountry('IR')
            ->showFlags(true)
            ->autoPlaceholder('polite')
            ->required()
            ->validateFor(country: 'INTERNATIONAL', lenient: true)
            ->validationAttribute(__('resources/user/strings.form.phone'))
            ->validationMessages([
                'required' => __('resources/user/strings.form.validation_phone_required'),
                'unique' => __('resources/user/strings.form.validation_phone_unique'),
                'phone' => __('resources/user/strings.form.validation_phone_invalid'),
            ]);
    }

    public static function getPosition(): Select
    {
        return Select::make('position')
            ->label(__('resources/user/strings.form.position'))
            ->options(PositionStatus::class)
            ->nullable()
            ->validationMessages([
                'enum' => __('resources/user/strings.form.validation_position_in'),
            ])
            ->helperText(__('resources/user/strings.form.helper_position'));
    }

    public static function getRoles(): Select
    {
        return Select::make('roles')
            ->label(__('resources/user/strings.form.role'))
            ->relationship('roles', 'name')
            ->getOptionLabelFromRecordUsing(fn (Model $record) => UserRole::tryFrom($record->name)?->getLabel() ?? $record->name)
            ->multiple()
            ->preload()
            ->searchable()
            ->validationMessages([
                '*.in' => __('resources/user/strings.form.validation_roles_in'),
            ])
            ->helperText(__('resources/user/strings.form.helper_roles'));
    }

    public static function getStatus(): Select
    {
        return Select::make('status')
            ->label(__('resources/user/strings.form.status'))
            ->options(UserStatus::class)
            ->default(UserStatus::ACTIVE->value)
            ->required()
            ->validationAttribute(__('resources/user/strings.form.status'))
            ->validationMessages([
                'required' => __('resources/user/strings.form.validation_status_required'),
                'enum' => __('resources/user/strings.form.validation_status_in'),
            ])
            ->helperText(__('resources/user/strings.form.helper_status'));
    }
}
