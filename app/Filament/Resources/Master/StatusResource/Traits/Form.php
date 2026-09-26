<?php

namespace App\Filament\Resources\Master\StatusResource\Traits;

use App\Models\Permission;
use App\Models\Status;
use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

trait Form
{
    public static function getApprovalUsersField(): Select
    {
        return Select::make('approval_users')
            ->label(__('resources/status/strings.form.approval_users'))
            ->helperText(__('resources/status/strings.form.helper_approval_users'))
            ->multiple()
            ->searchable()
            ->preload()
            ->options(fn () => User::query()->orderBy('name')->get()
                ->mapWithKeys(fn (User $user) => [$user->id => "{$user->name} ({$user->email})"]))
            ->visible(fn (Get $get) => (bool) $get('requires_approval'))
            ->afterStateHydrated(function (Select $component, ?Status $record) {
                if (! $record?->approval_permission) {
                    return;
                }

                $component->state(
                    Permission::where('name', $record->approval_permission)->first()?->users()->pluck('id')->toArray() ?? []
                );
            });
    }

    public static function getRequiresApprovalField(): Toggle
    {
        return Toggle::make('requires_approval')
            ->label(__('resources/status/strings.form.requires_approval'))
            ->helperText(__('resources/status/strings.form.helper_requires_approval'))
            ->live()
            ->afterStateHydrated(fn (Toggle $component, ?Status $record) => $component->state(filled($record?->approval_permission)));
    }

    public static function getStageOrderField(): TextInput
    {
        return TextInput::make('stage_order')
            ->label(__('resources/status/strings.form.stage_order'))
            ->helperText(__('resources/status/strings.form.helper_stage_order'))
            ->numeric()
            ->minValue(1)
            ->nullable();
    }

    public static function processApprovalWorkflow(array $data, ?Status $record = null): array
    {
        $data['previous_approval_permission'] = $record?->approval_permission;

        if (! ($data['requires_approval'] ?? false)) {
            $data['approval_permission'] = null;

            return $data;
        }

        $permissionName = $record?->approval_permission
            ?: static::generateApprovalPermissionName($data['english_type'] ?? $record?->english_type, $data['english_name'] ?? $record?->english_name);

        Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);

        $data['approval_permission'] = $permissionName;

        return $data;
    }

    public static function syncApprovalUsers(array $data): void
    {
        $previousPermission = $data['previous_approval_permission'] ?? null;
        $currentPermission = $data['approval_permission'] ?? null;

        if ($previousPermission && $previousPermission !== $currentPermission) {
            Permission::where('name', $previousPermission)->first()?->users()->sync([]);
        }

        if (! $currentPermission) {
            return;
        }

        Permission::where('name', $currentPermission)->first()?->users()->sync(
            collect($data['approval_users'] ?? [])->map(fn ($id) => (int) $id)->all()
        );
    }

    protected static function generateApprovalPermissionName(?string $englishType, ?string $englishName): string
    {
        $base = 'status.grant_'.Str::snake((string) $englishType).'_'.Str::snake((string) $englishName);
        $name = $base;
        $suffix = 2;

        while (Permission::where('name', $name)->exists()) {
            $name = "{$base}_{$suffix}";
            $suffix++;
        }

        return $name;
    }

    public static function getCustomEnglishType(): Hidden
    {
        return Hidden::make('custom_english_type')
            ->default(false);
    }

    public static function getCustomType(): Hidden
    {
        return Hidden::make('custom_type')
            ->default(false);
    }

    public static function getEnglishName(): TextInput
    {
        return TextInput::make('english_name')
            ->label(__('resources/status/strings.form.english_name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[A-Za-z\s\p{P}\d\*]+$/')
            ->unique(
                column: 'english_name',
                ignoreRecord: true,
                modifyRuleUsing: fn ($rule, $get) => $rule->where('english_type', $get('english_type'))->withoutTrashed()
            )
            ->placeholder(__('resources/status/strings.form.validation_english_name'))
            ->validationMessages([
                'required' => __('resources/status/strings.form.validation_english_name_required'),
                'regex' => __('resources/status/strings.form.validation_english_name'),
                'unique' => __('resources/status/strings.form.validation_english_name_unique'),
                'max' => __('resources/status/strings.form.validation_english_name_max'),
            ])
            ->validationAttribute(__('resources/status/strings.form.english_name'));
    }

    public static function getEnglishType(): Select
    {
        return Select::make('english_type')
            ->label(__('resources/status/strings.form.english_type'))
            ->options(fn () => Status::query()
                ->distinct()
                ->pluck('english_type', 'english_type')
                ->toArray() + ['write' => __('resources/status/strings.form.english_custom')]
            )
            ->live()
            ->afterStateUpdated(function (Set $set, $state) {
                if ($state === 'write') {
                    $set('custom_type', true);
                    $set('custom_english_type', true);
                } else {
                    $set('custom_english_type', false);
                    $farsi = Status::where('english_type', $state)->value('type');
                    $set('type', $farsi);
                    $set('custom_type', false);
                }
            })
            ->required(fn (Get $get) => ! $get('custom_english_type'))
            ->validationAttribute(__('resources/status/strings.form.english_type'))
            ->validationMessages([
                'required' => __('resources/status/strings.form.validation_english_type_required'),
            ])
            ->visible(fn (Get $get) => ! $get('custom_english_type'))
            ->helperText(__('resources/status/strings.form.helper_english_type'));
    }

    public static function getEnglishTypeCustomField(): TextInput
    {
        return TextInput::make('english_type_custom')
            ->label(__('resources/status/strings.form.english_type').' ('.__('resources/status/strings.form.custom').')')
            ->required(fn (Get $get) => $get('custom_english_type'))
            ->validationAttribute(__('resources/status/strings.form.english_type'))
            ->validationMessages([
                'required' => __('resources/status/strings.form.validation_english_type_custom_required'),
            ])
            ->visible(fn (Get $get) => $get('custom_english_type'));
    }

    public static function getName(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/status/strings.form.name'))
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[\x{0600}-\x{06FF}\s\p{P}\d\*]+$/u')
            ->unique(
                column: 'name',
                ignoreRecord: true,
                modifyRuleUsing: fn ($rule, $get) => $rule->where('type', $get('type'))->withoutTrashed()
            )
            ->placeholder(__('resources/status/strings.form.validation_name'))
            ->helperText(__('resources/status/strings.form.helper_name'))
            ->validationMessages([
                'required' => __('resources/status/strings.form.validation_name_required'),
                'regex' => __('resources/status/strings.form.validation_name'),
                'unique' => __('resources/status/strings.form.validation_name_unique'),
                'max' => __('resources/status/strings.form.validation_name_max'),
            ])
            ->validationAttribute(__('resources/status/strings.form.name'));
    }

    public static function getType(): Select
    {
        return Select::make('type')
            ->label(__('resources/status/strings.form.type'))
            ->options(fn () => Status::query()
                ->distinct()
                ->pluck('type', 'type')
                ->toArray() + ['write' => __('resources/status/strings.form.custom')]
            )
            ->live()
            ->afterStateUpdated(function (Set $set, $state) {
                if ($state === 'write') {
                    $set('custom_type', true);
                    $set('custom_english_type', true);
                } else {
                    $set('custom_type', false);
                    $english = Status::where('type', $state)->value('english_type');
                    $set('english_type', $english);
                    $set('custom_english_type', false);
                }
            })
            ->required(fn (Get $get) => ! $get('custom_type'))
            ->validationAttribute(__('resources/status/strings.form.type'))
            ->validationMessages([
                'required' => __('resources/status/strings.form.validation_type_required'),
            ])
            ->visible(fn (Get $get) => ! $get('custom_type'))
            ->helperText(__('resources/status/strings.form.helper_type'));
    }

    public static function getTypeCustomField(): TextInput
    {
        return TextInput::make('type_custom')
            ->label(__('resources/status/strings.form.type').' ('.__('resources/status/strings.form.custom').')')
            ->required(fn (Get $get) => $get('custom_type'))
            ->validationAttribute(__('resources/status/strings.form.type'))
            ->validationMessages([
                'required' => __('resources/status/strings.form.validation_type_custom_required'),
            ])
            ->visible(fn (Get $get) => $get('custom_type'));
    }
}
