<?php

namespace App\Configurators;

use BezhanSalleh\FilamentLanguageSwitch\Enums\Placement;
use BezhanSalleh\FilamentLanguageSwitch\LanguageSwitch;
use Illuminate\Support\Facades\Vite;

class LanguageSwitcher
{
    public static function configure(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(config('language-switch.locales', ['fa', 'en' ,'fr']))
                ->outsidePanelPlacement(
                    config('language-switch.outside_panel_placement', Placement::BottomRight)
                )
                ->circular()
                ->flags([
                    'fa' => Vite::asset('resources/img/flags/iran.svg'),
                    'en' => Vite::asset('resources/img/flags/usa.svg'),
                    'fr' => Vite::asset('resources/img/flags/france.svg'),
                ]);
        });
    }
}
