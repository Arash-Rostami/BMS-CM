<?php

namespace Tests\Unit;

use App\Configurators\FilamentAssets;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

class PalettesTest extends TestCase
{
    public function test_panel_assets_include_theme_entries(): void
    {
        $ids = array_map(fn ($asset) => $asset->getId(), FilamentAssets::getAssets());

        $this->assertContains('themes-css', $ids);
        $this->assertContains('theme-js', $ids);
    }

    public function test_every_palette_has_a_label_in_all_locales(): void
    {
        foreach (array_keys(config('palettes')) as $key) {
            foreach (['en', 'fa', 'fr'] as $locale) {
                $this->assertTrue(
                    Lang::has("resources/general/strings.theme_palette.palettes.{$key}", $locale),
                    "Palette [{$key}] has no {$locale} label."
                );
            }
        }
    }

    public function test_every_non_default_palette_has_a_css_block(): void
    {
        $css = File::get(resource_path('css/themes.css'));

        foreach (array_keys(config('palettes')) as $key) {
            if ($key === 'slate') {
                continue;
            }

            $this->assertStringContainsString(
                "html[data-theme=\"{$key}\"] {",
                $css,
                "Palette [{$key}] is declared in config/palettes.php but has no light block in themes.css."
            );
            $this->assertStringContainsString(
                "html[data-theme=\"{$key}\"].dark",
                $css,
                "Palette [{$key}] is declared in config/palettes.php but has no dark block in themes.css."
            );
        }
    }

    public function test_themes_css_has_no_orphan_blocks(): void
    {
        $css = File::get(resource_path('css/themes.css'));
        preg_match_all('/html\[data-theme="([^"]+)"\]/', $css, $matches);
        $keys = config('palettes');

        foreach (array_unique($matches[1]) as $block) {
            $this->assertArrayHasKey(
                $block,
                $keys,
                "themes.css has a [{$block}] block that config/palettes.php does not declare."
            );
        }
    }

    public function test_meta_partial_exports_the_palette_list(): void
    {
        $rendered = view('filament.partials.meta')->render();

        $this->assertStringContainsString('window.BMS_THEMES=', $rendered);
        foreach (array_keys(config('palettes')) as $key) {
            $this->assertStringContainsString("\"{$key}\"", $rendered);
        }
    }

    public function test_palette_switcher_dispatches_palette_changed_not_theme_changed(): void
    {
        $js = File::get(resource_path('js/filament/theme.js'));

        $this->assertStringContainsString("'palette-changed'", $js);
        $this->assertStringNotContainsString("CustomEvent('theme-changed'", $js);
        $this->assertStringNotContainsString("'theme-changed'", $js);
        $this->assertStringContainsString("addEventListener('livewire:navigated'", $js);
    }
}
