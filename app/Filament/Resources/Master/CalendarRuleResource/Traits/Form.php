<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Traits;

use App\Filament\Resources\Master\CalendarRuleResource\Components\TableRuleBuilder;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\CalendarColor;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\RuleType;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\Visibility;
use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Models\CalendarRule;
use App\Models\Casts\OutsideEmailsCast;
use App\Models\Role;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use App\Services\Calendar\CalendarPathResolver;
use App\Services\Calendar\Sync\CalendarEngine;
use App\Services\Calendar\Sync\CalendarFilterTree;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait Form
{
    private static array $similarCache = [];

    public static function flushSimilarCache(): void
    {
        self::$similarCache = [];
    }

    public static function getNameField(): TextInput
    {
        return TextInput::make('name')
            ->label(__('resources/calendarRule/strings.form.name'))
            ->helperText(__('resources/calendarRule/strings.form.name_hint'))
            ->required()
            ->maxLength(255)
            ->validationMessages([
                'required' => __('resources/calendarRule/strings.form.validation_required'),
                'max' => __('resources/calendarRule/strings.form.validation_name_max'),
            ]);
    }

    public static function getSubjectField(): Select
    {
        return Select::make('subject')
            ->label(__('resources/calendarRule/strings.form.subject'))
            ->helperText(__('resources/calendarRule/strings.form.subject_hint'))
            ->options(fn (): array => collect(CalendarModules::viewableBy(auth()->user()))
                ->mapWithKeys(fn (string $module): array => [$module => CalendarModules::label($module)])
                ->all())
            ->searchable()
            ->preload()
            ->live()
            ->required()
            ->afterStateUpdated(function (Set $set): void {
                $set('filters', null);
                $set('extra_paths', null);
                $set('_table', CalendarPathResolver::OWN_TABLE);
                $set('date_path', null);
                $set('_preview', null);
            })
            ->validationMessages([
                'required' => __('resources/calendarRule/strings.form.validation_required'),
                'in' => __('resources/calendarRule/strings.form.validation_subject'),
            ]);
    }

    public static function getExtraPathsField(): Hidden
    {
        return Hidden::make('extra_paths')
            ->dehydrateStateUsing(fn (Get $get): array => static::storedPaths($get));
    }

    public static function getTableField(): Select
    {
        return Select::make('_table')
            ->label(__('resources/calendarRule/strings.form.table'))
            ->options(fn (Get $get): array => static::tableOptions($get))
            ->noOptionsMessage(fn (Get $get): ?string => static::emptyMessage($get))
            ->afterStateHydrated(fn (Select $component, Get $get) => $component->state(static::usableSubject($get) === '' ? null : CalendarPathResolver::OWN_TABLE))
            ->placeholder(__('resources/calendarRule/strings.form.choose_module_first'))
            ->searchable()
            ->searchPrompt(__('resources/calendarRule/strings.form.search_prompt'))
            ->optionsLimit(100)
            ->preload()
            ->live()
            ->selectablePlaceholder(false)
            ->dehydrated(false);
    }

    public static function getDatePathField(): Select
    {
        return Select::make('date_path')
            ->label(__('resources/calendarRule/strings.form.date_path'))
            ->options(fn (Get $get): array => static::datePathOptions($get))
            ->noOptionsMessage(fn (Get $get): ?string => static::emptyMessage($get))
            ->helperText(fn (Get $get): string => static::datePathHelper($get))
            ->live()
            ->searchable()
            ->searchPrompt(__('resources/calendarRule/strings.form.search_prompt'))
            ->optionsLimit(100)
            ->preload()
            ->required()
            ->validationMessages([
                'required' => __('resources/calendarRule/strings.form.validation_required'),
                'in' => __('resources/calendarRule/strings.form.validation_date_path'),
            ]);
    }

    public static function getDayShiftField(): TextInput
    {
        return TextInput::make('day_shift')
            ->label(__('resources/calendarRule/strings.form.day_shift'))
            ->helperText(__('resources/calendarRule/strings.form.day_shift_hint'))
            ->numeric()
            ->integer()
            ->default(0)
            ->minValue(-3650)
            ->maxValue(3650)
            ->live(onBlur: true)
            ->validationMessages([
                'numeric' => __('resources/calendarRule/strings.form.validation_day_shift'),
                'integer' => __('resources/calendarRule/strings.form.validation_day_shift'),
                'min' => __('resources/calendarRule/strings.form.validation_day_shift'),
                'max' => __('resources/calendarRule/strings.form.validation_day_shift'),
            ]);
    }

    public static function getLeadTimesField(): Repeater
    {
        return Repeater::make('lead_times')
            ->label(__('resources/calendarRule/strings.form.lead_times'))
            ->helperText(__('resources/calendarRule/strings.form.lead_times_hint'))
            ->addActionLabel(__('resources/calendarRule/strings.form.add_lead_time'))
            ->simple(
                TextInput::make('lead_time')
                    ->numeric()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(120)
                    ->live(onBlur: true)
                    ->validationMessages([
                        'numeric' => __('resources/calendarRule/strings.form.validation_lead_time'),
                        'integer' => __('resources/calendarRule/strings.form.validation_lead_time'),
                        'min' => __('resources/calendarRule/strings.form.validation_lead_time'),
                        'max' => __('resources/calendarRule/strings.form.validation_lead_time'),
                    ])
            )
            ->default([7, 3, 1])
            ->reorderable()
            ->distinct()
            ->validationMessages([
                'distinct' => __('resources/calendarRule/strings.form.validation_lead_time'),
            ]);
    }

    public static function getOnDayField(): Toggle
    {
        return Toggle::make('on_day')
            ->label(__('resources/calendarRule/strings.form.on_day'))
            ->helperText(__('resources/calendarRule/strings.form.on_day_hint'))
            ->live()
            ->default(true);
    }

    public static function getNotificationTypeField(): Select
    {
        return Select::make('notification_type')
            ->label(__('resources/calendarRule/strings.form.notification_type'))
            ->options(CalendarRule::notificationChannel())
            ->default(CalendarRule::NOTIFICATION_CHANNEL_IN_APP)
            ->live()
            ->afterStateUpdated(fn (mixed $state, Set $set) => in_array(static::enumState($state), [CalendarRule::NOTIFICATION_CHANNEL_EMAIL, CalendarRule::NOTIFICATION_CHANNEL_ALL], true) ?: $set('notify_emails', null))
            ->required()
            ->validationMessages([
                'required' => __('resources/calendarRule/strings.form.validation_required'),
                'in' => __('resources/calendarRule/strings.form.validation_notification_type_in'),
            ])
            ->helperText(__('resources/calendarRule/strings.form.helper_notification_type'));
    }

    public static function getFiltersField(): Group
    {
        return Group::make(fn (Get $get): array => [
            static::getExtraPathsField(),
            static::filterBuilder($get),
        ])->columns(1)->columnSpanFull();
    }

    private static function filterBuilder(Get $get): TableRuleBuilder
    {
        $constraints = static::filterConstraints($get);

        return TableRuleBuilder::make('filters')
            ->label(__('resources/calendarRule/strings.form.filters'))
            ->constraints($constraints)
            ->pickable(static::pickableNames($get, $constraints))
            ->constraintPickerColumns(3)
            ->blockPickerWidth('5xl')
            ->columnSpanFull()
            ->maxRules(30)
            ->maxNestingDepth(3)
            ->rule(fn (): Closure => static::filtersRule($get))
            ->mutateDehydratedStateUsing(fn (?array $state): array => ['rules' => array_values(app(CalendarFilterTree::class)->prune($state ?? []))]);
    }

    public static function getTypeField(): ToggleButtons
    {
        return ToggleButtons::make('type')
            ->label(__('resources/calendarRule/strings.form.type'))
            ->helperText(__('resources/calendarRule/strings.form.type_hint'))
            ->options(RuleType::class)
            ->default(RuleType::HEADS_UP)
            ->inline()
            ->required()
            ->validationMessages([
                'required' => __('resources/calendarRule/strings.form.validation_required'),
                'in' => __('resources/calendarRule/strings.form.validation_type_in'),
            ]);
    }

    public static function getColorField(): Select
    {
        return Select::make('color')
            ->label(__('resources/calendarRule/strings.form.color'))
            ->helperText(__('resources/calendarRule/strings.form.color_hint'))
            ->options(CalendarColor::class)
            ->default(CalendarColor::SKY)
            ->searchable()
            ->validationMessages([
                'enum' => __('resources/calendarRule/strings.form.validation_color_in'),
            ]);
    }

    public static function getVisibilityField(): Select
    {
        return Select::make('visibility')
            ->label(__('resources/calendarRule/strings.form.visibility'))
            ->helperText(__('resources/calendarRule/strings.form.visibility_hint'))
            ->options(Visibility::class)
            ->default(Visibility::ME)
            ->afterStateUpdated(function (mixed $state, Set $set): void {
                $state = static::enumState($state);
                $state === Visibility::USERS->value ?: $set('shared_user_ids', null);
                $state === Visibility::ROLES->value ?: $set('shared_role_ids', null);
            })
            ->live()
            ->required()
            ->validationMessages([
                'required' => __('resources/calendarRule/strings.form.validation_required'),
                'enum' => __('resources/calendarRule/strings.form.validation_visibility_enum'),
            ]);
    }

    public static function getSharedUsersField(): Select
    {
        return Select::make('shared_user_ids')
            ->label(__('resources/calendarRule/strings.form.shared_user_ids'))
            ->helperText(__('resources/calendarRule/strings.form.shared_user_ids_hint'))
            ->multiple()
            ->searchable()
            ->preload()
            ->options(fn (Get $get): array => static::sharedUserOptions($get))
            ->visible(fn (Get $get): bool => static::enumState($get('visibility')) === Visibility::USERS->value)
            ->rule(fn (Get $get): Closure => static::sharedUsersRule($get))
            ->validationMessages([
                '*.in' => __('resources/calendarRule/strings.form.validation_shared_users_in'),
            ]);
    }

    public static function getSharedRolesField(): Select
    {
        return Select::make('shared_role_ids')
            ->label(__('resources/calendarRule/strings.form.shared_role_ids'))
            ->helperText(__('resources/calendarRule/strings.form.shared_role_ids_hint'))
            ->multiple()
            ->searchable()
            ->preload()
            ->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')
                ->map(fn (string $name): string => UserRole::tryFrom($name)?->getLabel() ?? $name)
                ->all())
            ->visible(fn (Get $get): bool => static::enumState($get('visibility')) === Visibility::ROLES->value)
            ->required(fn (Get $get): bool => static::enumState($get('visibility')) === Visibility::ROLES->value)
            ->validationMessages([
                'required' => __('resources/calendarRule/strings.form.validation_shared_roles_required'),
                '*.in' => __('resources/calendarRule/strings.form.validation_shared_roles_in'),
            ]);
    }

    public static function getNotifyEmailsField(): TagsInput
    {
        return TagsInput::make('notify_emails')
            ->label(__('resources/calendarRule/strings.form.notify_emails'))
            ->helperText(__('resources/calendarRule/strings.form.notify_emails_hint', ['max' => OutsideEmailsCast::MAX_ITEMS]))
            ->placeholder(__('resources/calendarRule/strings.form.notify_emails_placeholder'))
            ->splitKeys(['Tab', ',', ' '])
            ->visible(fn (Get $get): bool => in_array(static::enumState($get('notification_type')), [CalendarRule::NOTIFICATION_CHANNEL_EMAIL, CalendarRule::NOTIFICATION_CHANNEL_ALL], true))
            ->rule(fn (Get $get, ?Model $record): Closure => static::notifyEmailsRule($get, $record));
    }

    public static function getIsActiveField(): Toggle
    {
        return Toggle::make('is_active')
            ->label(__('resources/calendarRule/strings.form.is_active'))
            ->helperText(__('resources/calendarRule/strings.form.is_active_hint'))
            ->default(true);
    }

    public static function getPreviewField(): ViewField
    {
        return ViewField::make('_preview')
            ->label(__('resources/calendarRule/strings.form.preview'))
            ->dehydrated(false)
            ->view('filament.calendar.preview-field')
            ->hintAction(
                Action::make('preview')
                    ->icon('heroicon-o-eye')
                    ->label(__('resources/calendarRule/strings.form.preview_button'))
                    ->action(fn (Get $get, Set $set): mixed => $set('_preview', static::previewData($get)))
            );
    }

    public static function getSimilarRuleField(): TextEntry
    {
        $similar = fn (Get $get, ?Model $record): bool => static::similarRule($get, $record) !== null;

        return TextEntry::make('_similar')
            ->label('')
            ->visible($similar)
            ->state(fn (Get $get, ?Model $record): string => __('resources/calendarRule/strings.form.similar_found', [
                'rule' => (string) static::similarRule($get, $record)?->name,
            ]))
            ->hintAction(
                Action::make('merge')
                    ->label(__('resources/calendarRule/strings.form.merge'))
                    ->visible(fn (Get $get, ?Model $record): bool => static::canEditAny() && static::similarRule($get, $record) !== null)
                    ->action(fn (Get $get, ?Model $record): mixed => static::mergeIntoSimilar($get, $record))
            );
    }

    private static function emptyMessage(Get $get): ?string
    {
        return static::usableSubject($get) === '' ? __('resources/calendarRule/strings.form.choose_module_first') : null;
    }

    private static function usableSubject(Get $get): string
    {
        $subject = $get('subject');

        return is_string($subject) && in_array($subject, CalendarModules::viewableBy(auth()->user()), true)
            ? $subject
            : '';
    }

    private static function resolvablePaths(string $subject, mixed $paths): array
    {
        $resolver = app(CalendarPathResolver::class);

        return $subject === '' ? [] : array_values(array_filter(
            is_array($paths) ? $paths : [],
            fn (mixed $path): bool => is_string($path) && $resolver->isRelationPath($subject, $path)
        ));
    }

    private static function filtersRule(Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            if (! static::filtersUsable($get, $value)) {
                $fail(__('resources/calendarRule/strings.form.validation_filters'));
            }
        };
    }

    private static function filtersUsable(Get $get, mixed $filters): bool
    {
        $subject = static::usableSubject($get);
        $paths = static::activePaths($get);
        $allowed = static::permittedNames($subject, $paths, app(CalendarPathResolver::class)->leafNames($filters));
        $usable = true;

        try {
            app(CalendarFilterTree::class)->walkLeaves(blank($filters) ? [] : $filters, function (array $leaf) use ($allowed, &$usable): void {
                $usable = $usable && in_array($leaf['type'] ?? null, $allowed, true);
            });
        } catch (ValidationException) {
            return false;
        }

        return $usable && static::engineAcceptsFilters($subject, $paths, (array) ($filters ?? []));
    }

    private static function permittedNames(string $subject, array $paths, array $keep): array
    {
        if ($subject === '') {
            return [];
        }

        return collect(app(CalendarPathResolver::class)->constraintsFor($subject, $paths, auth()->user(), $keep))
            ->map(fn (Constraint $constraint): string => $constraint->getName())
            ->all();
    }

    private static function engineAcceptsFilters(string $subject, array $paths, array $filters): bool
    {
        $transient = (new CalendarRule)->forceFill([
            'subject' => $subject,
            'filters' => ['rules' => $filters],
            'extra_paths' => $paths,
        ]);

        return $subject !== '' && app(CalendarEngine::class)->filterProblems($transient) === [];
    }

    private static function datePathHelper(Get $get): string
    {
        $hint = __('resources/calendarRule/strings.form.date_path_hint');
        $subject = static::usableSubject($get);
        $path = $get('date_path');
        $info = $subject !== '' && is_string($path) && $path !== ''
            ? app(CalendarPathResolver::class)->describePath($subject, $path)
            : null;

        return $info === null ? $hint : $hint.' '.__('resources/calendarRule/strings.form.date_path_chosen', $info);
    }

    public static function tableOptions(Get $get): array
    {
        $subject = static::usableSubject($get);
        $groups = $subject === '' ? [] : app(CalendarPathResolver::class)->tableOptions($subject, auth()->user());

        return array_filter([
            __('resources/calendarRule/strings.form.tables_direct') => $groups['direct'] ?? [],
            __('resources/calendarRule/strings.form.tables_far') => $groups['far'] ?? [],
        ]);
    }

    public static function chosenPath(Get $get): string
    {
        $subject = static::usableSubject($get);
        $chosen = $get('_table');

        if ($subject === '' || ! is_string($chosen) || $chosen === CalendarPathResolver::OWN_TABLE) {
            return '';
        }

        $groups = app(CalendarPathResolver::class)->tableOptions($subject, auth()->user());

        return isset($groups['direct'][$chosen]) || isset($groups['far'][$chosen]) ? $chosen : '';
    }

    public static function activePaths(Get $get): array
    {
        $subject = static::usableSubject($get);

        return $subject === '' ? [] : array_values(array_unique([
            ...static::resolvablePaths($subject, $get('extra_paths')),
            ...app(CalendarPathResolver::class)->usedPaths($subject, $get('filters')),
        ]));
    }

    private static function storedPaths(Get $get): array
    {
        $subject = static::usableSubject($get);
        $resolver = app(CalendarPathResolver::class);

        return array_values(array_filter(
            static::activePaths($get),
            fn (string $path): bool => $resolver->relationViewable($subject, $path, auth()->user())
        ));
    }

    public static function pickableNames(Get $get, array $constraints): array
    {
        $path = static::chosenPath($get);

        return collect($constraints)
            ->map(fn (Constraint $constraint): string => $constraint->getName())
            ->filter(fn (string $name): bool => $path === ''
                ? ! str_contains($name, '.')
                : str_contains($name, '.') && Str::beforeLast($name, '.') === $path)
            ->values()
            ->all();
    }

    private static function datePathOptions(Get $get): array
    {
        $subject = static::usableSubject($get);

        if ($subject === '') {
            return [];
        }

        return app(CalendarPathResolver::class)
            ->groupedDatePathOptions($subject, auth()->user());
    }

    public static function filterConstraints(Get $get): array
    {
        $subject = static::usableSubject($get);

        if ($subject === '') {
            return [];
        }

        $resolver = app(CalendarPathResolver::class);

        return $resolver->constraintsFor(
            $subject,
            array_values(array_unique([...static::activePaths($get), ...(static::chosenPath($get) === '' ? [] : [static::chosenPath($get)])])),
            auth()->user(),
            $resolver->leafNames($get('filters')),
            $resolver->textLeafNames($get('filters')),
        );
    }

    private static function sharedUserOptions(Get $get): array
    {
        $subject = static::usableSubject($get);

        if ($subject === '') {
            return [];
        }

        $permission = Str::snake(class_basename($subject)).'.view';

        return User::permission($permission)->pluck('name', 'id')->all();
    }

    private static function sharedUsersRule(Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            if (blank($value)) {
                return;
            }

            $allowed = array_keys(static::sharedUserOptions($get));

            foreach ((array) $value as $id) {
                if (! in_array($id, $allowed)) {
                    $fail(__('resources/calendarRule/strings.form.validation_shared_users_permission'));

                    return;
                }
            }
        };
    }

    private static function notifyEmailsRule(Get $get, ?Model $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
            $emails = array_values(array_filter((array) $value, fn (mixed $email): bool => filled($email)));

            if ($emails === []) {
                return;
            }

            $valid = count($emails) <= OutsideEmailsCast::MAX_ITEMS
                && collect($emails)->every(fn (mixed $email): bool => is_string($email) && OutsideEmailsCast::isValid(Str::lower(trim($email))));

            if (! $valid) {
                $fail(__('resources/calendarRule/strings.form.validation_notify_emails', ['max' => OutsideEmailsCast::MAX_ITEMS]));

                return;
            }

            $duplicates = static::duplicateEmails(array_map(fn (string $email): string => Str::lower(trim($email)), $emails), $get, $record);

            if ($duplicates !== []) {
                $fail(__('resources/calendarRule/strings.form.validation_notify_emails_duplicates', ['emails' => implode(', ', $duplicates)]));
            }
        };
    }

    private static function duplicateEmails(array $emails, Get $get, ?Model $record): array
    {
        $audience = static::audienceEmails($get, $record);
        $repeated = array_keys(array_filter(array_count_values($emails), fn (int $count): bool => $count > 1));

        return array_values(array_unique([...$repeated, ...array_intersect($emails, $audience)]));
    }

    private static function audienceEmails(Get $get, ?Model $record): array
    {
        $subject = static::usableSubject($get);
        $visibility = Visibility::tryFrom(static::enumState($get('visibility')));

        if ($subject === '' || $visibility === null) {
            return [];
        }

        return (new CalendarRule)->forceFill([
            'subject' => $subject,
            'user_id' => $record?->user_id ?? auth()->id(),
            'visibility' => $visibility,
            'shared_user_ids' => $get('shared_user_ids'),
            'shared_role_ids' => $get('shared_role_ids'),
        ])->recipients()
            ->map(fn (User $user): string => Str::lower(trim((string) $user->email)))
            ->all();
    }

    private static function previewData(Get $get): array
    {
        $subject = static::usableSubject($get);

        if ($subject !== '' && ! static::filtersUsable($get, $get('filters'))) {
            app(CalendarFilterTree::class)->throwPreviewValidation('filters');
        }

        return app(CalendarEngine::class)->preview(
            auth()->user(),
            $subject,
            static::domainFilters($get),
            static::activePaths($get),
            (string) $get('date_path'),
            (int) $get('day_shift'),
        );
    }

    private static function domainFilters(Get $get): array
    {
        return ['rules' => app(CalendarFilterTree::class)->prune((array) ($get('filters') ?? []))];
    }

    private static function leadTimeValues(Get $get): array
    {
        $values = [];

        foreach ((array) ($get('lead_times') ?? []) as $item) {
            $values[] = is_array($item) ? (int) ($item['lead_time'] ?? 0) : (int) $item;
        }

        return $values;
    }

    private static function similarRule(Get $get, ?Model $record): ?CalendarRule
    {
        $user = auth()->user();
        $subject = static::usableSubject($get);
        $datePath = (string) $get('date_path');

        if ($subject === '' || $datePath === '') {
            return null;
        }

        $signature = md5(serialize([$user->id, $record?->getKey(), $subject, $get('filters'), $datePath, $get('day_shift'), $get('type')]));

        return self::$similarCache[$signature] ??= CalendarRule::query()
            ->when(! $user->isAdmin(), fn (Builder $query): Builder => $query->where('user_id', $user->id))
            ->when($record !== null, fn (Builder $query): Builder => $query->whereKeyNot($record->getKey()))
            ->where('conditions_hash', sha1(implode('|', [
                $subject,
                json_encode(CalendarRule::canonical(static::domainFilters($get))),
                $datePath,
            ])))
            ->where('fingerprint', '!=', sha1(implode('|', [
                $subject,
                json_encode(CalendarRule::canonical(static::domainFilters($get))),
                $datePath,
            ]).'|'.(int) $get('day_shift').'|'.static::enumState($get('type'))))
            ->orderBy('name')
            ->first();
    }

    private static function mergeIntoSimilar(Get $get, ?Model $record): void
    {
        $similar = static::canEditAny() ? static::similarRule($get, $record) : null;

        if ($similar === null) {
            return;
        }

        $similar->update([
            'lead_times' => array_values(array_unique(array_merge(
                array_map('intval', (array) $similar->lead_times),
                static::leadTimeValues($get),
            ))),
            'on_day' => $similar->on_day || (bool) $get('on_day'),
        ]);

        Notification::make()
            ->title(__('resources/calendarRule/strings.form.merged', ['rule' => $similar->name]))
            ->success()
            ->persistent()
            ->send();

        redirect()->to(static::getUrl('index'));
    }

    private static function enumState(mixed $state): string
    {
        return $state instanceof \BackedEnum ? (string) $state->value : (string) $state;
    }
}
