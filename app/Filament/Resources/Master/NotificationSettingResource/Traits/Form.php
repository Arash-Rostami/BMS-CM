<?php

namespace App\Filament\Resources\Master\NotificationSettingResource\Traits;

use App\Models\NotificationSetting;
use App\Models\User;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;

trait Form
{
    public static function getActionSelector(): Select
    {
        return Select::make('settings.actions')
            ->label(__('resources/notificationSetting/strings.form.actions'))
            ->options([
                'create' => __('resources/notificationSetting/strings.action_types.create'),
                'update' => __('resources/notificationSetting/strings.action_types.update'),
                'delete' => __('resources/notificationSetting/strings.action_types.delete'),
            ])
            ->default('create')
            ->live()
            ->multiple()
            ->rule('array')
            ->required()
            ->columnSpan(1)
            ->columnSpanFull()
            ->validationMessages([
                'required' => __('resources/notificationSetting/strings.form.validation_required'),
                '*.in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_actions'));
    }

    public static function getColumnSelector(): Select
    {
        return Select::make('settings.columns')
            ->label(__('resources/notificationSetting/strings.form.columns'))
            ->options(fn ($get) => NotificationSetting::getColumnsForSelectedTables($get('settings.tables')))
            ->multiple()
            ->rule('array')
            ->columnSpan(1)
            ->columnSpanFull()
            ->live()
            ->searchable()
            ->disabled(fn ($get) => ! in_array('update', (array) $get('settings.actions')))
            ->hidden(fn ($get) => ! in_array('update', (array) $get('settings.actions')))
            ->nullable()
            ->validationMessages([
                '*.in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_columns'));
    }

    public static function getColumnValueSelectors(): Grid
    {
        return Grid::make(1)
            ->columnSpanFull()
            ->hidden(fn ($get) => ! in_array('update', (array) $get('settings.actions')))
            ->schema(fn ($get): array => collect((array) $get('settings.columns'))
                ->filter(fn ($column) => is_string($column) && ! NotificationSetting::isSensitiveColumn($column) && ! collect((array) $get('settings.tables'))->contains(fn ($table) => is_string($table) && NotificationSetting::isMorphIdColumn($table, $column)))
                ->map(fn (string $column) => match (true) {
                    NotificationSetting::isForeignKeyColumn($get('settings.tables'), $column) => static::getForeignKeyValueSelector($column),
                    NotificationSetting::predefinedOptions($get('settings.tables'), $column) !== null => static::getPredefinedValueSelector($column),
                    default => static::getColumnValueSelector($column),
                })
                ->values()
                ->all());
    }

    public static function getColumnValueSelector(string $column): Select
    {
        return Select::make('settings.values.'.$column)
            ->label(fn ($get) => __('resources/notificationSetting/strings.form.column_values_for', ['column' => NotificationSetting::columnLabel($get('settings.tables'), $column)]))
            ->options(fn ($get) => NotificationSetting::valueChoices($get('settings.tables'), $column))
            ->getSearchResultsUsing(fn (string $search, $get) => NotificationSetting::valueChoices($get('settings.tables'), $column, $search))
            ->getOptionLabelsUsing(fn (array $values, $get) => NotificationSetting::typedValueLabels($get('settings.tables'), $column, $values))
            ->createOptionForm(fn ($get) => [static::getNewValueField($get('settings.tables'), $column)])
            ->createOptionUsing(fn (array $data, $get) => NotificationSetting::canonicalTypedValue($get('settings.tables'), $column, (string) ($data['value'] ?? '')))
            ->multiple()
            ->rule('array')
            ->rule('max:'.NotificationSetting::VALUES_PER_COLUMN_LIMIT)
            ->columnSpanFull()
            ->searchable()
            ->nullable()
            ->validationMessages([
                'max' => __('resources/notificationSetting/strings.form.validation_value_max'),
                '*.in' => __('resources/notificationSetting/strings.form.validation_value_invalid'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_column_values_typed'));
    }

    public static function getPredefinedValueSelector(string $column): Select
    {
        return Select::make('settings.values.'.$column)
            ->label(fn ($get) => __('resources/notificationSetting/strings.form.column_values_for', ['column' => NotificationSetting::columnLabel($get('settings.tables'), $column)]))
            ->options(fn ($get) => NotificationSetting::predefinedOptions($get('settings.tables'), $column) ?? [])
            ->multiple()
            ->rule('array')
            ->columnSpanFull()
            ->searchable()
            ->nullable()
            ->validationMessages([
                '*.in' => __('resources/notificationSetting/strings.form.validation_value_options'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_column_values_options'));
    }

    public static function getNewValueField(mixed $tables, string $column): Component
    {
        $field = match (NotificationSetting::columnKind($tables, $column)) {
            'date', 'datetime' => DatePicker::make('value')->native(false)->adaptive(),
            'number' => str_ends_with($column, '_id') ? TextInput::make('value')->numeric()->integer()->minValue(1) : TextInput::make('value')->numeric(),
            'boolean' => Select::make('value')->options(NotificationSetting::valueChoices($tables, $column)),
            default => TextInput::make('value')->maxLength(NotificationSetting::VALUE_LENGTH_LIMIT),
        };

        return $field
            ->label(__('resources/notificationSetting/strings.form.new_value'))
            ->required()
            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) use ($tables, $column): void {
                if (! NotificationSetting::isValidTypedValue($tables, $column, $value)) {
                    $fail(__('resources/notificationSetting/strings.form.validation_value_invalid'));
                }
            });
    }

    public static function getForeignKeyValueSelector(string $column): Select
    {
        return Select::make('settings.values.'.$column)
            ->label(fn ($get) => __('resources/notificationSetting/strings.form.column_values_for', ['column' => NotificationSetting::columnLabel($get('settings.tables'), $column)]))
            ->options(fn ($get) => NotificationSetting::getColumnValueOptions($get('settings.tables'), $column))
            ->getSearchResultsUsing(fn (string $search, $get) => NotificationSetting::searchForeignKeyOptions($get('settings.tables'), $column, $search))
            ->getOptionLabelsUsing(fn (array $values, $get) => static::foreignKeyLabels($get('settings.tables'), $column, $values))
            ->createOptionForm(fn ($get) => [static::getNewForeignKeyField($get('settings.tables'), $column)])
            ->createOptionUsing(fn (array $data, $get) => NotificationSetting::foreignKeyId($get('settings.tables'), $column, $data['value'] ?? null))
            ->multiple()
            ->rule('array')
            ->columnSpanFull()
            ->searchable()
            ->nullable()
            ->validationMessages([
                '*.in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_column_values'));
    }

    public static function getNewForeignKeyField(mixed $tables, string $column): Select
    {
        return Select::make('value')
            ->label(__('resources/notificationSetting/strings.form.new_value'))
            ->required()
            ->searchable()
            ->getSearchResultsUsing(fn (string $search) => NotificationSetting::searchForeignKeyOptions($tables, $column, $search))
            ->getOptionLabelUsing(fn ($value) => static::foreignKeyLabels($tables, $column, [$value])[$value] ?? null);
    }

    protected static function foreignKeyLabels(mixed $tables, string $column, array $values): array
    {
        $labels = NotificationSetting::foreignKeyLabels($tables, $column, $values);

        if (count($labels) < count($values)) {
            $labels += NotificationSetting::getColumnValueOptions($tables, $column);
        }

        return array_intersect_key($labels, array_flip(array_map('strval', $values)));
    }

    public static function getIsActive(): Toggle
    {
        return Toggle::make('settings.is_active')
            ->label(__('resources/notificationSetting/strings.form.is_active'))
            ->default(true)
            ->inline(false)
            ->onIcon('heroicon-s-check-circle')
            ->offIcon('heroicon-s-x-circle')
            ->onColor('success')
            ->offColor('danger');
    }

    public static function getNotes(): Textarea
    {
        return Textarea::make('notes')
            ->label(__('resources/notificationSetting/strings.form.notes'))
            ->nullable()
            ->columnSpanFull()
            ->maxLength(500)
            ->validationAttribute(__('resources/notificationSetting/strings.form.notes'))
            ->validationMessages([
                'max' => __('resources/notificationSetting/strings.form.validation_notes_max'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_notes'));
    }

    public static function getNotificationChannel(): Select
    {
        return Select::make('notification_type')
            ->label(__('resources/notificationSetting/strings.form.notification_type'))
            ->options(NotificationSetting::notificationChannel())
            ->default('in_app')
            ->required()
            ->columnSpan(1)
            ->columnSpanFull()
            ->validationMessages([
                'required' => __('resources/notificationSetting/strings.form.validation_required'),
                'in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_notification_type'));
    }

    public static function getTableSelector(): Select
    {
        return Select::make('settings.tables')
            ->label(__('resources/notificationSetting/strings.form.tables'))
            ->options(fn () => NotificationSetting::getViewableModels())
            ->multiple()
            ->rule('array')
            ->live()
            ->afterStateUpdated(fn ($state, $get, $set) => $set('settings.columns', NotificationSetting::sanitizeSettings([
                'tables' => $state,
                'actions' => ['update'],
                'columns' => $get('settings.columns'),
            ])['columns']))
            ->required()
            ->columnSpan(1)
            ->columnSpanFull()
            ->searchable()
            ->validationMessages([
                'required' => __('resources/notificationSetting/strings.form.validation_required'),
                '*.in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.tables_description'));
    }

    public static function getUserSelector(): Select
    {
        return Select::make('settings.users')
            ->label(__('resources/notificationSetting/strings.form.users'))
            ->options(fn () => User::pluck('name', 'id'))
            ->default(fn () => [auth()->id()])
            ->columnSpan(1)
            ->multiple()
            ->rule('array')
            ->required()
            ->columnSpanFull()
            ->searchable()
            ->validationMessages([
                'required' => __('resources/notificationSetting/strings.form.validation_required'),
                '*.in' => __('resources/notificationSetting/strings.form.validation_in'),
            ])
            ->helperText(__('resources/notificationSetting/strings.form.helper_users'));
    }
}
