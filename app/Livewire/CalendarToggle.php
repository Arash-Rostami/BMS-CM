<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class CalendarToggle extends Component
{
    public bool $isJalali;

    public function mount(): void
    {
        $this->isJalali = session('calendar_type', app()->isLocale('fa') ? 'jalali' : 'gregorian') === 'jalali';
    }

    public function toggle(): void
    {
        $this->isJalali = !$this->isJalali;
        session(['calendar_type' => $this->isJalali ? 'jalali' : 'gregorian']);
        $this->dispatch('calendar-toggled');
    }

    public function render(): View
    {
        return view('livewire.calendar-toggle');
    }
}
