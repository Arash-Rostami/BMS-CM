<?php

namespace App\Filament\Pages;

use App\Filament\Traits\PrefillsTableSearch;
use Livewire\Attributes\On;
use Filament\Resources\Pages\ManageRecords as BaseManageRecords;

class ManageRecords extends BaseManageRecords
{
    use PrefillsTableSearch;

    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
