<?php

namespace App\Filament\Pages;

use Filament\Resources\Pages\EditRecord as BaseEditRecord;
use Livewire\Attributes\On;

class EditRecord extends BaseEditRecord
{
    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
