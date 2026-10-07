<?php

namespace Tests\Feature\Models;

use App\Models\EntityAttribute;
use App\Models\PurchaseRequest;
use App\Models\User;
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

    public function test_restore_brings_a_soft_deleted_attribute_back(): void
    {
        $record = PurchaseRequest::factory()->create();
        $attribute = EntityAttribute::factory()->forEntity($record)->create(['key' => 'origin', 'value' => 'test']);
        $attribute->delete();

        $attribute->restore();

        $this->assertTrue($record->fresh()->customAttributes->contains('id', $attribute->id));
    }

    public function test_value_cast_preserves_scalar_types(): void
    {
        $record = PurchaseRequest::factory()->create();

        $string = EntityAttribute::factory()->forEntity($record)->create(['key' => 'a', 'value' => 'plain'])->fresh();
        $int = EntityAttribute::factory()->forEntity($record)->create(['key' => 'b', 'value' => 42])->fresh();
        $bool = EntityAttribute::factory()->forEntity($record)->create(['key' => 'c', 'value' => false])->fresh();
        $list = EntityAttribute::factory()->forEntity($record)->create(['key' => 'd', 'value' => [1, 2, 3]])->fresh();

        $this->assertSame('plain', $string->value);
        $this->assertSame(42, $int->value);
        $this->assertFalse($bool->value);
        $this->assertSame([1, 2, 3], $list->value);
    }

    public function test_value_is_stored_as_json_text_in_the_database(): void
    {
        $record = PurchaseRequest::factory()->create();
        $attribute = EntityAttribute::factory()->forEntity($record)->create(['key' => 'raw', 'value' => ['x' => 1]]);

        $raw = DB::table('entity_attributes')->where('id', $attribute->id)->value('value');

        $this->assertSame(['x' => 1], json_decode($raw, true));
        $this->assertIsString($raw);
    }

    public function test_extra_attributes_alias_returns_the_same_rows_as_custom_attributes(): void
    {
        $record = PurchaseRequest::factory()->create();
        $attribute = EntityAttribute::factory()->forEntity($record)->create(['key' => 'origin', 'value' => 'test']);

        $this->assertEquals(
            $record->customAttributes()->pluck('id')->all(),
            $record->extraAttributes()->pluck('id')->all()
        );
        $this->assertTrue($record->extraAttributes->contains('id', $attribute->id));
    }

    public function test_attributes_are_scoped_to_their_own_entity(): void
    {
        $first = PurchaseRequest::factory()->create();
        $second = PurchaseRequest::factory()->create();
        $mine = EntityAttribute::factory()->forEntity($first)->create(['key' => 'k', 'value' => 'one']);
        $theirs = EntityAttribute::factory()->forEntity($second)->create(['key' => 'k', 'value' => 'two']);

        $ids = $first->customAttributes()->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_creating_stamps_the_authenticated_user_as_creator(): void
    {
        $user = User::factory()->create();
        $record = PurchaseRequest::factory()->create();
        $this->actingAs($user);

        $attribute = EntityAttribute::factory()->forEntity($record)->create(['key' => 'k', 'value' => 'v']);

        $this->assertSame($user->id, $attribute->user_id);
        $this->assertTrue($attribute->creator->is($user));
    }

    public function test_updating_stamps_the_editor_only_when_dirty(): void
    {
        $creator = User::factory()->create();
        $editor = User::factory()->create();
        $record = PurchaseRequest::factory()->create();
        $this->actingAs($creator);
        $attribute = EntityAttribute::factory()->forEntity($record)->create(['key' => 'k', 'value' => 'v']);

        $this->actingAs($editor);
        $attribute->save();
        $this->assertNull($attribute->fresh()->updated_by_id);

        $attribute->update(['value' => 'changed']);
        $fresh = $attribute->fresh();

        $this->assertSame($editor->id, $fresh->updated_by_id);
        $this->assertTrue($fresh->updater->is($editor));
        $this->assertSame($creator->id, $fresh->user_id);
    }

    public function test_guest_creation_leaves_stamps_null(): void
    {
        $record = PurchaseRequest::factory()->create();

        $attribute = EntityAttribute::factory()->forEntity($record)->create(['key' => 'k', 'value' => 'v']);

        $this->assertNull($attribute->fresh()->user_id);
        $this->assertNull($attribute->fresh()->creator);
    }

    public function test_fillable_whitelist_pins_the_mass_assignable_columns(): void
    {
        $this->assertSame(
            ['entity_type', 'entity_id', 'key', 'value', 'user_id', 'updated_by_id'],
            (new EntityAttribute)->getFillable()
        );
    }
}