<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductResourceTest extends TestCase
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

    public function test_find_by_inquiry_code_returns_null_for_a_blank_code(): void
    {
        $this->assertNull(ProductResource::findByInquiryCode(null));
        $this->assertNull(ProductResource::findByInquiryCode(''));
    }

    public function test_find_by_inquiry_code_returns_null_when_no_product_matches(): void
    {
        $this->assertNull(ProductResource::findByInquiryCode('NO-SUCH-CODE-'.fake()->unique()->bothify('####')));
    }

    public function test_find_by_inquiry_code_returns_the_matching_product(): void
    {
        $product = Product::factory()->create(['code' => 'PRD-INQUIRY-TEST']);

        $found = ProductResource::findByInquiryCode('PRD-INQUIRY-TEST');

        $this->assertNotNull($found);
        $this->assertSame($product->id, $found->id);
    }

    public function test_specification_has_data_returns_false_when_every_field_is_blank(): void
    {
        $this->assertFalse(ProductResource::specificationHasData([
            'hs_code' => null,
            'import_duty' => '',
            'packing_type' => null,
            'vat_exempt' => false,
            'tax_id' => null,
            'manufacturer' => null,
            'import_licenses' => null,
            'extra' => [],
        ]));
    }

    public function test_specification_has_data_returns_true_when_any_text_field_is_filled(): void
    {
        $this->assertTrue(ProductResource::specificationHasData([
            'hs_code' => '7208.10',
            'vat_exempt' => false,
            'extra' => [],
        ]));
    }

    public function test_specification_has_data_treats_vat_exempt_true_as_filled(): void
    {
        $this->assertTrue(ProductResource::specificationHasData([
            'vat_exempt' => true,
        ]));
    }

    public function test_specification_has_data_treats_a_non_empty_extra_array_as_filled(): void
    {
        $this->assertTrue(ProductResource::specificationHasData([
            'vat_exempt' => false,
            'extra' => ['grade' => 'A'],
        ]));
    }
}
