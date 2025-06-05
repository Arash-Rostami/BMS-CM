<?php

namespace App\Configurators;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;

class FilamentAssets
{
    /**
     * Get the custom assets to register.
     *
     */
    public static function getAssets(): array
    {
        return [
            Css::make('fi-custom-css', resource_path('css/fi-custom.css')),

            // Registering a potential custom JavaScript file with a descriptive handle
//            Js::make('tweak-js', resource_path('js/tweak.js')),
        ];
    }

    public static function register()
    {
        FilamentAsset::register(self::getAssets());
    }
}
