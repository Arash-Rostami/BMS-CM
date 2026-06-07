<?php

namespace App\Filament\Pages;

use Livewire\Attributes\On;
use Filament\Resources\Pages\EditRecord as BaseEditRecord;

class EditRecord extends BaseEditRecord
{
    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
