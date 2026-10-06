<?php

namespace Tests\Feature\Models;

use App\Models\Attachment;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Status;
use App\Services\SmartCacheManager;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StatusModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->useMysql();
        DB::beginTransaction();
        SmartCacheManager::invalidate('Status');
    }

    protected function tearDown(): void
    {
        SmartCacheManager::invalidate('Status');
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
                    config(['database.connections.mysql.' . $cfgKey => $vals[$envKey]]);
                }
            }
        }
        DB::purge('mysql');
        config(['database.default' => 'mysql']);
    }

    // StatusFinder

    public function test_find_by_returns_the_matching_status_for_a_type(): void
    {
        $type = 'FinderType'.uniqid();
        $match = Status::factory()->create(['type' => $type, 'english_type' => $type, 'english_name' => 'Open', 'name' => 'Open']);

        $found = Status::findBy($type, 'Open');

        $this->assertSame($match->id, $found?->id);
    }

    public function test_find_by_name_returns_null_when_nothing_matches(): void
    {
        $this->assertNull(Status::findBy('Nope'.uniqid(), 'Missing'));
    }

    public function test_find_by_without_a_name_returns_the_whole_type_collection(): void
    {
        $type = 'CollectionType'.uniqid();
        $first = Status::factory()->create(['type' => $type, 'english_type' => $type, 'english_name' => 'Open']);
        $second = Status::factory()->create(['type' => $type, 'english_type' => $type, 'english_name' => 'Closed']);
        Status::factory()->create(['english_name' => 'Unrelated']);

        $collection = Status::findBy($type);

        $this->assertCount(2, $collection);
        $this->assertTrue($collection->contains('id', $first->id));
        $this->assertTrue($collection->contains('id', $second->id));
    }

    // scopeSearchStatus

    public function test_search_status_matches_against_either_name(): void
    {
        $uniq = 'St'.uniqid();
        $byName = Status::factory()->create(['name' => "Pending {$uniq}", 'english_name' => 'Other']);
        $byEnglish = Status::factory()->create(['name' => 'Other', 'english_name' => "Reviewing {$uniq}"]);

        $ids = Status::searchStatus($uniq)->pluck('id');

        $this->assertTrue($ids->contains($byName->id));
        $this->assertTrue($ids->contains($byEnglish->id));
    }

    public function test_search_status_expands_positive_words_to_authorized(): void
    {
        $type = 'PositiveType'.uniqid();
        $authorized = Status::factory()->create(['type' => $type, 'english_type' => $type, 'name' => 'Authorized', 'english_name' => 'Authorized']);
        $declined = Status::factory()->create(['type' => $type, 'english_type' => $type, 'name' => 'Declined', 'english_name' => 'Declined']);

        $ids = Status::searchStatus('approved')->pluck('id');

        $this->assertTrue($ids->contains($authorized->id));
        $this->assertFalse($ids->contains($declined->id));
    }

    public function test_search_status_expands_negative_words_to_declined(): void
    {
        $type = 'NegativeType'.uniqid();
        $authorized = Status::factory()->create(['type' => $type, 'english_type' => $type, 'name' => 'Authorized', 'english_name' => 'Authorized']);
        $declined = Status::factory()->create(['type' => $type, 'english_type' => $type, 'name' => 'Declined', 'english_name' => 'Declined']);

        $ids = Status::searchStatus('denied')->pluck('id');

        $this->assertTrue($ids->contains($declined->id));
        $this->assertFalse($ids->contains($authorized->id));
    }

    public function test_search_status_is_a_no_op_for_a_blank_term(): void
    {
        $baseline = Status::count();

        $this->assertSame($baseline, Status::searchStatus('')->count());
        $this->assertSame($baseline, Status::searchStatus('   ')->count());
    }

    // Consumer relations

    public function test_consumer_relations_return_the_records_pointing_at_the_status(): void
    {
        $status = Status::factory()->create(['english_name' => 'Linked']);

        $pr = PurchaseRequest::factory()->create(['status_id' => $status->id]);
        $item = PurchaseRequestItem::factory()->create(['status_id' => $status->id]);
        $attachment = Attachment::factory()->forAttachable($pr)->create(['status_id' => $status->id]);

        $this->assertTrue($status->purchaseRequests->contains('id', $pr->id));
        $this->assertTrue($status->purchaseItems->contains('id', $item->id));
        $this->assertTrue($status->attachments->contains('id', $attachment->id));
    }

    // Observer cascade

    public function test_saving_a_status_invalidates_the_status_cache(): void
    {
        $probe = ['probe' => uniqid()];

        $this->assertSame('warm', SmartCacheManager::remember('Status', $probe, 10, fn () => 'warm'));
        $this->assertSame('warm', SmartCacheManager::remember('Status', $probe, 10, fn () => 'recomputed'));

        Status::factory()->create();

        $this->assertSame('recomputed', SmartCacheManager::remember('Status', $probe, 10, fn () => 'recomputed'));
    }

    // Soft deletes

    public function test_soft_delete_hides_the_row_and_the_cache_is_invalidated_on_delete(): void
    {
        $probe = ['probe' => uniqid()];
        SmartCacheManager::remember('Status', $probe, 10, fn () => 'warm');

        $status = Status::factory()->create(['english_name' => 'Deletable']);
        $status->delete();

        $this->assertNull(Status::find($status->id));
        $this->assertNotNull(Status::withTrashed()->find($status->id));
        $this->assertSame('recomputed', SmartCacheManager::remember('Status', $probe, 10, fn () => 'recomputed'));
    }
}
