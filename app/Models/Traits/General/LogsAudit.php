<?php

namespace App\Models\Traits\General;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

trait LogsAudit
{
    use LogsActivity {
        attributeValuesToBeLogged as protected spatieAttributeValues;
    }

    public const AUDIT_LOG = 'audit';

    public static function auditColumns(): array
    {
        return [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName(self::AUDIT_LOG)
            ->logOnly(static::auditColumns())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function attributeValuesToBeLogged(string $processingEvent): array
    {
        return match ($processingEvent) {
            'deleted' => ['attributes' => ['deleted_at' => $this->deleted_at?->toDateTimeString()]],
            'restored' => ['attributes' => ['deleted_at' => null]],
            'created' => ['attributes' => array_filter(
                $this->spatieAttributeValues($processingEvent)['attributes'] ?? [],
                fn ($value): bool => $value !== null
            )],
            default => $this->spatieAttributeValues($processingEvent),
        };
    }
}
