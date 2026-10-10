<?php

namespace App\Filament\Resources\Master\NotificationSettingResource\Exports;

use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class NotificationSettingExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['creator', 'updater'])
            ->orderBy('id')
            ->get();

        $userNames = User::whereIn('id', $records->flatMap(fn (NotificationSetting $record) => $record->getUsers())->unique()->filter()->values())
            ->pluck('name', 'id');

        $stream = fopen($absolutePath, 'w+');

        fwrite($stream, "\xEF\xBB\xBF");

        $csv = Writer::from($stream);
        $csv->insertOne(array_values($labels));

        $rows = 0;

        foreach ($records as $record) {
            $csv->insertOne(static::row($record, $labels, $userNames));
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
            'id' => __('resources/notificationSetting/strings.export.id'),
            'tables' => __('resources/notificationSetting/strings.export.tables'),
            'actions' => __('resources/notificationSetting/strings.export.actions'),
            'columns' => __('resources/notificationSetting/strings.export.columns'),
            'users' => __('resources/notificationSetting/strings.export.users'),
            'notification_type' => __('resources/notificationSetting/strings.export.notification_type'),
            'is_active' => __('resources/notificationSetting/strings.export.is_active'),
            'notes' => __('resources/notificationSetting/strings.export.notes'),
            'creator' => __('resources/notificationSetting/strings.export.creator'),
            'updater' => __('resources/notificationSetting/strings.export.updater'),
            'created_at' => __('resources/notificationSetting/strings.export.created_at'),
            'updated_at' => __('resources/notificationSetting/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @param  \Illuminate\Support\Collection<int, string>  $userNames
     * @return array<int, string>
     */
    protected static function row(NotificationSetting $record, array $labels, $userNames): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['tables'] = implode(', ', $record->getLocalizedTables());
        $values['actions'] = implode(', ', $record->getLocalizedActions());
        $values['columns'] = implode(', ', NotificationSetting::columnLabels($record->getTables(), $record->getColumns()));
        $values['users'] = static::plainText(implode(', ', collect($record->getUsers())->map(fn ($id) => $userNames[$id] ?? $id)->all()));
        $values['notification_type'] = static::plainText($record->notification_channel);
        $values['is_active'] = $record->isActive()
            ? __('resources/notificationSetting/strings.export.active')
            : __('resources/notificationSetting/strings.export.inactive');
        $values['notes'] = static::plainText($record->notes ?? '');
        $values['creator'] = static::plainText($record->creator?->name ?? '');
        $values['updater'] = static::plainText($record->updater?->name ?? '');
        $values['created_at'] = static::jalaliDate($record->created_at);
        $values['updated_at'] = static::jalaliDate($record->updated_at);

        return array_values($values);
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
