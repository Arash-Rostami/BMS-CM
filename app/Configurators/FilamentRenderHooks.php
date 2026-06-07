<?php

namespace App\Configurators;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

class FilamentRenderHooks
{
    public static function configure(): void
    {
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): View => view('filament.partials.calendar-toggle')
        );
    }
}
