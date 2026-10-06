<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class RowClickToggle extends Component
{
    public bool $editOnClick;

    public function mount(): void
    {
        $this->editOnClick = (bool) session('row_click_edit', false);
    }

    public function toggle(): void
    {
        $this->editOnClick = ! $this->editOnClick;
        session(['row_click_edit' => $this->editOnClick]);
        $this->dispatch('row-click-toggled');
    }

    public function render(): View
    {
        return view('livewire.row-click-toggle');
    }
}
