<?php

namespace App\Filament\Pages;

use App\Filament\Traits\HandlesActionExceptions;
use App\Filament\Traits\HandlesSaveExceptions;
use Filament\Resources\Pages\EditRecord as BaseEditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Throwable;

class EditRecord extends BaseEditRecord
{
    use HandlesActionExceptions;
    use HandlesSaveExceptions;

    #[On('calendar-toggled')]
    public function calendarToggled(): void
    {
        $this->redirect(request()->header('Referer'));
    }

    protected function authorizeAccess(): void
    {
        parent::authorizeAccess();

        abort_if(method_exists($this->getRecord(), 'trashed') && $this->getRecord()->trashed(), 403);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (Halt $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->reportSaveException($e);
        }
    }
}
