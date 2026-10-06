<?php

namespace App\Filament\Resources\Operational\CorrespondenceResource\Exports;

use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Priority;
use App\Filament\Resources\Operational\CorrespondenceResource\Enums\Type;
use App\Models\Correspondence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use League\Csv\EscapeFormula;
use League\Csv\Writer;

class CorrespondenceExporter
{
    public static function write(Builder $query, string $absolutePath): int
    {
        $labels = static::columnLabels();

        $records = $query
            ->with(['status', 'recipients', 'correspondable', 'parent', 'creator', 'updater'])
            ->orderBy('id')
            ->lazy();

        $stream = fopen($absolutePath, 'w+');

        fwrite($stream, "\xEF\xBB\xBF");

        $csv = Writer::from($stream);
        $csv->insertOne(array_values($labels));

        $rows = 0;

        foreach ($records as $record) {
            $csv->insertOne(static::parentRow($record, $labels));
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
            'id' => __('resources/correspondence/strings.export.id'),
            'subject' => __('resources/correspondence/strings.export.subject'),
            'type' => __('resources/correspondence/strings.export.type'),
            'priority' => __('resources/correspondence/strings.export.priority'),
            'status' => __('resources/correspondence/strings.export.status'),
            'is_internal' => __('resources/correspondence/strings.export.is_internal'),
            'is_private' => __('resources/correspondence/strings.export.is_private'),
            'body' => __('resources/correspondence/strings.export.body'),
            'related_to' => __('resources/correspondence/strings.export.related_to'),
            'related_module' => __('resources/correspondence/strings.export.related_module'),
            'thread_role' => __('resources/correspondence/strings.export.thread_role'),
            'parent_subject' => __('resources/correspondence/strings.export.parent_subject'),
            'recipients' => __('resources/correspondence/strings.export.recipients'),
            'creator' => __('resources/correspondence/strings.export.creator'),
            'updater' => __('resources/correspondence/strings.export.updater'),
            'created_at' => __('resources/correspondence/strings.export.created_at'),
            'updated_at' => __('resources/correspondence/strings.export.updated_at'),
        ];
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<int, string>
     */
    protected static function parentRow(Correspondence $record, array $labels): array
    {
        $values = array_fill_keys(array_keys($labels), '');

        $values['id'] = (string) $record->id;
        $values['subject'] = static::plainText($record->subject);
        $values['type'] = Type::tryFrom($record->type)?->getLabel() ?? (string) $record->type;
        $values['priority'] = Priority::tryFrom($record->priority)?->getLabel() ?? (string) $record->priority;
        $values['status'] = $record->status?->getLocalizedNameAttribute() ?? '';
        $values['is_internal'] = static::yesNo($record->is_internal);
        $values['is_private'] = static::yesNo($record->is_private);
        $values['body'] = static::plainText($record->body);
        $values['related_to'] = $record->correspondable?->formatted_name ?? '';
        $values['related_module'] = $record->correspondable_type ? Str::headline(class_basename($record->correspondable_type)) : '';
        $values['thread_role'] = $record->parent_id
            ? __('resources/correspondence/strings.export.thread_role_reply')
            : __('resources/correspondence/strings.export.thread_role_root');
        $values['parent_subject'] = $record->parent ? static::plainText($record->parent->subject) : '';
        $values['recipients'] = static::recipientsSummary($record);
        $values['creator'] = static::plainText($record->creator?->name ?? '');
        $values['updater'] = static::plainText($record->updater?->name ?? '');
        $values['created_at'] = (string) ($record->created_at?->format('Y-m-d H:i:s') ?? '');
        $values['updated_at'] = (string) ($record->updated_at?->format('Y-m-d H:i:s') ?? '');

        return array_values($values);
    }

    protected static function recipientsSummary(Correspondence $record): string
    {
        $to = __('resources/correspondence/strings.export.recipient_to');
        $cc = __('resources/correspondence/strings.export.recipient_cc');

        return $record->recipients
            ->map(fn ($user) => "{$user->name} (".($user->pivot->type === 'to' ? $to : $cc).')')
            ->implode(', ');
    }

    protected static function yesNo(mixed $value): string
    {
        return $value
            ? __('resources/correspondence/strings.export.yes')
            : __('resources/correspondence/strings.export.no');
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
}
