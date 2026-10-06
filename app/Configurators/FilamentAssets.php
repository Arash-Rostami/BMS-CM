<?php

namespace App\Configurators;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Vite;

class FilamentAssets
{
    /**
     * Get the custom assets to register.
     */
    public static function getAssets(): array
    {
        return [
            Css::make('fi-custom-css', Vite::asset('resources/css/fi-custom.css')),
            Css::make('themes-css', Vite::asset('resources/css/themes.css')),
            Js::make('theme-js', Vite::asset('resources/js/filament/theme.js')),
            Js::make('nav-dock-js', Vite::asset('resources/js/filament/nav-dock.js')),
            Js::make('topbar-autohide-js', Vite::asset('resources/js/filament/topbar-autohide.js')),
            Js::make('auto-close-js', Vite::asset('resources/js/filament/auto-close.js')),
            Js::make('table-density-js', Vite::asset('resources/js/filament/table-density.js')),
            Js::make('table-stacking-js', Vite::asset('resources/js/filament/table-stacking.js')),
            Js::make('fullscreen-js', Vite::asset('resources/js/filament/fullscreen.js')),
            Js::make('recents-js', Vite::asset('resources/js/filament/recents.js')),
            Js::make('filepond-locale-js', Vite::asset('resources/js/filament/filepond-locale.js')),
        ];
    }

    public static function register()
    {
        FilamentAsset::register(self::getAssets());
    }
}
