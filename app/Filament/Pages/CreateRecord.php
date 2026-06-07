<?php

namespace App\Filament\Pages;

use Livewire\Attributes\On;
use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;

class CreateRecord extends BaseCreateRecord
{
    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
