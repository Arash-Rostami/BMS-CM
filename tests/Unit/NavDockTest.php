<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class NavDockTest extends TestCase
{
    public function test_peek_state_is_wired_across_js_css_and_lang(): void
    {
        $this->assertStringContainsString("'nav-dock-peek'", File::get(resource_path('js/filament/nav-dock.js')));
        $this->assertStringContainsString('nav-dock-peek .fi-sidebar-nav', File::get(resource_path('css/fi-custom.css')));
        $this->assertStringContainsString('position: sticky', File::get(resource_path('css/fi-custom.css')));
        $this->assertStringContainsString('.dock-min', File::get(resource_path('js/filament/nav-dock.js')));
        $this->assertStringContainsString('chevron-double-down', File::get(resource_path('views/filament/partials/dock-min.blade.php')));

        $rendered = view('filament.partials.dock-min')->render();
        $this->assertStringContainsString('class="dock-min', $rendered);

        foreach (['en', 'fa', 'fr'] as $locale) {
            $this->assertTrue(
                Lang::has('resources/general/strings.nav_dock.switch_to_peek', $locale),
                "nav_dock.switch_to_peek is missing in [{$locale}]."
            );
        }
    }
}
