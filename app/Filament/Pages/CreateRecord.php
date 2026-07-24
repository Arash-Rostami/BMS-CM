<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;
use Livewire\Attributes\On;

class CreateRecord extends BaseCreateRecord
{
    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
