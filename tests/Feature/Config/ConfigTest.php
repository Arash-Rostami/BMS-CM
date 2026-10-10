<?php

namespace Tests\Feature\Config;

use App\Configurators\FilamentAssets;
use App\Filament\Resources\Master\CalendarRuleResource\Enums\CalendarColor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Master file for the four project-owned config files. palettes.php's six
 * drift checks folded in from the former tests/Unit/Config companion
 * (config <-> themes.css <-> lang <-> meta partial <-> theme.js must stay
 * in lockstep); workspace.php and desk-reference.php get the contracts the
 * config comments themselves declare but nothing verified: every workspace
 * entry must point at a real model, a real named edit route, and real
 * table columns (a wrong route/column silently breaks record pinning with
 * no other signal), and every desk-reference entry must carry its required
 * keys with a resolvable model; calendar.php's queue/time/limit keys must
 * exist (the scheduler, the jobs' onQueue() and the engine's limits all
 * read them — a renamed key degrades silently to defaults) and every
 * CalendarColor case must keep its fi-custom.css var and its 3-locale
 * label (a dropped case renders an unstyled gray rule name).
 */
class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function useMysql(): void
    {
        $env = base_path('.env');
        if (is_file($env)) {
            $vals = [];
            foreach (explode("\n", (string) file_get_contents($env)) as $line) {
                if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $line, $m)) {
                    $vals[$m[1]] = trim(preg_replace('/\s+#.*$/', '', trim($m[2])), "\"' \t");
                }
            }
            $map = ['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'];
            foreach ($map as $envKey => $cfgKey) {
                if (isset($vals[$envKey])) {
                    config(['database.connections.mysql.'.$cfgKey => $vals[$envKey]]);
                }
            }
        }
        DB::purge('mysql');
        config(['database.default' => 'mysql']);
    }

    // config/palettes.php

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
                    trans()->has("resources/general/strings.theme_palette.palettes.{$key}", $locale),
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
        $this->assertStringNotContainsString("CustomEvent('theme-changed', { detail: { palette", $js);
        $this->assertStringContainsString("addEventListener('livewire:navigated'", $js);
        $this->assertStringContainsString("addEventListener('livewire:navigating'", $js);
        $this->assertStringContainsString('onSwap', $js);
        $this->assertStringContainsString('applySavedTheme();', $js);
        $this->assertStringContainsString("addEventListener('storage'", $js);
        $this->assertStringContainsString('applySavedDarkMode', $js);
        $this->assertStringContainsString("CustomEvent('dark-mode-toggled'", $js);
    }

    // config/workspace.php

    public function test_workspace_entries_reference_real_models_and_named_edit_routes(): void
    {
        foreach (config('workspace.resources') as $key => $entry) {
            $this->assertTrue(
                class_exists($entry['model']),
                "Workspace entry [{$key}] points at a non-existent model [{$entry['model']}]."
            );
            $this->assertTrue(
                Route::getRoutes()->hasNamedRoute($entry['route']),
                "Workspace entry [{$key}] points at a non-existent route [{$entry['route']}] — the pin's Edit link 404s."
            );
        }
    }

    public function test_workspace_title_and_subtitle_columns_exist_on_each_model(): void
    {
        foreach (config('workspace.resources') as $key => $entry) {
            $table = (new $entry['model'])->getTable();
            $columns = array_merge(
                $entry['title'],
                $entry['subtitle'] ?? [],
                $entry['search'] ?? []
            );

            $this->assertTrue(
                Schema::hasColumns($table, $columns),
                "Workspace entry [{$key}] references a column that doesn't exist on [{$table}]."
            );
        }
    }

    // config/calendar.php

    public function test_calendar_config_declares_its_expected_keys(): void
    {
        foreach (['channels', 'queue', 'alert_time', 'rebuild_time', 'max_overdue_alerts', 'max_path_depth', 'preview_limit', 'sync_chunk'] as $key) {
            $this->assertArrayHasKey(
                $key,
                config('calendar'),
                "config/calendar.php is missing its [{$key}] key — scheduler, jobs or the engine silently fall back to defaults."
            );
        }

        $this->assertSame('07:00', config('calendar.alert_time'));
        $this->assertSame('02:00', config('calendar.rebuild_time'));
        $this->assertSame(3, config('calendar.max_overdue_alerts'));
        $this->assertSame(500, config('calendar.sync_chunk'));
    }

    public function test_every_calendar_color_has_a_css_var_and_labels(): void
    {
        $css = File::get(resource_path('css/fi-custom.css'));

        foreach (CalendarColor::cases() as $color) {
            $this->assertStringContainsString(
                "--cal-color-{$color->value}:",
                $css,
                "CalendarColor [{$color->value}] has no --cal-color- var in fi-custom.css."
            );
            foreach (['en', 'fa', 'fr'] as $locale) {
                $this->assertTrue(
                    trans()->has("resources/calendarRule/strings.color.{$color->value}", $locale),
                    "CalendarColor [{$color->value}] has no {$locale} label."
                );
            }
        }
    }

    // config/desk-reference.php

    public function test_desk_reference_entries_carry_required_keys_and_real_models(): void
    {
        foreach (config('desk-reference') as $key => $entry) {
            foreach (['model', 'icon', 'group', 'version'] as $required) {
                $this->assertArrayHasKey(
                    $required,
                    $entry,
                    "Desk-reference entry [{$key}] is missing its [{$required}] key."
                );
            }
            $this->assertTrue(
                class_exists($entry['model']),
                "Desk-reference entry [{$key}] points at a non-existent model [{$entry['model']}]."
            );
        }
    }
}
