<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\Calendar\CalendarActivity;
use App\Services\Calendar\CalendarModules;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Lang;

class CalendarActivityAction
{
    public const SEARCH_LIMIT = 25;

    public static function make(): Action
    {
        return Action::make('calendarActivity')
            ->label(__('resources/calendarRule/strings.activity.label'))
            ->icon('heroicon-o-clock')
            ->color('gray')
            ->modalHeading(__('resources/calendarRule/strings.activity.modal_heading'))
            ->schema([
                static::getModuleField(),
                static::getRecordField(),
                static::getShowAllField(),
                static::getHistoryField(),
            ])
            ->modalSubmitAction(false)
            ->modalWidth('4xl');
    }

    public static function getModuleField(): Select
    {
        return Select::make('module')
            ->label(__('resources/calendarRule/strings.activity.module'))
            ->options(fn (): array => collect(CalendarModules::viewableBy(auth()->user()))
                ->mapWithKeys(fn (string $module): array => [$module => CalendarModules::label($module)])
                ->all())
            ->searchable()
            ->live()
            ->afterStateUpdated(fn (Set $set): mixed => $set('record', null));
    }

    public static function getRecordField(): Select
    {
        return Select::make('record')
            ->label(__('resources/calendarRule/strings.activity.record'))
            ->searchable()
            ->live()
            ->getSearchResultsUsing(fn (string $search, Get $get): array => static::searchRecords(
                auth()->user(),
                $get('module'),
                $search,
            ))
            ->getOptionLabelUsing(fn (mixed $value, Get $get): string => static::optionLabel(
                auth()->user(),
                $get('module'),
                $value,
            ));
    }

    public static function getShowAllField(): Toggle
    {
        return Toggle::make('show_all')
            ->label(__('resources/calendarRule/strings.activity.show_all'))
            ->live();
    }

    public static function getHistoryField(): Placeholder
    {
        return Placeholder::make('history')
            ->hiddenLabel()
            ->content(fn (Get $get) => view('filament.calendar.activity', static::history(
                auth()->user(),
                $get('module'),
                $get('record'),
                (bool) $get('show_all'),
            )))
            ->columnSpanFull();
    }

    public static function searchRecords(?User $user, mixed $module, string $search): array
    {
        $fqcn = static::viewableModule($user, $module);
        $identifier = $fqcn === null ? null : (CalendarModules::all()[$fqcn]['identifier'] ?? null);

        if (! is_string($identifier)) {
            return [];
        }

        $escaped = addcslashes($search, '%_\\');

        return static::scopedQuery($fqcn)
            ->whereRaw("CAST(`{$identifier}` AS CHAR) LIKE ?", ['%'.$escaped.'%'])
            ->orderByDesc((new $fqcn)->getKeyName())
            ->limit(self::SEARCH_LIMIT)
            ->get()
            ->mapWithKeys(fn (Model $record): array => [$record->getKey() => static::labelFor($record)])
            ->all();
    }

    /**
     * @return array{rows: array<int, array{text: string, when: string}>, empty: string}
     */
    public static function history(?User $user, mixed $module, mixed $recordId, bool $all): array
    {
        $record = static::recordFor($user, $module, $recordId);
        $rows = $record === null || $user === null ? collect() : app(CalendarActivity::class)->forViewer($record, $user, $all);

        return [
            'rows' => $rows->map(fn (array $row): array => static::describe($row))
                ->filter(fn (array $row): bool => $row['text'] !== '')
                ->values()
                ->all(),
            'empty' => $record === null
                ? __('resources/calendarRule/strings.activity.empty')
                : __('resources/calendarRule/strings.activity.none'),
        ];
    }

    public static function recordFor(?User $user, mixed $module, mixed $recordId): ?Model
    {
        $fqcn = static::viewableModule($user, $module);

        return $fqcn === null || ! is_numeric($recordId) ? null : static::scopedQuery($fqcn)->find((int) $recordId);
    }

    private static function optionLabel(?User $user, mixed $module, mixed $recordId): string
    {
        $record = static::recordFor($user, $module, $recordId);

        return $record === null ? '' : static::labelFor($record);
    }

    private static function scopedQuery(string $fqcn): Builder
    {
        $query = $fqcn::query();

        return in_array(SoftDeletes::class, class_uses_recursive($fqcn), true)
            ? $query->withoutGlobalScopes([SoftDeletingScope::class])
            : $query;
    }

    private static function labelFor(Model $record): string
    {
        $label = CalendarModules::itemLabel($record);

        return method_exists($record, 'trashed') && $record->trashed()
            ? $label.' ('.__('resources/calendarRule/strings.activity.deleted').')'
            : $label;
    }

    private static function viewableModule(?User $user, mixed $module): ?string
    {
        if (! is_string($module) || $user === null) {
            return null;
        }

        return in_array($module, CalendarModules::viewableBy($user), true) ? $module : null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{text: string, when: string}
     */
    private static function describe(array $row): array
    {
        $props = is_array($row['properties'] ?? null) ? $row['properties'] : [];
        $event = $row['event'] ?? null;
        $key = is_string($event) ? 'resources/calendarRule/strings.activity.'.$event : '';

        return [
            'text' => $key !== '' && Lang::has($key) ? __($key, static::params($props)) : '',
            'when' => adaptiveDate($row['created_at'] ?? null, true),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, string>
     */
    private static function params(array $props): array
    {
        return [
            'rule' => static::text($props['rule_name'] ?? null),
            'label' => static::text($props['label'] ?? null),
            'date' => static::dateText($props['event_date'] ?? $props['alert_date'] ?? null),
            'old_date' => static::dateText($props['old_date'] ?? null),
            'lead' => static::text($props['lead'] ?? null),
            'status' => static::statusText($props),
        ];
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function dateText(mixed $value): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value) === 1 ? adaptiveDate($value) : '';
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private static function statusText(array $props): string
    {
        $kindKey = is_string($props['kind'] ?? null) ? 'resources/calendarRule/strings.alerts.kinds.'.$props['kind'] : null;

        if ($kindKey !== null) {
            return Lang::has($kindKey) ? (string) __($kindKey) : '';
        }

        if (is_string($props['problems'] ?? null)) {
            return $props['problems'];
        }

        return array_key_exists('active', $props)
            ? __($props['active'] ? 'resources/calendarRule/strings.activity.active' : 'resources/calendarRule/strings.activity.inactive')
            : '';
    }
}
