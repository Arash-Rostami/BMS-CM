<?php

namespace App\Filament\Traits;


use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

trait ExportDefaults
{
    public function getQuery(): Builder
    {
        return parent::getQuery()->limit(1000);
    }

    public function getFileName(Export $export): string
    {
        $app = config('app.name');
        $timestamp = now()->format('His');
        $model = strtoupper(class_basename(static::$model));

        return "{$app}-{$model}-{$timestamp}";
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your bank export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
