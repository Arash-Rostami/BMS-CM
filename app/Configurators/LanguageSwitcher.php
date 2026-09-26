<?php

namespace App\Configurators;

use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\View\PanelsRenderHook;

class LanguageSwitcher
{
    public static function configure(): void
    {
        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->renderHook(PanelsRenderHook::GLOBAL_SEARCH_BEFORE)
                ->locales(config('language-switch.locales', ['fa', 'en', 'fr']));

        });
    }
}
