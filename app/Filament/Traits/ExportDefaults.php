<?php

namespace App\Filament\Traits;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

trait ExportDefaults
{
    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('resources/general/strings.export.completed', [
            'successful' => number_format($export->successful_rows),
        ]);

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= __('resources/general/strings.export.failed', [
                'failed' => number_format($failedRowsCount),
            ]);
        }

        return $body;
    }

    public function getFileName(Export $export): string
    {
        $app = config('app.name');
        $timestamp = now()->format('His');
        $model = strtoupper(class_basename(static::$model));

        return "{$app}-{$model}-{$timestamp}";
    }

    public function getQuery(): Builder
    {
        return parent::getQuery()
            ->with(array_merge(['creator', 'updater'], static::eagerLoadRelations()))
            ->limit(1000);
    }

    protected static function eagerLoadRelations(): array
    {
        return [];
    }
}
