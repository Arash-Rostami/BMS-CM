<?php

namespace App\Filament\Pages;

use Livewire\Attributes\On;
use Filament\Resources\Pages\ManageRecords as BaseManageRecords;

class ManageRecords extends BaseManageRecords
{
    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
