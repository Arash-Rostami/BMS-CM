<?php

namespace App\Livewire\LandingPage;

use Livewire\Component;

class Features extends Component
{
    public bool $isRtl = false;

    public function mount(bool $isRtl): void
    {
        $this->isRtl = $isRtl;
    }

    public function render()
    {
        return view('livewire.landing-page.features', [
            'panelTitle' => __('dashboard/strings.app_features.panel_title'),
            'panelIntro' => __('dashboard/strings.app_features.panel_intro'),
            'distinguishing' => __('dashboard/strings.app_features.distinguishing'),
            'standard' => __('dashboard/strings.app_features.standard'),
        ]);
    }
}
