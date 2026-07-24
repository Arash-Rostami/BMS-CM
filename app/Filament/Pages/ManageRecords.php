<?php

namespace App\Filament\Pages;

use App\Filament\Traits\PrefillsTableSearch;
use Filament\Resources\Pages\ManageRecords as BaseManageRecords;
use Livewire\Attributes\On;

class ManageRecords extends BaseManageRecords
{
    use PrefillsTableSearch;

    #[On('calendar-toggled')]
    public function calendarToggled(): void {}
}
