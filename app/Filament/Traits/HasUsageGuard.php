<?php

namespace App\Filament\Traits;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

trait HasUsageGuard
{
    protected static function usageRelations(): array
    {
        return [];
    }

    public static function usageCount(Model $record): int
    {
        return collect(static::usageRelations())
            ->sum(fn (string $relation) => $record->{$relation}()->count());
    }

    protected static function guardRecordAction(Action $action): Action
    {
        return $action->before(function (Action $action, Model $record) {
            static::haltIfInUse($action, static::usageCount($record));
        });
    }

    protected static function guardBulkAction(Action $action): Action
    {
        return $action->before(function (Action $action, Collection $records) {
            static::haltIfInUse($action, $records->sum(fn (Model $record) => static::usageCount($record)));
        });
    }

    protected static function haltIfInUse(Action $action, int $count): void
    {
        if ($count < 1) {
            return;
        }

        Notification::make()
            ->title(__('resources/general/strings.usage_guard.blocked', ['count' => $count]))
            ->danger()
            ->send();

        $action->halt();
    }
}
