<?php

namespace Tests\Feature;

use App\Models\Traits\General\HasCustomAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EntityAttributeSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['entity_attributes', 'sync_test_entities'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('sync_test_entities', function ($table) {
            $table->id();
            $table->timestamps();
        });

        Schema::create('entity_attributes', function ($table) {
            $table->id();
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');
            $table->string('key');
            $table->json('value')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('updated_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['entity_type', 'entity_id', 'key']);
        });
    }

    public function test_sync_custom_attributes_restores_a_trashed_row_instead_of_colliding(): void
    {
        $entity = SyncTestEntity::create();

        $entity->customAttributes()->create(['key' => 'foo', 'value' => 'bar']);
        $entity->customAttributes()->where('key', 'foo')->delete();

        $this->assertSame(1, $entity->customAttributes()->onlyTrashed()->where('key', 'foo')->count());

        $entity->syncCustomAttributes(['foo' => 'baz']);

        $this->assertSame(1, $entity->customAttributes()->where('key', 'foo')->count());
        $this->assertSame('baz', $entity->customAttributes()->where('key', 'foo')->value('value'));
        $this->assertSame(0, $entity->customAttributes()->onlyTrashed()->where('key', 'foo')->count());
    }

    public function test_naive_update_or_create_still_throws_a_duplicate_entry_error(): void
    {
        $entity = SyncTestEntity::create();

        $entity->customAttributes()->create(['key' => 'foo', 'value' => 'bar']);
        $entity->customAttributes()->where('key', 'foo')->delete();

        $this->expectException(QueryException::class);

        $entity->customAttributes()->updateOrCreate(['key' => 'foo'], ['value' => 'baz']);
    }

    public function test_sync_custom_attributes_soft_deletes_keys_no_longer_present(): void
    {
        $entity = SyncTestEntity::create();

        $entity->syncCustomAttributes(['foo' => 'bar', 'baz' => 'qux']);
        $entity->syncCustomAttributes(['foo' => 'bar']);

        $this->assertSame(1, $entity->customAttributes()->count());
        $this->assertSame(1, $entity->customAttributes()->onlyTrashed()->where('key', 'baz')->count());
    }
}

class SyncTestEntity extends Model
{
    use HasCustomAttributes;

    protected $table = 'sync_test_entities';

    protected $guarded = [];
}
