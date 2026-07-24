<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\ListRecords as BaseListRecords;
use Livewire\Attributes\On;

class ListRecords extends BaseListRecords
{
    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
