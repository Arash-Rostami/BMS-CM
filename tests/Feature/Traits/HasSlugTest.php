<?php

namespace Tests\Feature\Traits;

use App\Models\Category;
use App\Models\Product;
use App\Models\Traits\General\HasSlug;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * App\Models\Traits\General\HasSlug boots a `saving` hook that derives `slug` from
 * `english_name` via Str::slug(), with a numeric collision suffix, skipping when
 * english_name is empty or unchanged on an existing row. Composed on 2 unrelated
 * models (Category, Product) — cross-cutting, and one of only 3 General traits
 * allowed a boot* method, so its own collision-suffix branch is worth a direct test.
 */
class HasSlugTest extends TestCase
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

    public function test_category_composes_the_trait(): void
    {
        $this->assertContains(HasSlug::class, class_uses_recursive(Category::class));
    }

    public function test_slug_is_derived_from_english_name_on_create(): void
    {
        $unique = 'Trait Slug Fixture '.uniqid();
        $category = Category::factory()->create(['english_name' => $unique, 'slug' => null]);

        $this->assertSame(\Illuminate\Support\Str::slug($unique), $category->slug);
    }

    public function test_a_collision_gets_a_numeric_suffix(): void
    {
        $unique = 'Trait Slug Collide '.uniqid();
        $first = Category::factory()->create(['english_name' => $unique, 'slug' => null]);
        $second = Category::factory()->create(['english_name' => $unique, 'slug' => null]);

        $base = \Illuminate\Support\Str::slug($unique);
        $this->assertSame($base, $first->slug);
        $this->assertSame($base.'-1', $second->slug);
    }

    public function test_slug_is_untouched_when_english_name_is_unchanged_on_update(): void
    {
        $unique = 'Trait Slug Stable '.uniqid();
        $product = Product::factory()->create(['english_name' => $unique, 'slug' => null]);
        $originalSlug = $product->slug;

        $product->description = 'updated description';
        $product->save();

        $this->assertSame($originalSlug, $product->refresh()->slug);
    }

    public function test_slug_is_skipped_entirely_when_english_name_is_empty(): void
    {
        $product = Product::factory()->create(['english_name' => '', 'slug' => 'kept-as-is']);

        $this->assertSame('kept-as-is', $product->refresh()->slug);
    }
}
