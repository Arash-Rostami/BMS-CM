<?php

namespace App\Filament\Pages;

use App\Filament\Traits\HandlesActionExceptions;
use Filament\Resources\Pages\ListRecords as BaseListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;

class ListRecords extends BaseListRecords
{
    use HandlesActionExceptions;

    #[On('calendar-toggled')]
    public function calendarToggled(): void {}

    #[On('row-click-toggled')]
    public function rowClickToggled(): void {}

    protected function makeTable(): Table
    {
        return parent::makeTable()->recordUrl(function (Model $record): ?string {
            if (! session('row_click_edit', false)) {
                return null;
            }

            $resource = static::getResource();

            if (! $resource::hasPage('edit') || ! $resource::canEdit($record)) {
                return null;
            }

            return $this->getResourceUrl('edit', ['record' => $record]);
        });
    }
}
