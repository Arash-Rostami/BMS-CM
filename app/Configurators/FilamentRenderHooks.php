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
            PanelsRenderHook::GLOBAL_SEARCH_AFTER,
            fn(): View => view('filament.partials.calendar-toggle')
        );
    }
}
