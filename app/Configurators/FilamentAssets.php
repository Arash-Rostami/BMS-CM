<?php

namespace App\Configurators;

use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\Facades\Vite;

class FilamentAssets
{
    /**
     * Get the custom assets to register.
     *
     */
    public static function getAssets(): array
    {
        return [
            Css::make('fi-custom-css', Vite::asset('resources/css/fi-custom.css')),

            // Registering a potential custom JavaScript file with a descriptive handle
//            Js::make('tweak-js', resource_path('js/tweak.js')),
        ];
    }

    public static function register()
    {
        FilamentAsset::register(self::getAssets());
    }
}
