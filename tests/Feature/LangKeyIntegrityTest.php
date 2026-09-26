<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Lang;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

class LangKeyIntegrityTest extends TestCase
{
    public function test_filament_lang_keys_exist_in_all_locales(): void
    {
        $keys = [];

        foreach (Finder::create()->in(app_path('Filament'))->name('*.php')->files() as $file) {
            preg_match_all("/__\(\s*'([a-zA-Z0-9_\/\.]+)'/", $file->getContents(), $matches);

            foreach ($matches[1] as $key) {
                if (str_starts_with($key, 'resources/') && ! str_ends_with($key, '.')) {
                    $keys[$key] = $file->getRelativePathname();
                }
            }
        }

        $this->assertNotEmpty($keys);

        $missing = [];

        foreach (['en', 'fa', 'fr'] as $locale) {
            app()->setLocale($locale);

            foreach ($keys as $key => $file) {
                if (! Lang::has($key)) {
                    $missing[] = "{$locale} :: {$key} ({$file})";
                }
            }
        }

        $this->assertSame([], $missing, "Missing lang keys:\n".implode("\n", $missing));
    }
}
