<?php

namespace App\Filament\Pages;

use Livewire\Attributes\On;
use Filament\Resources\Pages\ListRecords as BaseListRecords;

class ListRecords extends BaseListRecords
{
    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
