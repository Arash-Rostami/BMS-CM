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
            Js::make('nav-dock-js', Vite::asset('resources/js/filament/nav-dock.js')),
            Js::make('topbar-autohide-js', Vite::asset('resources/js/filament/topbar-autohide.js')),
        ];
    }

    public static function register()
    {
        FilamentAsset::register(self::getAssets());
    }
}
