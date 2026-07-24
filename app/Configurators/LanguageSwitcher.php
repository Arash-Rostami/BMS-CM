<?php

namespace App\Configurators;

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Vite;

class LanguageSwitcher
{
    public static function configure(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE)
                ->locales(config('language-switch.locales', ['fa', 'en', 'fr']))
                ->flags([
                    'fa' => Vite::asset('resources/img/flags/iran.svg'),
                    'en' => Vite::asset('resources/img/flags/usa.svg'),
                    'fr' => Vite::asset('resources/img/flags/france.svg'),
                ])
                ->flagsOnly();

        });
    }
}
