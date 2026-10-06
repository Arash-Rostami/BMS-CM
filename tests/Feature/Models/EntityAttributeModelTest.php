<?php

namespace Tests\Feature\Models;

use App\Models\EntityAttribute;
use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EntityAttributeModelTest extends TestCase
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

    public function test_value_round_trips_through_the_json_cast(): void
    {
        $payload = ['nested' => ['depth' => 2], 'flag' => true, 'text' => 'some value'];
        $record = PurchaseRequest::factory()->create();

        $attribute = EntityAttribute::factory()->forEntity($record)->create(['key' => 'spec', 'value' => $payload]);

        $this->assertEquals($payload, $attribute->fresh()->value);
    }

    public function test_entity_morph_to_resolves_the_owning_record(): void
    {
        $record = PurchaseRequest::factory()->create();
        $attribute = EntityAttribute::factory()->forEntity($record)->create(['key' => 'origin', 'value' => 'test']);

        $this->assertTrue($attribute->entity->is($record));
        $this->assertSame(PurchaseRequest::class, $attribute->entity_type);
        $this->assertSame($record->id, $attribute->entity_id);
    }

    public function test_owning_record_sees_the_attribute_through_the_morph_many_relation(): void
    {
        $record = PurchaseRequest::factory()->create();
        $attribute = EntityAttribute::factory()->forEntity($record)->create(['key' => 'origin', 'value' => 'test']);

        $this->assertTrue($record->customAttributes->contains('id', $attribute->id));
        $this->assertSame('origin', $record->customAttributes->first()->key);
    }

    public function test_soft_delete_hides_the_attribute_from_the_parent_relation(): void
    {
        $record = PurchaseRequest::factory()->create();
        $attribute = EntityAttribute::factory()->forEntity($record)->create(['key' => 'origin', 'value' => 'test']);

        $attribute->delete();

        $this->assertFalse($record->fresh()->customAttributes->contains('id', $attribute->id));
        $this->assertNotNull(EntityAttribute::withTrashed()->find($attribute->id));
    }
}