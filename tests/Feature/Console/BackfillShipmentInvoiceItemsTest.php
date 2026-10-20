<?php

namespace Tests\Feature\Console;

use App\Models\EntityAttribute;
use App\Models\Product;
use App\Models\ProformaInvoice;
use App\Models\ProformaInvoiceItem;
use App\Models\RegisteredOrder;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BackfillShipmentInvoiceItemsTest extends TestCase
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

    private function shipmentWithInvoice(array $lines, array $piProducts, bool $doubleEncode = false): Shipment
    {
        $pi = ProformaInvoice::factory()->create();
        foreach ($piProducts as $product) {
            ProformaInvoiceItem::factory()->create(['proforma_invoice_id' => $pi->id, 'product_id' => $product->id]);
        }

        $shipment = Shipment::factory()->create(['registered_order_id' => RegisteredOrder::factory()->create()->id]);
        $header = ['proforma_invoice_id' => $pi->id, 'items' => $lines, 'grand_total' => 120];

        EntityAttribute::create([
            'entity_type' => Shipment::class,
            'entity_id' => $shipment->id,
            'key' => 'commercial_invoice',
            'value' => $doubleEncode ? json_encode($header) : $header,
        ]);

        return $shipment;
    }

    private function line(string $name): array
    {
        return ['english_description' => $name, 'quantity' => 10, 'unit_price' => 10, 'total_amount' => 100, 'net_weight' => 5, 'gross_weight' => 6];
    }

    public function test_it_copies_lines_links_products_and_sets_totals_and_is_idempotent(): void
    {
        $citrate = Product::factory()->create(['name' => 'سیترات', 'english_name' => 'Citrate Tri Sodium']);
        $other = Product::factory()->create(['name' => 'پت', 'english_name' => 'Pet Resin']);
        $shipment = $this->shipmentWithInvoice([$this->line('CITRATE   tri-sodium'), $this->line('Unknown thing')], [$citrate, $other]);

        $this->artisan('shipments:backfill-invoice-items')->run();

        $items = $shipment->items()->orderBy('id')->get();
        $this->assertCount(2, $items);
        $this->assertSame('CITRATE   tri-sodium', $items[0]->english_name, 'the legacy english_description key lands in english_name');
        $this->assertSame($citrate->id, $items[0]->product_id);
        $this->assertNull($items[1]->product_id, 'an unknown name on a multi-product invoice is flagged, never guessed');

        $shipment->refresh();
        $this->assertEquals(10, $shipment->total_net_weight);
        $this->assertEquals(12, $shipment->total_gross_weight);
        $this->assertEquals(120, $shipment->grand_total);

        $this->artisan('shipments:backfill-invoice-items')->run();
        $this->assertCount(2, $shipment->items()->get());
    }

    public function test_a_single_product_proforma_links_every_line_and_double_encoded_json_is_decoded(): void
    {
        $product = Product::factory()->create(['name' => 'پت', 'english_name' => 'Pet Resin Bottle Grade']);
        $shipment = $this->shipmentWithInvoice([$this->line('anything at all')], [$product], doubleEncode: true);

        $this->artisan('shipments:backfill-invoice-items')->run();

        $this->assertSame($product->id, $shipment->items()->first()->product_id);
    }

    public function test_dry_run_writes_nothing_and_prune_strips_items_only_after_a_verified_copy(): void
    {
        $product = Product::factory()->create(['english_name' => 'Dry Run Product']);
        $shipment = $this->shipmentWithInvoice([$this->line('Dry Run Product')], [$product]);

        $this->artisan('shipments:backfill-invoice-items', ['--dry-run' => true])->run();
        $this->assertSame(0, $shipment->items()->count());

        $this->artisan('shipments:backfill-invoice-items', ['--prune' => true])->run();

        $header = EntityAttribute::where('entity_id', $shipment->id)->where('key', 'commercial_invoice')->first()->value;
        $this->assertSame(1, $shipment->items()->count());
        $this->assertArrayNotHasKey('items', $header);
        $this->assertSame(120, $header['grand_total']);
    }

    public function test_free_text_origin_and_unit_are_stored_as_option_keys(): void
    {
        $product = Product::factory()->create(['english_name' => 'Keys Product']);
        $shipment = $this->shipmentWithInvoice([[...$this->line('Keys Product'), 'origin' => 'china', 'unit' => 'کیلوگرم']], [$product]);

        $this->artisan('shipments:backfill-invoice-items')->run();

        $row = $shipment->items()->first();
        $this->assertSame(['CN', 'kg'], [$row->origin, $row->unit]);
    }

    public function test_prune_on_a_later_run_strips_items_of_a_shipment_copied_earlier(): void
    {
        $product = Product::factory()->create(['english_name' => 'Later Prune Product']);
        $shipment = $this->shipmentWithInvoice([$this->line('Later Prune Product')], [$product]);

        $this->artisan('shipments:backfill-invoice-items')->run();
        $this->artisan('shipments:backfill-invoice-items', ['--prune' => true])->run();

        $header = EntityAttribute::where('entity_id', $shipment->id)->where('key', 'commercial_invoice')->first()->value;
        $this->assertArrayNotHasKey('items', $header);
        $this->assertSame(1, $shipment->items()->count());
    }

    public function test_trashed_shipments_are_included(): void
    {
        $product = Product::factory()->create(['english_name' => 'Trashed Product']);
        $shipment = $this->shipmentWithInvoice([$this->line('Trashed Product')], [$product]);
        $shipment->delete();

        $this->artisan('shipments:backfill-invoice-items')->run();

        $this->assertSame(1, $shipment->items()->count());
    }
}
