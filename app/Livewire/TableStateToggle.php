<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class TableStateToggle extends Component
{
    public bool $persisted;

    public function mount(): void
    {
        $this->persisted = (bool) session('persist_table_state', false);
    }

    public function toggle(): void
    {
        $this->persisted = ! $this->persisted;
        session(['persist_table_state' => $this->persisted]);
        $this->dispatch('table-state-toggled');
    }

    public function render(): View
    {
        return view('livewire.table-state-toggle');
    }
}
