<?php

namespace App\Filament\Traits;

use App\Services\ExceptionPresenter;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Covers every Filament Action invoked through the generic mounted-action pipeline — row
 * actions (View/Edit/Delete/Restore), header actions, and bulk actions — on any Page or
 * RelationManager that uses this trait. This is the sibling of HandlesSaveExceptions, which
 * only covers the dedicated Create/Edit page save flow; together they cover both of
 * Filament's two separate action-execution mechanisms app-wide.
 *
 * `Filament\Actions\Concerns\InteractsWithActions::callMountedAction()` already rolls back
 * the database transaction itself before re-throwing a non-Halt/non-Cancel exception, so
 * unlike HandlesSaveExceptions there is no Halt to throw here — we only need to stop the
 * exception before it reaches Livewire uncaught. ValidationException is explicitly excluded
 * so Filament's own inline field-error UI is never touched.
 */
trait HandlesActionExceptions
{
    public function callMountedAction(array $arguments = []): mixed
    {
        try {
            return parent::callMountedAction($arguments);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->reportActionException($e);

            return null;
        }
    }

    protected function reportActionException(Throwable $e): void
    {
        $presented = ExceptionPresenter::present($e);

        Notification::make()
            ->danger()
            ->title($presented['title'])
            ->body($presented['body'])
            ->persistent()
            ->send();
    }
}
