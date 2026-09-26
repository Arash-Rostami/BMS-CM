<?php

namespace App\Filament\Traits;

use App\Services\ExceptionPresenter;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Throwable;

/**
 * Turns an uncaught save-time exception into a translated, actionable Filament notification,
 * then throws Halt so Filament's own Create/Edit lifecycle rolls back the transaction and
 * stops quietly — the generic "error loading page" Livewire fallback never fires because
 * Halt never reaches it as an unhandled exception.
 */
trait HandlesSaveExceptions
{
    protected function reportSaveException(Throwable $e): never
    {
        $presented = ExceptionPresenter::present($e);

        Notification::make()
            ->danger()
            ->title($presented['title'])
            ->body($presented['body'])
            ->persistent()
            ->send();

        throw (new Halt)->rollBackDatabaseTransaction();
    }
}
