<?php

namespace Tests\Feature\Integrity;

use App\Services\Calendar\CalendarPathResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Schema;
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

    public function test_lang_files_keep_parity_and_every_model_column_has_a_translated_label(): void
    {
        $problems = [];

        foreach (Finder::create()->in(lang_path('en'))->name('*.php')->files() as $file) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());
            $english = Arr::dot(require $file->getPathname());

            foreach (['fa', 'fr'] as $locale) {
                $translated = Arr::dot(require lang_path("{$locale}/{$relative}"));

                foreach (array_diff_key($english, $translated) as $key => $value) {
                    if (! str_contains($key, 'container_types_with_opt')) {
                        $problems[] = "{$locale} missing {$relative} :: {$key}";
                    }
                }

                if (str_starts_with($relative, 'deskReference/')) {
                    continue;
                }

                foreach ($translated as $key => $value) {
                    if (is_string($value) && ($english[$key] ?? null) === $value && ! $this->isNeutral($value)) {
                        $problems[] = "{$locale} untranslated {$relative} :: {$key} = {$value}";
                    }
                }
            }
        }

        $this->assertSame([], $problems, implode(PHP_EOL, $problems));

        $this->useMysql();
        $resolver = app(CalendarPathResolver::class);

        foreach (Finder::create()->in(app_path('Models'))->name('*.php')->depth(0)->files() as $file) {
            $class = 'App\\Models\\'.$file->getBasename('.php');

            if (! is_subclass_of($class, Model::class) || ! Schema::hasTable($table = (new $class)->getTable())) {
                continue;
            }

            foreach (Schema::getColumnListing($table) as $column) {
                app()->setLocale('en');
                $english = $resolver->columnLabel($class, $column);

                foreach (['fa', 'fr'] as $locale) {
                    app()->setLocale($locale);
                    $label = $resolver->columnLabel($class, $column);

                    if ($label === $english && ! $this->isNeutral($label)) {
                        $problems[] = "{$locale} column {$class}.{$column} = {$label}";
                    }
                }
            }
        }

        app()->setLocale('en');

        $this->assertSame([], $problems, 'Untranslated labels:'.PHP_EOL.implode(PHP_EOL, $problems));
    }

    private function isNeutral(string $value): bool
    {
        $cognates = ['Action', 'Description', 'Notes', 'Note', 'Type', 'Types', 'Module', 'Modules', 'Conditions', 'Slug', 'Incoterms', 'Image', 'Version', 'Classification', 'Documents', 'Permissions', 'Permission', 'Actions', 'Table', 'Tables', 'Public', 'Ratio', 'Stock', 'Code', 'Agent', 'Junior', 'Senior', 'Multimodal', 'Rose', 'Violet', 'Orange', 'Zinc', 'Indigo', 'Olive', 'Pause', 'Guide', 'Widgets', 'Performance', 'Agenda', 'Notification', 'Notifications', 'Proforma', 'Gallon', 'Standard', 'Open', 'Top', 'Flat', 'Rack', 'High', 'Cube', 'Total', 'via', 'Extra', 'PorterrA'];
        $words = preg_split('/[\s()\/:.,|·-]+/u', preg_replace('/:\w+/', '', $value), -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $word) {
            if (preg_match('/[A-Za-z]{2,}/', $word) && ! in_array($word, $cognates, true) && $word !== strtoupper($word) && ! preg_match('/\d/', $word)) {
                return false;
            }
        }

        return true;
    }

    private function useMysql(): void
    {
        $env = base_path('.env');

        if (is_file($env)) {
            $values = [];

            foreach (file($env) ?: [] as $line) {
                if (preg_match('/^\s*(DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$/', $line, $m)) {
                    $values[$m[1]] = trim(preg_replace('/\s+#.*$/', '', trim($m[2])), " 	\"'");
                }
            }

            foreach (['DB_HOST' => 'host', 'DB_PORT' => 'port', 'DB_DATABASE' => 'database', 'DB_USERNAME' => 'username', 'DB_PASSWORD' => 'password'] as $envKey => $configKey) {
                if (isset($values[$envKey])) {
                    config(["database.connections.mysql.{$configKey}" => $values[$envKey]]);
                }
            }
        }

        DB::purge('mysql');
        config(['database.default' => 'mysql']);
    }
}
