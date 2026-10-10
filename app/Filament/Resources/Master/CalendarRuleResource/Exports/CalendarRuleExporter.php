<?php

namespace App\Filament\Resources\Master\CalendarRuleResource\Exports;

use App\Filament\Resources\Master\UserResource\Enums\UserRole;
use App\Models\CalendarRule;
use App\Models\Role;
use App\Models\User;
use App\Services\Calendar\CalendarModules;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class CalendarRuleExporter
{
    public static function write(Builder $query, string $absolutePath, ?User $viewer = null): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['creator', 'updater'])
            ->orderBy('id')
            ->lazy();

        $stream = fopen($absolutePath, 'w+');

        fwrite($stream, "\xEF\xBB\xBF");

        $csv = Writer::from($stream);
        $csv->insertOne(array_values($labels));

        $rows = 0;

        foreach ($records as $record) {
            $csv->insertOne(static::row($record, $labels, $viewer));
            $rows++;
        }

        fclose($stream);

        return $rows;
    }

    /**
     * @return array<string, string>
     */
    public static function columnLabels(): array
    {
        return [
            'id' => __('resources/calendarRule/strings.export.id'),
            'name' => __('resources/calendarRule/strings.export.name'),
            'subject' => __('resources/calendarRule/strings.export.subject'),
            'type' => __('resources/calendarRule/strings.export.type'),
            'visibility' => __('resources/calendarRule/strings.export.visibility'),
            'shared_roles' => __('resources/calendarRule/strings.export.shared_roles'),
            'notify_emails' => __('resources/calendarRule/strings.export.notify_emails'),
            'color' => __('resources/calendarRule/strings.export.color'),
            'notification_type' => __('resources/calendarRule/strings.export.notification_type'),
            'date_path' => __('resources/calendarRule/strings.export.date_path'),
            'day_shift' => __('resources/calendarRule/strings.export.day_shift'),
            'lead_times' => __('resources/calendarRule/strings.export.lead_times'),
            'on_day' => __('resources/calendarRule/strings.export.on_day'),
            'is_active' => __('resources/calendarRule/strings.export.is_active'),
            'created_by' => __('resources/calendarRule/strings.export.created_by'),
            'updated_by' => __('resources/calendarRule/strings.export.updated_by'),
            'created_at' => __('resources/calendarRule/strings.export.created_at'),
            'updated_at' => __('resources/calendarRule/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function row(CalendarRule $record, array $labels, ?User $viewer = null): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['name'] = static::plainText($record->name);
        $values['subject'] = static::plainText(CalendarModules::label($record->subject));
        $values['type'] = static::plainText($record->type?->getLabel());
        $values['visibility'] = static::plainText($record->visibility?->getLabel());
        $values['shared_roles'] = static::plainText(static::roleNames($record));
        $values['notify_emails'] = static::plainText(static::emails($record, $viewer));
        $values['color'] = static::plainText($record->color?->getLabel());
        $values['notification_type'] = static::plainText($record->notification_channel);
        $values['date_path'] = static::plainText($record->date_path);
        $values['day_shift'] = (string) $record->day_shift;
        $values['lead_times'] = static::plainText(implode(', ', array_map('strval', (array) ($record->lead_times ?? []))));
        $values['on_day'] = $record->on_day
            ? __('resources/calendarRule/strings.export.active')
            : __('resources/calendarRule/strings.export.inactive');
        $values['is_active'] = $record->is_active
            ? __('resources/calendarRule/strings.export.active')
            : __('resources/calendarRule/strings.export.inactive');
        $values['created_by'] = static::plainText($record->creator?->name ?? '');
        $values['updated_by'] = static::plainText($record->updater?->name ?? '');
        $values['created_at'] = static::jalaliDate($record->created_at);
        $values['updated_at'] = static::jalaliDate($record->updated_at);

        return array_values($values);
    }

    protected static function roleNames(CalendarRule $record): string
    {
        return Role::query()
            ->whereIn('id', $record->shared_role_ids ?? [])
            ->pluck('name')
            ->map(fn (string $name): string => UserRole::tryFrom($name)?->getLabel() ?? $name)
            ->implode(', ');
    }

    protected static function emails(CalendarRule $record, ?User $viewer): string
    {
        $emails = $record->notify_emails ?? [];

        return $viewer !== null && $record->isEditableBy($viewer)
            ? implode(', ', $emails)
            : ($emails === [] ? '' : __('resources/calendarRule/strings.infolist.notify_emails_count', ['count' => count($emails)]));
    }

    protected static function plainText(mixed $value): string
    {
        $text = trim(html_entity_decode(strip_tags((string) ($value ?? '')), ENT_QUOTES));

        return static::escapeCsvFormula($text);
    }

    protected static function escapeCsvFormula(string $value): string
    {
        return (new EscapeFormula)->escapeRecord([$value])[0];
    }

    protected static function jalaliDate(mixed $date): string
    {
        return $date ? jdate($date)->format('Y-m-d') : '';
    }
}
