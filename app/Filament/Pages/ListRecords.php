<?php

namespace App\Filament\Pages;

use App\Filament\Traits\HandlesActionExceptions;
use Filament\Resources\Pages\ListRecords as BaseListRecords;
use Livewire\Attributes\On;

class ListRecords extends BaseListRecords
{
    use HandlesActionExceptions;

    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
