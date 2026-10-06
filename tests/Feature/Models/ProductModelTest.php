<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductModelTest extends TestCase
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

    // normalizeCode()

    public function test_normalize_code_trims_and_uppercases(): void
    {
        $this->assertSame('ABC-1', Product::normalizeCode('  abc-1  '));
        $this->assertSame('ABC-1', Product::normalizeCode('ABC-1'));
        $this->assertNull(Product::normalizeCode(null));
    }

    // Localization

    public function test_get_localized_name_attribute_returns_persian_name_for_fa_locale(): void
    {
        app()->setLocale('fa');
        $product = new Product(['name' => 'نام فارسی', 'english_name' => 'English Name']);

        $this->assertSame('نام فارسی', $product->getLocalizedNameAttribute());
    }

    public function test_get_localized_name_attribute_returns_english_name_for_non_fa_locale(): void
    {
        app()->setLocale('en');
        $product = new Product(['name' => 'نام فارسی', 'english_name' => 'English Name']);

        $this->assertSame('English Name', $product->getLocalizedNameAttribute());
    }

    // Casts

    public function test_boolean_and_array_casts_are_applied(): void
    {
        $product = Product::factory()->create([
            'in_stock' => 1,
            'is_active' => 0,
            'attributes' => ['red', 'large'],
        ]);

        $fresh = $product->fresh();

        $this->assertTrue($fresh->in_stock);
        $this->assertFalse($fresh->is_active);
        $this->assertIsArray($fresh->attributes);
        $this->assertSame(['red', 'large'], $fresh->attributes);
    }

    // category() relation

    public function test_category_relation_resolves_the_belongs_to_category(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $this->assertTrue($product->category->is($category));
    }

    public function test_category_relation_is_null_when_category_id_is_null(): void
    {
        $product = new Product(['category_id' => null]);

        $this->assertNull($product->category);
    }

    // determineRollOrSheetType()

    public function test_determine_roll_or_sheet_type_returns_sheet_when_an_attribute_contains_an_asterisk(): void
    {
        $product = new Product(['category_id' => null, 'attributes' => ['100*200*5']]);

        $this->assertSame('Sheet', $product->determineRollOrSheetType());
    }

    public function test_determine_roll_or_sheet_type_returns_roll_when_an_attribute_contains_cm(): void
    {
        $product = new Product(['category_id' => null, 'attributes' => ['120cm']]);

        $this->assertSame('Roll', $product->determineRollOrSheetType());
    }

    public function test_determine_roll_or_sheet_type_returns_null_when_no_pattern_matches(): void
    {
        $product = new Product(['category_id' => null, 'attributes' => ['plain-tag']]);

        $this->assertNull($product->determineRollOrSheetType());
    }

    public function test_determine_roll_or_sheet_type_returns_null_when_the_category_tree_is_not_the_roll_sheet_ancestor(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'attributes' => ['120cm']]);

        $this->assertNull($product->fresh()->determineRollOrSheetType());
    }

    // typedAttributes()

    public function test_typed_attributes_parses_known_patterns(): void
    {
        $product = new Product(['attributes' => ['99.5%', '120cm', '50gr']]);

        $typed = $product->typedAttributes();

        $this->assertSame('99.5%', $typed['Purity']);
        $this->assertSame('120cm', $typed['Length (cm)']);
        $this->assertSame('50gr', $typed['Weight']);
    }

    // getCustomizedLabelAttribute()

    public function test_customized_label_combines_code_name_and_attributes(): void
    {
        app()->setLocale('en');
        $product = new Product(['code' => 'PRD-1', 'english_name' => 'Test Product', 'attributes' => ['red', 'large']]);

        $this->assertSame('PRD-1 - Test Product (red, large)', $product->getCustomizedLabelAttribute());
    }

    // HasSlug

    public function test_slug_is_generated_from_english_name_on_create(): void
    {
        $product = Product::factory()->create(['english_name' => 'Unique Slug Source']);

        $this->assertSame('unique-slug-source', $product->fresh()->slug);
    }

    public function test_slug_de_duplicates_on_collision(): void
    {
        Product::factory()->create(['english_name' => 'Duplicate Slug Source']);
        $second = Product::factory()->create(['english_name' => 'Duplicate Slug Source']);

        $this->assertSame('duplicate-slug-source-1', $second->fresh()->slug);
    }
}
