<?php

namespace App\Filament\Pages;

use App\Filament\Traits\HandlesActionExceptions;
use App\Filament\Traits\HandlesSaveExceptions;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Throwable;

class CreateRecord extends BaseCreateRecord
{
    use HandlesActionExceptions;
    use HandlesSaveExceptions;

    #[On('calendar-toggled')]
    public function calendarToggled(): void
    {
        $this->redirect(request()->header('Referer'));
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->keyBindings(['mod+s']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (Halt $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->reportSaveException($e);
        }
    }

    public function getDefaultTestingSchemaName(): ?string
    {
        return 'form';
    }
}
